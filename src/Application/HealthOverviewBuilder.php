<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

final class HealthOverviewBuilder
{
    /**
     * @param array<int, array{key: string, result: \Kontor\SDK\DTO\HealthCheckResult}> $checks
     * @return array{
     *   checks: array<int, array{key: string, result: \Kontor\SDK\DTO\HealthCheckResult}>,
     *   counts: array{ok: int, warning: int, critical: int},
     *   overall: string
     * }
     */
    public function build(array $checks, string $query = '', string $status = ''): array
    {
        $counts = ['ok' => 0, 'warning' => 0, 'critical' => 0];

        foreach ($checks as $check) {
            $counts[$check['result']->status]++;
        }

        $query = mb_strtolower(trim($query));
        $filtered = array_values(array_filter(
            $checks,
            static function (array $check) use ($query, $status): bool {
                $result = $check['result'];

                if ($status !== '' && $result->status !== $status) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                $details = json_encode(
                    $result->details,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                $haystack = mb_strtolower(
                    $check['key'] . ' ' . $result->message . ' ' . ($details ?: '')
                );

                return str_contains($haystack, $query);
            }
        ));

        return [
            'checks' => $filtered,
            'counts' => $counts,
            'overall' => $counts['critical'] > 0
                ? 'critical'
                : ($counts['warning'] > 0 ? 'warning' : 'ok'),
        ];
    }
}
