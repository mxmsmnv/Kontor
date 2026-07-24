<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Manifest;

use Kontor\Core\Domain\InvalidManifestException;
use Kontor\Core\Infrastructure\Manifest\ManifestReader;
use PHPUnit\Framework\TestCase;

final class ManifestReaderTest extends TestCase
{
    private function validManifestJson(): string
    {
        return json_encode([
            'name' => 'KontorCRM',
            'version' => '1.0.0',
            'package' => 'kontor/crm',
            'namespace' => 'Kontor\\CRM',
            'requires' => ['php' => '>=8.2', 'kontor/core' => '^0.1'],
            'permissions' => ['kontor-crm-lead-view'],
            'storage' => ['tables' => ['kontor_crm_leads'], 'directories' => []],
        ], JSON_THROW_ON_ERROR);
    }

    public function test_reads_a_valid_manifest(): void
    {
        $manifest = (new ManifestReader())->readJson($this->validManifestJson());

        $this->assertSame('KontorCRM', $manifest->name);
        $this->assertSame('kontor/crm', $manifest->package);
        $this->assertSame(['php' => '>=8.2', 'kontor/core' => '^0.1'], $manifest->requires);
        $this->assertSame(['kontor_crm_leads'], $manifest->storageTables);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->expectException(InvalidManifestException::class);

        (new ManifestReader())->readJson('{not valid json');
    }

    public function test_rejects_manifest_missing_a_required_field(): void
    {
        $data = json_decode($this->validManifestJson(), true);
        unset($data['requires']);

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('requires');

        (new ManifestReader())->readJson(json_encode($data));
    }

    public function test_read_file_throws_for_missing_file(): void
    {
        $this->expectException(InvalidManifestException::class);

        (new ManifestReader())->readFile('/nonexistent/kontor.json');
    }
}
