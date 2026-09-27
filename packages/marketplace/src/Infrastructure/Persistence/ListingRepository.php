<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Infrastructure\Persistence;

use Kontor\Marketplace\Domain\MarketplaceListing;

final class ListingRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function find(string $registryName, string $package): ?MarketplaceListing
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_marketplace_listings WHERE registry_name = :registry_name AND package = :package'
        );
        $statement->execute(['registry_name' => $registryName, 'package' => $package]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(MarketplaceListing $listing): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_marketplace_listings
                (registry_name, package, name, version, title, description, license, repository_url,
                 publisher_name, manifest_json, synced_at)
             VALUES
                (:registry_name, :package, :name, :version, :title, :description, :license, :repository_url,
                 :publisher_name, :manifest_json, :synced_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), version = VALUES(version), title = VALUES(title),
                description = VALUES(description), license = VALUES(license),
                repository_url = VALUES(repository_url), publisher_name = VALUES(publisher_name),
                manifest_json = VALUES(manifest_json), synced_at = VALUES(synced_at)'
        );

        $statement->execute([
            'registry_name' => $listing->registryName,
            'package' => $listing->package,
            'name' => $listing->name,
            'version' => $listing->version,
            'title' => $listing->title,
            'description' => $listing->description,
            'license' => $listing->license,
            'repository_url' => $listing->repositoryUrl,
            'publisher_name' => $listing->publisherName,
            'manifest_json' => json_encode($listing->manifest, JSON_THROW_ON_ERROR),
            'synced_at' => $listing->syncedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Every known listing for a package, across every registry it
     * appears in.
     *
     * @return MarketplaceListing[]
     */
    public function forPackage(string $package): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_marketplace_listings WHERE package = :package ORDER BY registry_name');
        $statement->execute(['package' => $package]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return MarketplaceListing[]
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_marketplace_listings ORDER BY package, registry_name');

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): MarketplaceListing
    {
        return new MarketplaceListing(
            registryName: $row['registry_name'],
            package: $row['package'],
            name: $row['name'],
            version: $row['version'],
            title: $row['title'],
            description: $row['description'],
            license: $row['license'],
            repositoryUrl: $row['repository_url'],
            publisherName: $row['publisher_name'],
            manifest: json_decode($row['manifest_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            syncedAt: new \DateTimeImmutable($row['synced_at']),
        );
    }
}
