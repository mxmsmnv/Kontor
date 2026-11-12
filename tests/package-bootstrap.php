<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$loader = require $root . '/vendor/autoload.php';
foreach (glob($root . '/packages/*/composer.json') ?: [] as $manifest) {
    $package = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
    foreach ($package['autoload-dev']['psr-4'] ?? [] as $namespace => $directory) {
        $loader->addPsr4($namespace, dirname($manifest) . '/' . $directory);
    }
}
