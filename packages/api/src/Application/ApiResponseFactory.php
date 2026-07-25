<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#20.2 "standard response" / #20.3 "standard error" / #20.4
 * "pagination". Pure — builds plain arrays ready for `json_encode()`.
 */
final class ApiResponseFactory
{
    public function newRequestId(): string
    {
        return 'req_'.Uid::generate()->toString();
    }

    /**
     * @param array<string, mixed> $extraMeta
     * @return array<string, mixed>
     */
    public function success(mixed $data, string $requestId, array $extraMeta = []): array
    {
        return [
            'data' => $data,
            'meta' => array_merge(['requestId' => $requestId, 'version' => 'v1'], $extraMeta),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collection(ApiCollectionResult $result, ApiQuery $query, string $requestId): array
    {
        $totalPages = $query->pageSize > 0 ? (int) ceil($result->total / $query->pageSize) : 0;

        return $this->success($result->rows, $requestId, [
            'page' => $query->page,
            'pageSize' => $query->pageSize,
            'total' => $result->total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public function error(string $code, string $message, array $details, string $requestId): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'requestId' => $requestId,
            ],
        ];
    }
}
