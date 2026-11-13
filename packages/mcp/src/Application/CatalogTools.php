<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use Kontor\API\Application\IdempotencyKeyConflictException;
use Kontor\API\Application\IdempotencyService;
use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\KontorCatalog;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use stdClass;

trait CatalogTools
{
    private function catalogTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorCatalog')) {
            return [];
        }
        $kind = ['type' => 'string', 'enum' => ['item', 'category', 'pricelist']];
        $page = [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
        ];
        $key = $this->idempotencySchema();

        return [
            $this->tool('kontor_catalog_list', 'List catalog records',
                'Search catalog items, categories or price lists in your organization. Query matches names, item SKU or barcode. Archived filtering applies to items and categories.',
                $this->mcpCatalogList(...), 'read', [
                    'kind' => $kind, 'query' => ['type' => 'string', 'maxLength' => 160],
                    'archived' => ['type' => 'boolean'],
                ] + $page, ['kind']),
            $this->tool('kontor_catalog_get', 'Get a catalog record',
                'Read a catalog item, category or price list by UID in your organization.',
                $this->mcpCatalogGet(...), 'read', ['kind' => $kind, 'uid' => $this->uidSchema()], ['kind', 'uid']),
            $this->tool('kontor_catalog_save', 'Create or update a catalog record',
                'Omit uid to create; provide uid to update. Supply only fields to change. Use preview=true to validate without saving. Read catalog references for available units, tax codes and field names.',
                $this->mcpCatalogSave(...), 'draft', [
                    'kind' => $kind, 'uid' => $this->uidSchema(),
                    'fields' => $this->jsonDocumentSchema(16_384, 'JSON object with catalog fields. Names and descriptions use locale-keyed objects; prices use amount_minor and currency.'),
                    'idempotency_key' => $key, 'preview' => ['type' => 'boolean'],
                ], ['kind', 'fields', 'idempotency_key']),
            $this->tool('kontor_catalog_price_tiers_list', 'List catalog price tiers',
                'List tiers for a price list. Optionally filter to one catalog item. Requires price list and catalog item view.',
                $this->mcpCatalogPriceTiersList(...), 'read', [
                    'price_list_uid' => $this->uidSchema(), 'item_uid' => $this->uidSchema(),
                ] + $page, ['price_list_uid']),
            $this->tool('kontor_catalog_price_tier_save', 'Set a catalog price tier',
                'Set the price for one catalog item and minimum quantity in one price list. The list determines currency. Confirm the price with the user before saving.',
                $this->mcpCatalogPriceTierSave(...), 'draft', [
                    'price_list_uid' => $this->uidSchema(), 'item_uid' => $this->uidSchema(),
                    'quantity' => ['type' => 'string', 'pattern' => '^(?:0|[1-9][0-9]{0,7})(?:\\.[0-9]{1,6})?$'],
                    'amount_minor' => ['type' => 'integer'], 'idempotency_key' => $key,
                    'valid_from' => ['type' => ['string', 'null'], 'maxLength' => 10],
                    'valid_to' => ['type' => ['string', 'null'], 'maxLength' => 10],
                    'preview' => ['type' => 'boolean'],
                ], ['price_list_uid', 'item_uid', 'quantity', 'amount_minor', 'idempotency_key']),
            $this->tool('kontor_catalog_references_get', 'Get catalog field references',
                'Discover default language and currency, available units and tax codes, and writable fields by record kind.',
                $this->mcpCatalogReferencesGet(...)),
        ];
    }

    public function mcpCatalogList(
        string $kind,
        string $query = '',
        bool $archived = false,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $actor = $this->catalogActor($kind, 'view');
        $this->catalogCheckPage($limit, $offset);
        if (mb_strlen($query) > 160 || ($kind === 'pricelist' && $archived)) {
            throw new WireException('Invalid catalog search filters.');
        }
        $catalog = $this->catalogModule();
        $organization = $this->catalogOrganization()->uid->toString();
        $repository = $this->catalogRepository($catalog, $kind);
        $query = trim($query);
        $options = $kind === 'pricelist' ? [] : ['archived' => $archived];
        $rows = $repository->findAll($organization, $query, ...($options + ['limit' => $limit, 'offset' => $offset]));

        return [
            'items' => array_map(fn ($row): array => $this->catalogPresent($row, $actor), $rows),
            'total' => $repository->countMatching($organization, $query, ...$options),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function mcpCatalogGet(string $kind, string $uid): array
    {
        $actor = $this->catalogActor($kind, 'view');
        $record = $this->catalogOwned($this->catalogModule(), $kind, $uid, $this->catalogOrganization()->uid->toString());
        return $this->catalogPresent($record, $actor, detail: true);
    }

    public function mcpCatalogReferencesGet(): array
    {
        $this->catalogActor('item', 'view');
        $organization = $this->catalogOrganization();
        return [
            'default_language' => $organization->defaultLanguage,
            'default_currency' => $organization->defaultCurrency,
            'units' => (new UnitOfMeasure())->all(),
            'tax_codes' => (new TaxCode())->all(),
            'item_types' => ['product', 'service'],
            'fields' => [
                'item' => $this->catalogFields('item'),
                'category' => $this->catalogFields('category'),
                'pricelist' => $this->catalogFields('pricelist'),
            ],
        ];
    }

    public function mcpCatalogSave(
        string $kind,
        string $fields,
        string $idempotency_key,
        ?string $uid = null,
        bool $preview = false,
    ): array {
        $actor = $this->catalogActor($kind, $uid === null ? 'create' : 'edit');
        $catalog = $this->catalogModule();
        $organization = $this->catalogOrganization();
        $organizationUid = $organization->uid->toString();
        $fields = $this->catalogDecodeFields($kind, $fields);

        return $this->catalogWrite($actor, $organizationUid, $idempotency_key, ['kind' => $kind, 'uid' => $uid, 'fields' => $fields],
            $preview, function () use ($actor, $catalog, $kind, $uid, $fields, $organization, $organizationUid, $preview): array {
                $record = $uid === null
                    ? match ($kind) {
                        'item' => CatalogItem::create($organizationUid, []),
                        'category' => Category::create($organizationUid, []),
                        'pricelist' => PriceList::create($organizationUid, '', $organization->defaultCurrency),
                    }
                    : $this->catalogOwned($catalog, $kind, $uid, $organizationUid, !$preview);
                if ($record instanceof CatalogItem) {
                    $this->catalogFillItem($catalog, $record, $fields, $organization, $actor);
                } elseif ($record instanceof Category) {
                    $this->catalogFillCategory($catalog, $record, $fields, $organization, !$preview);
                } else {
                    $this->catalogFillPriceList($catalog, $record, $fields);
                }
                $entity = match ($kind) {
                    'item' => 'catalog_item', 'category' => 'catalog_category', 'pricelist' => 'catalog_price_list',
                };
                $recordUid = $record->uid->toString();
                if (!$preview) {
                    $repository = $this->catalogRepository($catalog, $kind);
                    if ($record instanceof CatalogItem) {
                        $repository->saveByUid($record, $uid === null);
                    } else {
                        $repository->save($record);
                    }
                    $this->catalogAudit($actor, $organizationUid, $entity, $recordUid, $uid === null ? 'created' : 'updated');
                }
                $body = ['kind' => $kind, 'uid' => $preview && $uid === null ? null : $recordUid, 'saved' => !$preview];
                if ($preview) {
                    $body['proposed'] = array_intersect_key($this->catalogPresent($record, $actor, detail: true), $fields);
                }
                return $body;
            });
    }

    public function mcpCatalogPriceTiersList(string $price_list_uid, ?string $item_uid = null, int $limit = 20, int $offset = 0): array
    {
        $this->catalogActor('pricelist', 'view');
        $this->catalogActor('item', 'view');
        $this->catalogCheckPage($limit, $offset);
        $organization = $this->catalogOrganization()->uid->toString();
        $catalog = $this->catalogModule();
        $this->catalogOwned($catalog, 'pricelist', $price_list_uid, $organization);
        if ($item_uid !== null) {
            $this->catalogOwned($catalog, 'item', $item_uid, $organization);
        }
        $rows = $catalog->priceRepository()->listOrganizationTiers($organization, $price_list_uid, $item_uid, $limit + 1, $offset);

        return [
            'items' => array_map($this->catalogPresentTier(...), array_slice($rows, 0, $limit)),
            'has_more' => count($rows) > $limit,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function mcpCatalogPriceTierSave(
        string $price_list_uid,
        string $item_uid,
        string $quantity,
        int $amount_minor,
        string $idempotency_key,
        ?string $valid_from = null,
        ?string $valid_to = null,
        bool $preview = false,
    ): array {
        $actor = $this->catalogActor('pricelist', 'edit');
        $this->catalogActor('item', 'view');
        if (preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,6})?$/D', $quantity) !== 1 || (float) $quantity <= 0) {
            throw new WireException('Minimum quantity must be positive, with up to eight whole and six decimal digits.');
        }
        $from = $this->catalogDate($valid_from);
        $to = $this->catalogDate($valid_to);
        $this->catalogDateRange($from, $to);
        $catalog = $this->catalogModule();
        $organization = $this->catalogOrganization()->uid->toString();
        $arguments = [$price_list_uid, $item_uid, (float) $quantity, $amount_minor, $valid_from, $valid_to];

        return $this->catalogWrite($actor, $organization, $idempotency_key, ['tier' => $arguments], $preview,
            function () use ($catalog, $organization, $actor, $price_list_uid, $item_uid, $quantity, $amount_minor, $from, $to, $preview): array {
                $list = $this->catalogOwned($catalog, 'pricelist', $price_list_uid, $organization, !$preview);
                $this->catalogOwned($catalog, 'item', $item_uid, $organization);
                $tier = new PriceListEntry($price_list_uid, $item_uid, Money::ofMinor($amount_minor, $list->currencyCode), (float) $quantity, $from, $to);
                if (!$preview) {
                    $catalog->priceRepository()->save($tier);
                    $this->catalogAudit($actor, $organization, 'catalog_price', $item_uid, 'updated', ['price_list_uid' => $price_list_uid]);
                }
                return ['saved' => !$preview] + $this->catalogPresentTier($tier);
            });
    }

    private function catalogActor(string $kind, string $permission): User
    {
        $this->catalogFields($kind);
        $actor = $this->mcpActor();
        if ($actor === null || !$this->catalogCan($actor, $kind, $permission)) {
            throw new WirePermissionException('The linked user lacks the required catalog permission.');
        }
        return $actor;
    }

    private function catalogCan(User $actor, string $kind, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission('kontor-catalog-' . $kind . '-' . $permission);
    }

    private function catalogModule(): KontorCatalog
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorCatalog')) {
            throw new Wire404Exception('Kontor Catalog is unavailable.');
        }
        return $modules->get('KontorCatalog');
    }

    private function catalogOrganization(): Organization
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
    }

    private function catalogRepository(KontorCatalog $catalog, string $kind): object
    {
        return match ($kind) {
            'item' => $catalog->itemRepository(),
            'category' => $catalog->categoryRepository(),
            'pricelist' => $catalog->priceListRepository(),
        };
    }

    private function catalogOwned(KontorCatalog $catalog, string $kind, string $uid, string $organization, bool $lock = false): CatalogItem|Category|PriceList
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Catalog UID must be a valid ULID.');
        }
        $record = $this->catalogRepository($catalog, $kind)->find($uid, $lock);
        if ($record === null || $record->organizationId !== $organization) {
            throw new Wire404Exception('The catalog record was not found.');
        }
        return $record;
    }

    private function catalogCheckPage(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 50 || $offset < 0 || $offset > 10000) {
            throw new WireException('Invalid catalog page limit or offset.');
        }
    }

    private function catalogFields(string $kind): array
    {
        return match ($kind) {
            'item' => ['title', 'description', 'item_type', 'sku', 'barcode', 'category_uid', 'unit_code', 'tax_code', 'track_inventory', 'status', 'sales_price', 'purchase_price', 'cost_price'],
            'category' => ['name', 'parent_uid', 'sort_order', 'status'],
            'pricelist' => ['name', 'currency', 'status', 'valid_from', 'valid_to'],
            default => throw new WireException('Invalid catalog record kind.'),
        };
    }

    private function catalogDecodeFields(string $kind, string $document): array
    {
        try {
            $data = strlen($document) <= 16_384 ? json_decode($document, false, 8, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            $data = null;
        }
        if (!$data instanceof stdClass || (array) $data === [] || array_diff(array_keys((array) $data), $this->catalogFields($kind)) !== []) {
            throw new WireException('Provide a nonempty JSON object containing supported catalog fields.');
        }
        $fields = (array) $data;
        foreach ($fields as &$value) {
            if ($value instanceof stdClass) {
                $value = (array) $value;
                ksort($value);
            }
        }
        ksort($fields);
        return $fields;
    }

    private function catalogFillItem(KontorCatalog $catalog, CatalogItem $item, array $fields, Organization $organization, User $actor): void
    {
        if (array_key_exists('item_type', $fields)) {
            $item->itemType = $this->catalogOption($fields['item_type'], ['product', 'service'], 'item_type');
        }
        if (array_key_exists('title', $fields)) {
            $item->title = $this->catalogTranslations($item->title, $fields['title'], 255);
        }
        if (array_key_exists('description', $fields)) {
            $item->description = $this->catalogTranslations($item->description, $fields['description'], 8000);
        }
        if (($item->title[$organization->defaultLanguage] ?? '') === '') {
            throw new WireException('A title in the organization default language is required.');
        }
        if (array_key_exists('sku', $fields)) {
            $sku = $this->catalogText($fields['sku'], 100);
            $existing = $sku === null ? null : $catalog->itemRepository()->findBySku($item->organizationId, $sku);
            if ($existing !== null && $existing->uid->toString() !== $item->uid->toString()) {
                throw new WireException('This SKU belongs to another catalog item.');
            }
            $item->sku = $sku;
        }
        if (array_key_exists('barcode', $fields)) {
            $item->barcode = $this->catalogText($fields['barcode'], 100);
        }
        if (array_key_exists('category_uid', $fields)) {
            $uid = $fields['category_uid'];
            if (!$this->catalogCan($actor, 'category', 'view')) {
                throw new WirePermissionException('The linked user cannot select catalog categories.');
            }
            $item->categoryUid = $uid === null ? null : $this->catalogOwned(
                $catalog, 'category', $this->catalogText($uid, 26) ?? '', $item->organizationId,
            )->uid->toString();
        }
        if (array_key_exists('unit_code', $fields)) {
            $unit = $this->catalogText($fields['unit_code'], 20);
            if ($unit === null || ($unit !== $item->unitCode && !(new UnitOfMeasure())->isKnown($unit))) {
                throw new WireException('Choose a known catalog unit.');
            }
            $item->unitCode = $unit;
        }
        if (array_key_exists('tax_code', $fields)) {
            $tax = $this->catalogText($fields['tax_code'], 30);
            if ($tax !== null && $tax !== $item->taxCode && !(new TaxCode())->isKnown($tax)) {
                throw new WireException('Choose a known catalog tax code.');
            }
            $item->taxCode = $tax;
        }
        if (array_key_exists('track_inventory', $fields)) {
            if (!is_bool($fields['track_inventory'])) {
                throw new WireException('track_inventory must be a boolean.');
            }
            $item->trackInventory = $fields['track_inventory'];
        }
        if (array_key_exists('status', $fields)) {
            $item->status = $this->catalogOption($fields['status'], ['active', 'inactive', 'discontinued'], 'status');
        }
        foreach (['sales_price' => 'salesPrice', 'purchase_price' => 'purchasePrice', 'cost_price' => 'costPrice'] as $key => $property) {
            if (array_key_exists($key, $fields)) {
                $item->{$property} = $this->catalogMoney($fields[$key]);
            }
        }
    }

    private function catalogFillCategory(KontorCatalog $catalog, Category $category, array $fields, Organization $organization, bool $lock): void
    {
        if (array_key_exists('name', $fields)) {
            $category->name = $this->catalogTranslations($category->name, $fields['name'], 255);
        }
        if (($category->name[$organization->defaultLanguage] ?? '') === '') {
            throw new WireException('A category name in the organization default language is required.');
        }
        if (array_key_exists('parent_uid', $fields)) {
            $parent = $fields['parent_uid'];
            $candidate = $parent;
            $seen = [$category->uid->toString() => true];
            while ($candidate !== null) {
                if (!is_string($candidate) || isset($seen[$candidate]) || count($seen) > 250) {
                    throw new WireException('The category parent would create a cycle or exceed the supported depth.');
                }
                $seen[$candidate] = true;
                $candidate = $this->catalogOwned($catalog, 'category', $candidate, $category->organizationId, $lock)->parentUid;
            }
            $category->parentUid = $parent;
        }
        if (array_key_exists('sort_order', $fields)) {
            $order = $fields['sort_order'];
            if (!is_int($order) || $order < -2147483648 || $order > 2147483647) {
                throw new WireException('Invalid category sort order.');
            }
            $category->sortOrder = $order;
        }
        if (array_key_exists('status', $fields)) {
            $category->status = $this->catalogOption($fields['status'], ['active', 'inactive'], 'status');
        }
    }

    private function catalogFillPriceList(KontorCatalog $catalog, PriceList $priceList, array $fields): void
    {
        if (array_key_exists('name', $fields)) {
            $priceList->name = $this->catalogText($fields['name'], 255) ?? '';
        }
        if ($priceList->name === '') {
            throw new WireException('A price list name is required.');
        }
        if (array_key_exists('currency', $fields)) {
            $currency = $this->catalogCurrency($fields['currency']);
            if ($currency !== $priceList->currencyCode && $catalog->priceRepository()->countsForPriceLists([$priceList->uid->toString()]) !== []) {
                throw new WireException('Remove existing price tiers before changing the currency.');
            }
            $priceList->currencyCode = $currency;
        }
        if (array_key_exists('status', $fields)) {
            $priceList->status = $this->catalogOption($fields['status'], ['active', 'inactive'], 'status');
        }
        foreach (['valid_from' => 'validFrom', 'valid_to' => 'validTo'] as $key => $property) {
            if (array_key_exists($key, $fields)) {
                $priceList->{$property} = $this->catalogDate($fields[$key]);
            }
        }
        $this->catalogDateRange($priceList->validFrom, $priceList->validTo);
    }

    private function catalogText(mixed $value, int $max, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > $max) {
            throw new WireException('Invalid catalog text field.');
        }
        $sanitizer = $this->wire()->sanitizer;
        $text = $multiline ? $sanitizer->textarea($value, ['maxLength' => $max]) : $sanitizer->text($value, ['maxLength' => $max]);
        return $text === '' ? null : $text;
    }

    private function catalogTranslations(array $current, mixed $fields, int $max): array
    {
        if (!is_array($fields) || array_is_list($fields) || count($fields) > 15) {
            throw new WireException('Translated catalog fields require a locale-to-text JSON object.');
        }
        foreach ($fields as $locale => $value) {
            if (!is_string($locale) || preg_match('/^[a-z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $locale) !== 1) {
                throw new WireException('Invalid catalog locale.');
            }
            $text = $this->catalogText($value, $max, $max > 255);
            if ($text === null) {
                unset($current[$locale]);
            } else {
                $current[$locale] = $text;
            }
        }
        return $current;
    }

    private function catalogOption(mixed $value, array $choices, string $field): string
    {
        if (!in_array($value, $choices, true)) {
            throw new WireException('Invalid catalog option: ' . $field);
        }
        return $value;
    }

    private function catalogCurrency(mixed $currency): string
    {
        if (!is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new WireException('Use a three-letter uppercase currency code.');
        }
        return $currency;
    }

    private function catalogMoney(mixed $value): ?Money
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value) || array_diff(array_keys($value), ['amount_minor', 'currency']) !== []
            || !is_int($value['amount_minor'] ?? null)) {
            throw new WireException('A catalog price requires integer amount_minor and currency.');
        }
        return Money::ofMinor($value['amount_minor'], $this->catalogCurrency($value['currency'] ?? null));
    }

    private function catalogDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new WireException('Use calendar dates in YYYY-MM-DD format.');
        }
        return $date;
    }

    private function catalogDateRange(?DateTimeImmutable $from, ?DateTimeImmutable $to): void
    {
        if ($from !== null && $to !== null && $from > $to) {
            throw new WireException('The end date must be on or after the start date.');
        }
    }

    private function catalogPresent(CatalogItem|Category|PriceList $record, User $actor, bool $detail = false): array
    {
        $result = ['uid' => $record->uid->toString(), 'status' => $record->status];
        if ($record instanceof CatalogItem) {
            $result += [
                'title' => $record->title,
                'item_type' => $record->itemType,
                'sku' => $record->sku,
                'barcode' => $record->barcode,
                'unit_code' => $record->unitCode,
                'tax_code' => $record->taxCode,
                'track_inventory' => $record->trackInventory,
                'sales_price' => $this->catalogPresentMoney($record->salesPrice),
            ];
            if ($this->catalogCan($actor, 'category', 'view')) {
                $result['category_uid'] = $record->categoryUid;
            }
            if ($detail) {
                $result['description'] = $record->description;
                if ($this->catalogCan($actor, 'item', 'edit')) {
                    $result['purchase_price'] = $this->catalogPresentMoney($record->purchasePrice);
                    $result['cost_price'] = $this->catalogPresentMoney($record->costPrice);
                }
            }
            return $result;
        }
        if ($record instanceof Category) {
            return $result + ['name' => $record->name, 'parent_uid' => $record->parentUid, 'sort_order' => $record->sortOrder];
        }
        return $result + [
            'name' => $record->name, 'currency' => $record->currencyCode,
            'valid_from' => $record->validFrom?->format('Y-m-d'), 'valid_to' => $record->validTo?->format('Y-m-d'),
        ];
    }

    private function catalogPresentMoney(?Money $money): ?array
    {
        return $money === null ? null : ['amount_minor' => $money->amountMinor(), 'currency' => $money->currencyCode()];
    }

    private function catalogPresentTier(PriceListEntry $tier): array
    {
        return [
            'price_list_uid' => $tier->priceListUid, 'item_uid' => $tier->itemUid,
            'quantity' => $tier->minQuantity, 'price' => $this->catalogPresentMoney($tier->price),
            'valid_from' => $tier->validFrom?->format('Y-m-d'), 'valid_to' => $tier->validTo?->format('Y-m-d'),
        ];
    }

    private function catalogWrite(User $actor, string $organization, string $key, array $arguments, bool $preview, callable $apply): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('A valid idempotency key is required.');
        }
        if ($preview) {
            return $apply();
        }
        $modules = $this->wire()->modules;
        $database = $modules->get('Kontor')->container()->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($database);
        try {
            $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
            $response = $idempotency->remember(
                $organization,
                'mcp:catalog:' . $actor->id . ':' . hash('sha256', $key),
                $arguments,
                fn (): array => ['status' => 200, 'body' => $apply()],
            );
            if ($ownsTransaction) {
                $database->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if ($error instanceof IdempotencyKeyConflictException) {
                throw new WireException($error->getMessage());
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            $this->wire()->log->save('kontor-mcp', 'Catalog write failed: ' . get_class($error));
            throw new WireException('The catalog change could not be saved. Retry with the same idempotency key.');
        }
    }

    private function catalogAudit(User $actor, string $organization, string $entity, string $uid, string $action, array $metadata = []): void
    {
        $container = $this->wire()->modules->get('Kontor')->container();
        $container->get(AuditLogger::class)->record(
            $container->get(OrganizationRepository::class)->internalIdOf($organization),
            'catalog', $entity, $uid, $action, 'user', (string) $actor->id,
            metadata: ['transport' => 'mcp'] + $metadata,
        );
    }
}
