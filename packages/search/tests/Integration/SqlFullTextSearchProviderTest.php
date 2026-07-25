<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\Search\Infrastructure\Search\SqlFullTextSearchProvider;

final class SqlFullTextSearchProviderTest extends DatabaseTestCase
{
    private string $organizationUid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec(<<<SQL
            CREATE TABLE kontor_search_test_articles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                summary VARCHAR(500) NULL,
                body TEXT NOT NULL,
                FULLTEXT KEY ft_search (title, body)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);

        $organizations = new OrganizationRepository($this->pdo);
        $this->organizationUid = $organizations->defaultOrganization('US', 'en', 'EUR')->uid->toString();
    }

    private function provider(): SqlFullTextSearchProvider
    {
        return new SqlFullTextSearchProvider(
            pdo: $this->pdo,
            organizations: new OrganizationRepository($this->pdo),
            providerName: 'articles',
            entityType: 'article',
            table: 'kontor_search_test_articles',
            uidColumn: 'uid',
            titleColumn: 'title',
            subtitleColumn: 'summary',
            fullTextColumns: ['title', 'body'],
        );
    }

    private function insertArticle(string $uid, string $title, string $body, string $summary = ''): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organizationId = $organizations->internalIdOf($this->organizationUid);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_search_test_articles (uid, organization_id, title, summary, body)
             VALUES (:uid, :organization_id, :title, :summary, :body)'
        );
        $statement->execute([
            'uid' => $uid,
            'organization_id' => $organizationId,
            'title' => $title,
            'summary' => $summary,
            'body' => $body,
        ]);
    }

    public function test_supports_only_its_configured_entity_type(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->supports('article'));
        $this->assertFalse($provider->supports('contact'));
    }

    public function test_finds_a_matching_article_by_fulltext(): void
    {
        $this->insertArticle('art_01', 'Kontor Release Notes', 'Kontor now supports modular components.');
        $this->insertArticle('art_02', 'Unrelated', 'Nothing to do with the search term.');

        $result = $this->provider()->search(new SearchQuery($this->organizationUid, 'modular components'));

        $this->assertSame(1, $result->total);
        $this->assertSame('art_01', $result->hits[0]->entityUid);
        $this->assertSame('article', $result->hits[0]->entityType);
        $this->assertGreaterThan(0.0, $result->hits[0]->score);
    }

    public function test_ranks_a_title_match_above_a_body_only_match(): void
    {
        $this->insertArticle('art_title', 'Invoice Automation', 'General overview text.');
        $this->insertArticle('art_body', 'General overview', 'Mentions invoice automation only once in passing.');

        $result = $this->provider()->search(new SearchQuery($this->organizationUid, 'invoice automation'));

        $this->assertSame('art_title', $result->hits[0]->entityUid);
    }

    public function test_scopes_results_to_the_given_organization(): void
    {
        // defaultOrganization() always returns the first org once one exists,
        // so a second organization needs to be created explicitly here.
        $secondOrg = \Kontor\Core\Domain\Organization::createDefault('DE', 'de', 'EUR');
        (new OrganizationRepository($this->pdo))->save($secondOrg);

        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($secondOrg->uid->toString());
        $this->pdo->prepare(
            'INSERT INTO kontor_search_test_articles (uid, organization_id, title, body) VALUES (:uid, :oid, :title, :body)'
        )->execute(['uid' => 'art_other_org', 'oid' => $organizationId, 'title' => 'Shared Term', 'body' => 'shared term content']);

        $this->insertArticle('art_mine', 'Shared Term', 'shared term content');

        $result = $this->provider()->search(new SearchQuery($this->organizationUid, 'shared term'));

        $this->assertSame(1, $result->total);
        $this->assertSame('art_mine', $result->hits[0]->entityUid);
    }

    public function test_no_match_returns_an_empty_result(): void
    {
        $this->insertArticle('art_01', 'Something', 'Completely different subject matter.');

        $result = $this->provider()->search(new SearchQuery($this->organizationUid, 'nonexistenttermxyz'));

        $this->assertSame([], $result->hits);
        $this->assertSame(0, $result->total);
    }

    public function test_respects_limit_and_offset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertArticle("art_{$i}", "Widget Report {$i}", 'widget report content');
        }

        $result = $this->provider()->search(new SearchQuery($this->organizationUid, 'widget report', limit: 2, offset: 2));

        $this->assertSame(5, $result->total);
        $this->assertCount(2, $result->hits);
    }
}
