<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Persistence;

use Kontor\CRM\Domain\Stage;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * kontor.md#13.3
 */
final class StageRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function save(Stage $stage): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_stages
                (uid, pipeline_uid, name_key, display_name_json, probability, sort_order, state_type, color, rules_json)
             VALUES
                (:uid, :pipeline_uid, :name_key, :display_name_json, :probability, :sort_order, :state_type, :color, :rules_json)
             ON DUPLICATE KEY UPDATE
                name_key = VALUES(name_key), display_name_json = VALUES(display_name_json),
                probability = VALUES(probability), sort_order = VALUES(sort_order),
                state_type = VALUES(state_type), color = VALUES(color), rules_json = VALUES(rules_json)'
        );

        $statement->execute([
            'uid' => $stage->uid->toString(),
            'pipeline_uid' => $stage->pipelineUid,
            'name_key' => $stage->nameKey,
            'display_name_json' => json_encode($stage->displayName, JSON_THROW_ON_ERROR),
            'probability' => $stage->probability,
            'sort_order' => $stage->sortOrder,
            'state_type' => $stage->stateType,
            'color' => $stage->color,
            'rules_json' => $stage->rules !== [] ? json_encode($stage->rules, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function find(string $uid): ?Stage
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_stages WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): Stage
    {
        return $this->find($uid) ?? throw new RuntimeException("Stage \"{$uid}\" was not found.");
    }

    /**
     * @return array<int, Stage> ordered by sort_order ascending
     */
    public function forPipeline(string $pipelineUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_stages WHERE pipeline_uid = :pipeline_uid ORDER BY sort_order ASC');
        $statement->execute(['pipeline_uid' => $pipelineUid]);

        return array_map(fn (array $row): Stage => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function firstOpenStage(string $pipelineUid): ?Stage
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_crm_stages WHERE pipeline_uid = :pipeline_uid AND state_type = 'open' ORDER BY sort_order ASC LIMIT 1"
        );
        $statement->execute(['pipeline_uid' => $pipelineUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function firstStageOfType(string $pipelineUid, string $stateType): ?Stage
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_crm_stages WHERE pipeline_uid = :pipeline_uid AND state_type = :state_type ORDER BY sort_order ASC LIMIT 1'
        );
        $statement->execute(['pipeline_uid' => $pipelineUid, 'state_type' => $stateType]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    private function hydrate(array $row): Stage
    {
        return new Stage(
            uid: Uid::fromString($row['uid']),
            pipelineUid: $row['pipeline_uid'],
            nameKey: $row['name_key'],
            displayName: json_decode($row['display_name_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            probability: (int) $row['probability'],
            sortOrder: (int) $row['sort_order'],
            stateType: $row['state_type'],
            color: $row['color'],
            rules: $row['rules_json'] !== null ? json_decode($row['rules_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }
}
