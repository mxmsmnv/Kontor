<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Infrastructure\Persistence;

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
            'SELECT * FROM kontor_dashboard_widgets WHERE dashboard_uid = :dashboard_uid ORDER BY sort_order ASC'
        );
        $statement->execute(['dashboard_uid' => $dashboardUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
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
