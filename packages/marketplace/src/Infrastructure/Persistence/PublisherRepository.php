<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Infrastructure\Persistence;

use Kontor\Marketplace\Domain\Publisher;

final class PublisherRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function find(string $name): ?Publisher
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_marketplace_publishers WHERE name = :name');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Publisher $publisher): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_marketplace_publishers (name, url, verified, created_at, updated_at)
             VALUES (:name, :url, :verified, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                url = VALUES(url), verified = VALUES(verified), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'name' => $publisher->name,
            'url' => $publisher->url,
            'verified' => $publisher->verified ? 1 : 0,
            'created_at' => $publisher->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $publisher->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return Publisher[]
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_marketplace_publishers ORDER BY name');

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Publisher
    {
        return new Publisher(
            name: $row['name'],
            url: $row['url'],
            verified: (bool) $row['verified'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
