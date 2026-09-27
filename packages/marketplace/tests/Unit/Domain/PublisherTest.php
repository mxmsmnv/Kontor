<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Unit\Domain;

use Kontor\Marketplace\Domain\Publisher;
use PHPUnit\Framework\TestCase;

final class PublisherTest extends TestCase
{
    public function test_create_defaults_to_unverified(): void
    {
        $publisher = Publisher::create('Acme Inc', 'https://acme.example');

        $this->assertFalse($publisher->verified);
    }

    public function test_verify(): void
    {
        $publisher = Publisher::create('Acme Inc', null);

        $publisher->verify();

        $this->assertTrue($publisher->verified);
    }
}
