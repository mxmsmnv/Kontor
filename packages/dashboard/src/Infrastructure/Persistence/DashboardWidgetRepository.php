<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Dashboard\Domain\DashboardWidget;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class DashboardWidgetRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(DashboardWidget $widget): void
    {
        $organizationId = $this->organizations->internalIdOf($widget->organizationId);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_dashboard_widgets
                (uid, organization_id, dashboard_uid, widget_key, position_x, position_y, width, height,
                 config_json, sort_order, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :dashboard_uid, :widget_key, :position_x, :position_y, :width, :height,
                 :config_json, :sort_order, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                position_x = VALUES(position_x), position_y = VALUES(position_y), width = VALUES(width),
                height = VALUES(height), config_json = VALUES(config_json), sort_order = VALUES(sort_order),
                updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $widget->uid->toString(),
            'organization_id' => $organizationId,
            'dashboard_uid' => $widget->dashboardUid,
            'widget_key' => $widget->widgetKey,
            'position_x' => $widget->positionX,
            'position_y' => $widget->positionY,
            'width' => $widget->width,
            'height' => $widget->height,
            'config_json' => $widget->config !== [] ? json_encode($widget->config, JSON_THROW_ON_ERROR) : null,
            'sort_order' => $widget->sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function find(string $uid): ?DashboardWidget
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_dashboard_widgets WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): DashboardWidget
    {
        return $this->find($uid) ?? throw new RuntimeException("Dashboard widget \"{$uid}\" was not found.");
    }

    /**
     * @return DashboardWidget[]
     */
    public function forDashboard(string $dashboardUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_dashboard_widgets WHERE dashboard_uid = :dashboard_uid ORDER BY sort_order, id'
        );
        $statement->execute(['dashboard_uid' => $dashboardUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function shift(DashboardWidget $widget, bool $earlier): bool
    {
        $scope = [
            'dashboard_uid' => $widget->dashboardUid,
            'organization_id' => $this->organizations->internalIdOf($widget->organizationId),
        ];
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $select = $this->pdo->prepare(
                'SELECT uid, sort_order FROM kontor_dashboard_widgets
                 WHERE dashboard_uid = :dashboard_uid AND organization_id = :organization_id
                 ORDER BY sort_order, id' . DatabaseConcurrency::forUpdate($this->pdo)
            );
            $select->execute($scope);
            $widgets = $select->fetchAll(\PDO::FETCH_ASSOC);
            $index = array_search($widget->uid->toString(), array_column($widgets, 'uid'), true);
            $neighbor = $index === false ? null : $index + ($earlier ? -1 : 1);
            $changed = $neighbor !== null && isset($widgets[$neighbor]);

            if ($changed) {
                [$widgets[$index], $widgets[$neighbor]] = [$widgets[$neighbor], $widgets[$index]];
                $update = $this->pdo->prepare(
                    'UPDATE kontor_dashboard_widgets SET sort_order = :sort_order, updated_at = :updated_at
                     WHERE uid = :uid AND dashboard_uid = :dashboard_uid AND organization_id = :organization_id'
                );
                $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
                foreach ($widgets as $sortOrder => $row) {
                    if ((int) $row['sort_order'] !== $sortOrder) {
                        $update->execute($scope + ['uid' => $row['uid'], 'sort_order' => $sortOrder, 'updated_at' => $now]);
                    }
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $changed;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function remove(string $uid): void
    {
        $statement = $this->pdo->prepare('DELETE FROM kontor_dashboard_widgets WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);
    }

    private function hydrate(array $row): DashboardWidget
    {
        return new DashboardWidget(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            dashboardUid: $row['dashboard_uid'],
            widgetKey: $row['widget_key'],
            positionX: (int) $row['position_x'],
            positionY: (int) $row['position_y'],
            width: (int) $row['width'],
            height: (int) $row['height'],
            config: $row['config_json'] !== null ? json_decode($row['config_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
