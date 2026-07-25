<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Registry;

use Kontor\SDK\Contracts\SearchProviderInterface;

/**
 * Substage 2.4 "provider registry". Components register a
 * SearchProviderInterface per searchable entity type (kontor.md#9.8);
 * GlobalSearchService fans a query out to whichever providers match.
 */
final class SearchProviderRegistry
{
    /**
     * @var list<SearchProviderInterface>
     */
    private array $providers = [];

    public function register(SearchProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<SearchProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Providers whose supports() matches at least one of $entityTypes, or
     * every registered provider when $entityTypes is empty (search
     * everything).
     *
     * @param string[] $entityTypes
     * @return list<SearchProviderInterface>
     */
    public function forEntityTypes(array $entityTypes): array
    {
        if ($entityTypes === []) {
            return $this->providers;
        }

        return array_values(array_filter(
            $this->providers,
            static function (SearchProviderInterface $provider) use ($entityTypes): bool {
                foreach ($entityTypes as $entityType) {
                    if ($provider->supports($entityType)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }
}
