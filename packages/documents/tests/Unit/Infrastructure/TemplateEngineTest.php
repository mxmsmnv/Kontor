<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Unit\Infrastructure;

use Kontor\Documents\Infrastructure\Rendering\TemplateEngine;
use PHPUnit\Framework\TestCase;

final class TemplateEngineTest extends TestCase
{
    public function test_substitutes_a_simple_placeholder(): void
    {
        $html = (new TemplateEngine())->render('<p>Hello {{name}}</p>', ['name' => 'Widget Corp']);

        $this->assertSame('<p>Hello Widget Corp</p>', $html);
    }

    public function test_substitutes_a_nested_dot_path(): void
    {
        $html = (new TemplateEngine())->render('<p>{{customer.name}}</p>', ['customer' => ['name' => 'Acme']]);

        $this->assertSame('<p>Acme</p>', $html);
    }

    public function test_html_escapes_substituted_values(): void
    {
        $html = (new TemplateEngine())->render('<p>{{name}}</p>', ['name' => '<script>alert(1)</script>']);

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }

    public function test_missing_placeholder_renders_as_empty_string(): void
    {
        $html = (new TemplateEngine())->render('<p>[{{missing}}]</p>', []);

        $this->assertSame('<p>[]</p>', $html);
    }

    public function test_each_repeats_a_section_per_item(): void
    {
        $template = '<ul>{{#each lines}}<li>{{title}} x{{quantity}}</li>{{/each}}</ul>';
        $data = ['lines' => [
            ['title' => 'Widget', 'quantity' => 2],
            ['title' => 'Gadget', 'quantity' => 1],
        ]];

        $html = (new TemplateEngine())->render($template, $data);

        $this->assertSame('<ul><li>Widget x2</li><li>Gadget x1</li></ul>', $html);
    }

    public function test_each_over_an_empty_list_renders_nothing(): void
    {
        $html = (new TemplateEngine())->render('<ul>{{#each lines}}<li>{{title}}</li>{{/each}}</ul>', ['lines' => []]);

        $this->assertSame('<ul></ul>', $html);
    }

    public function test_if_renders_the_section_when_truthy(): void
    {
        $html = (new TemplateEngine())->render('{{#if discount}}<p>Discount applied</p>{{/if}}', ['discount' => true]);

        $this->assertSame('<p>Discount applied</p>', $html);
    }

    public function test_if_omits_the_section_when_falsy_or_missing(): void
    {
        $engine = new TemplateEngine();

        $this->assertSame('', $engine->render('{{#if discount}}<p>Discount applied</p>{{/if}}', ['discount' => false]));
        $this->assertSame('', $engine->render('{{#if discount}}<p>Discount applied</p>{{/if}}', []));
    }
}
