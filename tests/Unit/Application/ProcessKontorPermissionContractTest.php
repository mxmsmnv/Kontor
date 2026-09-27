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
}
