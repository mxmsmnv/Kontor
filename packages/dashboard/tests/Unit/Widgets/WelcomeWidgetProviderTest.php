<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Tests\Unit\Widgets;

use Kontor\Dashboard\Widgets\WelcomeWidgetProvider;
use PHPUnit\Framework\TestCase;

final class WelcomeWidgetProviderTest extends TestCase
{
    public function test_render_includes_the_organization_and_user(): void
    {
        $data = (new WelcomeWidgetProvider())->render('org_01', 42);

        $this->assertSame('org_01', $data['organizationUid']);
        $this->assertSame(42, $data['userId']);
        $this->assertArrayHasKey('generatedAt', $data);
    }

    public function test_key_and_title(): void
    {
        $widget = new WelcomeWidgetProvider();

        $this->assertSame('welcome', $widget->key());
        $this->assertSame('Welcome', $widget->title());
    }
}
