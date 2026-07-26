<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

final class BackupOverviewBuilder
{
    /**
     * @param array<int, array<string, mixed>> $backups
     * @return array{
     *   backups: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   totalPages: int
     * }
     */
    public function build(
        array $backups,
        string $query = '',
        string $component = '',
        string $status = '',
        int $page = 1,
        int $pageSize = 20,
    ): array {
        $query = mb_strtolower(trim($query));
        $pageSize = max(1, min($pageSize, 100));
        $filtered = array_values(array_filter(
            $backups,
            static function (array $backup) use ($query, $component, $status): bool {
                if ($component !== '' && (string) ($backup['component'] ?? '') !== $component) {
                    return false;
                }

                $verified = (bool) ($backup['verified'] ?? false);

                if ($status === 'verified' && !$verified) {
                    return false;
                }

                if ($status === 'failed' && $verified) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($backup['id'] ?? ''),
                    (string) ($backup['component'] ?? ''),
                    (string) ($backup['kind'] ?? ''),
                ]));

                return str_contains($haystack, $query);
            }
        ));
        $total = count($filtered);
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($totalPages, max(1, $page));

        return [
            'backups' => array_slice($filtered, ($page - 1) * $pageSize, $pageSize),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ];
    }
}
