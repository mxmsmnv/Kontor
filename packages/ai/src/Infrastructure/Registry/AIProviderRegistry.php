<?php

declare(strict_types=1);

namespace Kontor\AI\Infrastructure\Registry;

use Kontor\SDK\Contracts\KontorAIProviderInterface;

/**
 * The "provider contract" milestone's registry: `KontorAIProviderInterface`
 * itself already lives in the SDK (kontor.md#9.12) — this is the
 * extension-point registry business code resolves a capability through,
 * the same shape every other registry in this monorepo uses. Providers
 * are tried in registration order; the first one that `supports()` a
 * capability wins.
 */
final class AIProviderRegistry
{
    /**
     * @var KontorAIProviderInterface[]
     */
    private array $providers = [];

    public function register(KontorAIProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function find(string $capability): ?KontorAIProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($capability)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @return KontorAIProviderInterface[]
     */
    public function all(): array
    {
        return $this->providers;
    }
}
