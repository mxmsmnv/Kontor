<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationalPackagePublicApiContractTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function documentedMethodReferences(): iterable
    {
        yield 'inventory balance mutation' => ['inventory', \Kontor\Inventory\Infrastructure\Persistence\BalanceRepository::class, 'updateQuantities'];
        yield 'inventory queue integration' => ['inventory', \Kontor\Queue\Infrastructure\Persistence\JobRepository::class, 'enqueue'];
        yield 'purchasing goods receipt' => ['purchasing', \Kontor\Purchasing\Application\GoodsReceiptService::class, 'receive'];
        yield 'purchasing inventory integration' => ['purchasing', \Kontor\Inventory\Application\InventoryMovementService::class, 'receive'];
        yield 'projects invoice generation' => ['projects', \Kontor\Projects\Application\ProjectInvoicingService::class, 'generateInvoice'];
        yield 'projects time entry close' => ['projects', \Kontor\Projects\Domain\TimeEntry::class, 'close'];
        yield 'tasks calendar query' => ['tasks', \Kontor\Tasks\Infrastructure\Persistence\TaskRepository::class, 'dueBetween'];
        yield 'automation event handler' => ['automation', \Kontor\Automation\Application\AutomationEngine::class, 'handleEvent'];
        yield 'automation event dispatcher' => ['automation', \Kontor\Core\Infrastructure\Events\EventDispatcher::class, 'dispatch'];
        yield 'documents current template' => ['documents', \Kontor\Documents\Infrastructure\Persistence\TemplateRepository::class, 'findCurrentVersion'];
        yield 'ledger balance validation' => ['ledger', \Kontor\Ledger\Application\LedgerBalanceValidator::class, 'validate'];
        yield 'ledger reference lookup' => ['ledger', \Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository::class, 'findByReference'];
        yield 'ledger entry recording' => ['ledger', \Kontor\Ledger\Application\LedgerEntryService::class, 'record'];
    }

    #[DataProvider('documentedMethodReferences')]
    public function test_documented_method_references_resolve_to_public_implementations(
        string $package,
        string $class,
        string $method,
    ): void {
        $readme = file_get_contents($this->root() . "/packages/{$package}/README.md");
        self::assertIsString($readme);

        $shortClass = (new \ReflectionClass($class))->getShortName();
        self::assertStringContainsString(
            "`{$shortClass}::{$method}()`",
            $readme,
            "{$package}/README.md must retain the API reference protected by this contract.",
        );

        $reflection = new \ReflectionMethod($class, $method);
        self::assertTrue(
            $reflection->isPublic(),
            "Documented API {$class}::{$method}() must remain public.",
        );
    }

    public function test_automation_init_retains_its_documented_event_subscription(): void
    {
        $source = $this->moduleSource('automation', 'KontorAutomation');
        $init = $this->publicMethodBody($source, 'init');
        $subscription = $this->privateMethodBody($source, 'subscribeToTriggerEvents');

        self::assertStringContainsString('`KontorAutomation::init()`', $this->readme('automation'));
        self::assertStringContainsString('$this->subscribeToTriggerEvents(', $init);
        self::assertStringContainsString('$dispatcher->subscribe(', $subscription);
        self::assertStringContainsString('$this->engine()->handleEvent(', $subscription);
    }

    public function test_germany_init_retains_its_documented_localization_registration(): void
    {
        $source = $this->moduleSource('germany', 'KontorGermany');
        $init = $this->publicMethodBody($source, 'init');

        self::assertStringContainsString('`KontorGermany::init()`', $this->readme('germany'));
        self::assertStringContainsString("capability: 'localization.de'", $init);
        self::assertStringContainsString('contract: LocalizationProviderInterface::class', $init);
        self::assertStringContainsString('implementation: $this->localizationProvider()', $init);
    }

    private function readme(string $package): string
    {
        $readme = file_get_contents($this->root() . "/packages/{$package}/README.md");
        self::assertIsString($readme);

        return $readme;
    }

    private function moduleSource(string $package, string $module): string
    {
        $source = file_get_contents($this->root() . "/packages/{$package}/{$module}.module.php");
        self::assertIsString($source);

        return $source;
    }

    private function publicMethodBody(string $source, string $method): string
    {
        return $this->methodBody($source, $method, T_PUBLIC);
    }

    private function privateMethodBody(string $source, string $method): string
    {
        return $this->methodBody($source, $method, T_PRIVATE);
    }

    private function methodBody(string $source, string $method, int $visibility): string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $index + 1;
            while ($nameIndex < $count
                && (!is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING)) {
                $nameIndex++;
            }
            if ($nameIndex >= $count || $tokens[$nameIndex][1] !== $method) {
                continue;
            }

            $hasVisibility = false;
            for ($modifierIndex = $index - 1; $modifierIndex >= 0; $modifierIndex--) {
                $token = $tokens[$modifierIndex];
                if ($token === ';' || $token === '{' || $token === '}') {
                    break;
                }
                if (is_array($token) && $token[0] === $visibility) {
                    $hasVisibility = true;
                    break;
                }
            }
            self::assertTrue($hasVisibility, "{$method}() must retain its expected visibility.");

            $bodyIndex = $nameIndex + 1;
            while ($bodyIndex < $count && $tokens[$bodyIndex] !== '{') {
                $bodyIndex++;
            }

            $depth = 0;
            $body = '';
            for (; $bodyIndex < $count; $bodyIndex++) {
                $token = $tokens[$bodyIndex];
                $body .= is_array($token) ? $token[1] : $token;
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}' && --$depth === 0) {
                    return $body;
                }
            }
        }

        self::fail("Missing {$method}() method.");
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
