<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "layouts" milestone: one widget's placement on one dashboard.
 */
final class DashboardWidget
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $dashboardUid,
        public readonly string $widgetKey,
        public int $positionX,
        public int $positionY,
        public int $width,
        public int $height,
        public array $config,
        public int $sortOrder,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function create(
        string $organizationId,
        string $dashboardUid,
        string $widgetKey,
        int $positionX = 0,
        int $positionY = 0,
        int $width = 4,
        int $height = 3,
        array $config = [],
        int $sortOrder = 0,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            dashboardUid: $dashboardUid,
            widgetKey: $widgetKey,
            positionX: $positionX,
            positionY: $positionY,
            width: $width,
            height: $height,
            config: $config,
            sortOrder: $sortOrder,
        );
    }
}
