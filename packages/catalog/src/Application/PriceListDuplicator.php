<?php

declare(strict_types=1);

namespace Kontor\Catalog\Application;

use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;

final class PriceListDuplicator
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PriceListRepository $priceLists,
        private readonly PriceRepository $prices,
    ) {
    }

    public function duplicate(PriceList $source, string $nameSuffix = ' (copy)'): PriceList
    {
        $duplicate = $source->duplicate($nameSuffix);
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->priceLists->save($duplicate);

            foreach ($this->prices->forPriceList($source->uid->toString()) as $entry) {
                $this->prices->save($entry->copyToPriceList($duplicate->uid->toString()));
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return $duplicate;
    }
}
