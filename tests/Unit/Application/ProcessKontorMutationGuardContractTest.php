<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProcessKontorMutationGuardContractTest extends TestCase
{
    /** @var string[] */
    private const CONDITIONAL_MUTATION_HANDLERS = [
        'executeContact',
        'executeCompany',
    ];

    /**
     * These handlers only render, query, or stream a read-only export/download.
     * Audit records written while serving a read are observability, not the
     * business mutation initiated by the endpoint.
     *
     * @var string[]
     */
    private const READ_ONLY_HANDLERS = [
        'execute',
        'executeActivity',
        'executeActivityExport',
        'executeAI',
        'executeApi',
        'executeAutomations',
        'executeBackups',
        'executeCache',
        'executeCatalog',
        'executeCatalogCategories',
        'executeCatalogPriceLists',
        'executeCatalogReferences',
        'executeCollaboration',
        'executeCompanies',
        'executeComponents',
        'executeContacts',
        'executeCrm',
        'executeCrmDeals',
        'executeCrmIntake',
        'executeCustomEntities',
        'executeDemo',
        'executeDocuments',
        'executeExpenses',
        'executeFiles',
        'executeFilesDownload',
        'executeGermany',
        'executeGraphql',
        'executeHealth',
        'executeInventory',
        'executeInvoice',
        'executeInvoices',
        'executeLedger',
        'executeMail',
        'executeMarketplace',
        'executePayment',
        'executePayments',
        'executePortal',
        'executeProjects',
        'executePurchasing',
        'executeQueue',
        'executeReports',
        'executeSales',
        'executeSalesOrder',
        'executeSearch',
        'executeSections',
        'executeSettingsExport',
        'executeSettingsMigration',
        'executeTasks',
        'executeWorkflows',
        'executeExport',
    ];

    public function test_read_only_allowlist_matches_real_execute_handlers(): void
    {
        $handlers = self::executeHandlers();
        $missing = array_values(array_diff(self::READ_ONLY_HANDLERS, array_keys($handlers)));
        $mutations = array_diff_key($handlers, array_flip(self::READ_ONLY_HANDLERS));

        self::assertCount(168, $handlers);
        self::assertSame([], $missing, 'Unknown read-only execute handlers: ' . implode(', ', $missing));
        self::assertCount(118, $mutations);
    }

    #[DataProvider('conditionalMutationHandlerProvider')]
    public function test_conditional_form_mutations_guard_the_submit_branch_before_processing(
        string $handler,
        string $body,
    ): void {
        $submitBranch = strpos($body, "if (\$this->wire()->input->post('submit_save')) {");
        $postGuard = strpos($body, '$this->requirePost();');
        $processInput = strpos($body, '$form->processInput($this->wire()->input->post);');

        self::assertNotFalse($submitBranch, sprintf('%s must have an explicit submit branch.', $handler));
        self::assertNotFalse($postGuard, sprintf('%s must validate POST and CSRF.', $handler));
        self::assertNotFalse($processInput, sprintf('%s must process its form after validation.', $handler));
        self::assertLessThan($postGuard, $submitBranch);
        self::assertLessThan($processInput, $postGuard);
    }

    #[DataProvider('mutationHandlerProvider')]
    public function test_every_mutation_handler_requires_post_and_csrf(string $handler, string $body): void
    {
        self::assertStringContainsString(
            '$this->requirePost();',
            $body,
            sprintf('%s must enforce POST and ProcessWire CSRF validation before mutating state.', $handler),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mutationHandlerProvider(): iterable
    {
        $handlers = self::executeHandlers();
        $mutations = array_diff_key($handlers, array_flip(self::READ_ONLY_HANDLERS));

        foreach ($mutations as $handler => $body) {
            yield $handler => [$handler, $body];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function conditionalMutationHandlerProvider(): iterable
    {
        $handlers = self::executeHandlers();

        foreach (self::CONDITIONAL_MUTATION_HANDLERS as $handler) {
            yield $handler => [$handler, $handlers[$handler]];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function executeHandlers(): array
    {
        $source = file_get_contents(__DIR__ . '/../../../ProcessKontor.module.php');
        self::assertIsString($source);
        $tokens = token_get_all($source);
        $handlers = [];
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $index + 1;
            while ($nameIndex < $tokenCount
                && (!is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING)) {
                $nameIndex++;
            }
            if ($nameIndex >= $tokenCount) {
                continue;
            }

            $methodName = $tokens[$nameIndex][1];
            if (!str_starts_with($methodName, '___execute')) {
                continue;
            }

            $bodyIndex = $nameIndex + 1;
            while ($bodyIndex < $tokenCount && $tokens[$bodyIndex] !== '{') {
                $bodyIndex++;
            }
            self::assertLessThan($tokenCount, $bodyIndex, sprintf('%s has no method body.', $methodName));

            $depth = 0;
            $body = '';
            for (; $bodyIndex < $tokenCount; $bodyIndex++) {
                $token = $tokens[$bodyIndex];
                $text = is_array($token) ? $token[1] : $token;
                $body .= $text;
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }

            $handlers[substr($methodName, 3)] = $body;
            $index = $bodyIndex;
        }

        ksort($handlers);

        return $handlers;
    }
}
