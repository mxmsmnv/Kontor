<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Recovery;

use Kontor\Core\Infrastructure\Recovery\RecoveryModeManager;
use PHPUnit\Framework\TestCase;

final class RecoveryModeManagerTest extends TestCase
{
    private string $stateDir;

    protected function setUp(): void
    {
        $this->stateDir = sys_get_temp_dir() . '/kontor-recovery-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->stateDir)) {
            array_map('unlink', glob($this->stateDir . '/*') ?: []);
            rmdir($this->stateDir);
        }
    }

    public function test_inactive_by_default(): void
    {
        $manager = new RecoveryModeManager($this->stateDir);

        $this->assertFalse($manager->isActive());
        $this->assertNull($manager->reason());
    }

    public function test_enable_then_disable(): void
    {
        $manager = new RecoveryModeManager($this->stateDir);

        $manager->enable('restoring core backup');
        $this->assertTrue($manager->isActive());
        $this->assertSame('restoring core backup', $manager->reason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $manager->enteredAt());

        $manager->disable();
        $this->assertFalse($manager->isActive());
        $this->assertNull($manager->reason());
    }

    public function test_disable_when_not_active_is_a_no_op(): void
    {
        $manager = new RecoveryModeManager($this->stateDir);

        $manager->disable();

        $this->assertFalse($manager->isActive());
    }

    public function test_a_second_manager_instance_sees_the_same_state(): void
    {
        (new RecoveryModeManager($this->stateDir))->enable('migration in progress');

        $this->assertTrue((new RecoveryModeManager($this->stateDir))->isActive());
    }
}
