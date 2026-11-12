<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$phpunit = $root . '/vendor/bin/phpunit';

if (!is_file($phpunit)) {
    fwrite(STDERR, "Run composer install in the Kontor repository first.\n");
    exit(2);
}

$dsn = getenv('KONTOR_TEST_DB_DSN') ?: '';
if (!str_starts_with($dsn, 'mysql:')
    || !preg_match('/(?:^mysql:|;)dbname=(kontor_test(?:_[a-z0-9]+)*)(?:;|$)/D', $dsn, $databaseName)
) {
    fwrite(STDERR, "Set KONTOR_TEST_DB_DSN to a disposable MySQL database named kontor_test or kontor_test_<suffix>.\n");
    exit(2);
}
if (!class_exists(Redis::class) || !getenv('KONTOR_TEST_REDIS_HOST')) {
    fwrite(STDERR, "Enable ext-redis and set KONTOR_TEST_REDIS_HOST for a disposable Redis instance.\n");
    exit(2);
}

try {
    $database = new PDO(
        $dsn,
        getenv('KONTOR_TEST_DB_USER') ?: null,
        getenv('KONTOR_TEST_DB_PASS') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    if ($database->query('SELECT DATABASE()')->fetchColumn() !== $databaseName[1]
        || $database->query('SHOW TABLES')->fetchColumn() !== false
    ) {
        fwrite(STDERR, "The selected MySQL database must be an empty test database.\n");
        exit(2);
    }
    $database = null;
} catch (PDOException) {
    fwrite(STDERR, "Could not connect to the configured MySQL test database.\n");
    exit(2);
}

$bootstrap = __DIR__ . '/package-bootstrap.php';
$configurations = [$root . '/phpunit.xml', ...(glob($root . '/packages/*/phpunit.xml') ?: [])];
$failed = [];
foreach ($configurations as $configuration) {
    $name = $configuration === $configurations[0] ? 'core' : basename(dirname($configuration));
    printf("\nKontor %s\n", $name);
    $process = proc_open(
        [PHP_BINARY, $phpunit, '-c', $configuration, '--bootstrap', $bootstrap, '--do-not-cache-result', '--fail-on-skipped'],
        [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $root,
    );
    if (!is_resource($process)) {
        $failed[] = $name;
        continue;
    }
    stream_copy_to_stream($pipes[1], STDOUT);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "\nSuites requiring attention: " . implode(', ', $failed) . "\n");
    exit(1);
}
printf("\nAll %d PHPUnit configurations passed.\n", count($configurations));
