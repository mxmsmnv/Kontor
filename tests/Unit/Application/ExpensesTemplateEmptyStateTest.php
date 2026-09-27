<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class ExpensesTemplateEmptyStateTest extends TestCase
{
    public function test_empty_workspace_without_category_access_explains_the_blocker(): void
    {
        $html = $this->renderEmptyWorkspace(canViewCategories: false, canManageCategories: false);

        self::assertStringContainsString('No expenses available', $html);
        self::assertStringContainsString('category setup is restricted', $html);
        self::assertStringContainsString(
            'Ask an expense administrator to finish category setup or grant category access',
            $html,
        );
        self::assertStringNotContainsString('Record first expense', $html);
        self::assertStringNotContainsString('Ready to reimburse', $html);
    }

    public function test_empty_workspace_with_category_access_keeps_the_setup_guidance(): void
    {
        $html = $this->renderEmptyWorkspace(canViewCategories: true, canManageCategories: false);

        self::assertStringContainsString('Build a consistent approval flow', $html);
        self::assertStringContainsString('Ask an expense administrator to create the first category.', $html);
        self::assertStringNotContainsString('No expenses available', $html);
    }

    private function renderEmptyWorkspace(bool $canViewCategories, bool $canManageCategories): string
    {
        $template = __DIR__ . '/../../../templates/admin/expenses.php';
        $variables = [
            'expenses' => [],
            'allExpenses' => [],
            'categories' => [],
            'categoryLabels' => [],
            'selectedStatus' => null,
            'selectedCategory' => '',
            'query' => '',
            'canViewCategories' => $canViewCategories,
            'canCreateExpense' => false,
            'canManageCategories' => $canManageCategories,
            'adminUrl' => '/admin/kontor/',
            'e' => static fn (string $value): string => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ),
        ];

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        ob_start();

        try {
            (static function (string $template, array $variables): void {
                extract($variables, EXTR_SKIP);
                include $template;
            })($template, $variables);

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        } finally {
            restore_error_handler();
        }
    }
}
