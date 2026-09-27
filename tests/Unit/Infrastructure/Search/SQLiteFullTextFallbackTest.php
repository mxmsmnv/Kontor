<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Search;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\Search\Infrastructure\Search\SqlFullTextSearchProvider;
use PHPUnit\Framework\TestCase;

final class SQLiteFullTextFallbackTest extends TestCase
{
    public function testSearchUsesEscapedLikeFallbackOnSqlite(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE kontor_organizations (id INTEGER PRIMARY KEY, uid TEXT NOT NULL)');
        $pdo->exec("INSERT INTO kontor_organizations (id, uid) VALUES (1, 'org-1')");
        $pdo->exec('CREATE TABLE contacts (organization_id INTEGER, uid TEXT, title TEXT, email TEXT)');
        $pdo->exec("INSERT INTO contacts VALUES (1, 'a', 'Alice 100%', 'alice@example.test')");
        $pdo->exec("INSERT INTO contacts VALUES (1, 'b', 'Alice 1000', 'other@example.test')");

        $provider = new SqlFullTextSearchProvider(
            pdo: $pdo,
            organizations: new OrganizationRepository($pdo),
            providerName: 'contacts',
            entityType: 'contact',
            table: 'contacts',
            uidColumn: 'uid',
            titleColumn: 'title',
            subtitleColumn: 'email',
            fullTextColumns: ['title', 'email'],
        );

        $result = $provider->search(new SearchQuery(
            organizationId: 'org-1',
            term: '100%',
            limit: 20,
            offset: 0,
        ));

        self::assertCount(1, $result->hits);
        self::assertSame('a', $result->hits[0]->entityUid);
        self::assertSame(1, $result->total);
    }
}
