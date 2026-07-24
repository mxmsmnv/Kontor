<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Manifest;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Domain\InvalidManifestException;

/**
 * Parses a kontor.json file or string into a ComponentManifest
 * (kontor.md#22).
 */
final class ManifestReader
{
    public function readFile(string $path): ComponentManifest
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidManifestException("Cannot read manifest at \"{$path}\".");
        }

        return $this->readJson((string) file_get_contents($path));
    }

    public function readJson(string $json): ComponentManifest
    {
        try {
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidManifestException("kontor.json is not valid JSON: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($data)) {
            throw new InvalidManifestException('kontor.json must decode to a JSON object.');
        }

        return ComponentManifest::fromArray($data);
    }
}
