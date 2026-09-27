<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class ProcessKontorPermissionContractTest extends TestCase
{
    public function test_crm_intake_settings_action_requires_intake_and_settings_permissions(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../ProcessKontor.module.php');

        self::assertIsString($controller);
        self::assertMatchesRegularExpression(
            "/'canManage'\s*=>\s*\\\$this->can\('kontor-crm-intake-admin'\)\s*"
            . "&&\s*\(\\\$this->can\('kontor-settings-export'\)\s*\|\|\s*"
            . "\\\$this->can\('kontor-settings-import'\)\)/s",
            $controller,
        );
    }

    public function test_task_relation_permission_is_required_before_a_linked_task_is_saved(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../ProcessKontor.module.php');

        self::assertIsString($controller);
        $methodStart = strpos($controller, 'public function ___executeTask(): string');
        $methodEnd = strpos($controller, 'public function ___executeTaskAction(): void', $methodStart ?: 0);
        self::assertNotFalse($methodStart);
        self::assertNotFalse($methodEnd);
        $method = substr($controller, $methodStart, $methodEnd - $methodStart);
        $relationGuard = strpos(
            $method,
            "if (\$task === null && \$contextType !== '' && \$contextUid !== '') {"
        );
        $permissionCheck = strpos(
            $method,
            "\$this->requirePermission('kontor-tasks-relation-manage');"
        );
        $save = strpos($method, '$repository->save($task);');
        $link = strpos($method, '$this->taskModule()->relations()->linkToEntity(');

        self::assertNotFalse($relationGuard);
        self::assertNotFalse($permissionCheck);
        self::assertNotFalse($save);
        self::assertNotFalse($link);
        self::assertLessThan($permissionCheck, $relationGuard);
        self::assertLessThan($save, $permissionCheck);
        self::assertLessThan($link, $save);
    }
}
