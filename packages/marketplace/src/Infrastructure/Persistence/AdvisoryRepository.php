<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Infrastructure\Persistence;

use Kontor\Marketplace\Domain\Advisory;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class AdvisoryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function find(string $uid): ?Advisory
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_marketplace_advisories WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): Advisory
    {
        return $this->find($uid) ?? throw new RuntimeException("Advisory \"{$uid}\" was not found.");
    }

    public function insert(Advisory $advisory): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_marketplace_advisories
                (uid, package, affected_versions, severity, title, description, url, published_at)
             VALUES
                (:uid, :package, :affected_versions, :severity, :title, :description, :url, :published_at)'
        );

        $statement->execute([
            'uid' => $advisory->uid->toString(),
            'package' => $advisory->package,
            'affected_versions' => $advisory->affectedVersions,
            'severity' => $advisory->severity,
            'title' => $advisory->title,
            'description' => $advisory->description,
            'url' => $advisory->url,
            'published_at' => $advisory->publishedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Used by `RegistrySyncService` to avoid inserting the same advisory
     * again on every sync — advisories have no natural unique key of
     * their own (unlike listings, keyed by `(registry_name, package)`),
     * so re-publication is detected by an exact match on
     * package + title + publishedAt instead.
     */
    public function findExisting(string $package, string $title, \DateTimeImmutable $publishedAt): ?Advisory
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_marketplace_advisories
             WHERE package = :package AND title = :title AND published_at = :published_at'
        );
        $statement->execute([
            'package' => $package,
            'title' => $title,
            'published_at' => $publishedAt->format('Y-m-d H:i:s.u'),
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return Advisory[]
     */
    public function forPackage(string $package): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_marketplace_advisories WHERE package = :package ORDER BY published_at DESC');
        $statement->execute(['package' => $package]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Advisory[]
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_marketplace_advisories ORDER BY published_at DESC');

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Advisory
    {
        return new Advisory(
            uid: Uid::fromString($row['uid']),
            package: $row['package'],
            affectedVersions: $row['affected_versions'],
            severity: $row['severity'],
            title: $row['title'],
            description: $row['description'],
            url: $row['url'],
            publishedAt: new \DateTimeImmutable($row['published_at']),
        );
    }
}
