<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Support;

use Kontor\Core\Support\VersionConstraint;
use PHPUnit\Framework\TestCase;

final class VersionConstraintTest extends TestCase
{
    public function test_caret_constraint_matches_same_major(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.4.2', '^1.0'));
        $this->assertTrue(VersionConstraint::satisfies('1.0.0', '^1.0'));
    }

    public function test_caret_constraint_rejects_different_major(): void
    {
        $this->assertFalse(VersionConstraint::satisfies('2.0.0', '^1.0'));
    }

    public function test_caret_constraint_rejects_lower_minor(): void
    {
        $this->assertFalse(VersionConstraint::satisfies('1.0.0', '^1.2'));
    }

    public function test_comparison_operators(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('8.3.0', '>=8.2'));
        $this->assertFalse(VersionConstraint::satisfies('8.1.0', '>=8.2'));
        $this->assertTrue(VersionConstraint::satisfies('3.0.240', '>=3.0.240'));
    }

    public function test_exact_match(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.0.0', '1.0.0'));
        $this->assertFalse(VersionConstraint::satisfies('1.0.1', '1.0.0'));
    }
}
