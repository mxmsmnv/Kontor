<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\ApiRouter;
use PHPUnit\Framework\TestCase;

final class ApiRouterTest extends TestCase
{
    private ApiRouter $router;

    protected function setUp(): void
    {
        $this->router = new ApiRouter();
    }

    public function test_get_collection_is_list(): void
    {
        $match = $this->router->match('GET', '/api/kontor/v1/organizations');

        $this->assertNotNull($match);
        $this->assertSame('organizations', $match->resourceKey);
        $this->assertSame('list', $match->action);
        $this->assertNull($match->uid);
    }

    public function test_post_collection_is_create(): void
    {
        $match = $this->router->match('POST', 'api/kontor/v1/organizations');

        $this->assertSame('create', $match->action);
    }

    public function test_get_item_is_find(): void
    {
        $match = $this->router->match('GET', '/api/kontor/v1/organizations/org_01ABC');

        $this->assertSame('find', $match->action);
        $this->assertSame('org_01ABC', $match->uid);
    }

    public function test_patch_item_is_update(): void
    {
        $match = $this->router->match('PATCH', '/api/kontor/v1/organizations/org_01ABC');

        $this->assertSame('update', $match->action);
    }

    public function test_delete_item_is_delete(): void
    {
        $match = $this->router->match('DELETE', '/api/kontor/v1/organizations/org_01ABC');

        $this->assertSame('delete', $match->action);
    }

    public function test_post_to_an_item_path_does_not_match(): void
    {
        $this->assertNull($this->router->match('POST', '/api/kontor/v1/organizations/org_01ABC'));
    }

    public function test_delete_without_uid_does_not_match(): void
    {
        $this->assertNull($this->router->match('DELETE', '/api/kontor/v1/organizations'));
    }

    public function test_sub_action_routes_are_out_of_scope(): void
    {
        $this->assertNull($this->router->match('POST', '/api/kontor/v1/leads/lead_01/convert'));
    }

    public function test_paths_outside_the_api_prefix_do_not_match(): void
    {
        $this->assertNull($this->router->match('GET', '/kontor/organizations'));
    }

    public function test_query_string_is_ignored_when_matching(): void
    {
        $match = $this->router->match('GET', '/api/kontor/v1/organizations?page[number]=2');

        $this->assertNotNull($match);
        $this->assertSame('organizations', $match->resourceKey);
    }
}
