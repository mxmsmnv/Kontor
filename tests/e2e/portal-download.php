<?php

declare(strict_types=1);

$site = realpath((string) getenv('KONTOR_E2E_SITE'));
$origin = rtrim((string) getenv('KONTOR_E2E_ORIGIN'), '/');
if (!$site || !is_file($site . '/index.php') || !preg_match('~^http://127\.0\.0\.1:[0-9]+$~D', $origin)) {
    throw new RuntimeException('Use a disposable ProcessWire site served on a loopback HTTP port.');
}

require $site . '/index.php';
if (!preg_match('/(?:^|_)test(?:_|$)|(?:^|_)http$/D', (string) $wire->config->dbName)) {
    throw new RuntimeException('Use a disposable test database.');
}
$portal = $wire->modules->get('KontorPortal');
$files = $wire->modules->get('KontorFiles');
$body = 'Private portal fixture ' . bin2hex(random_bytes(12));
$organization = $wire->modules->get('Kontor')->container()
    ->get(\Kontor\Core\Infrastructure\Persistence\OrganizationRepository::class)
    ->defaultOrganization('US', 'en', 'USD')->uid->toString();

$request = static function (string $path, int $status, string $expected) use ($origin): void {
    $curl = curl_init($origin . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($curl);
    if (!is_string($response) || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== $status) {
        throw new RuntimeException('Unexpected HTTP status for portal download.');
    }
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    if (substr($response, $headerSize) !== $expected
        || !str_contains(strtolower($headers), 'cache-control: private, no-store')
        || ($status === 200 && !str_contains(strtolower($headers), 'content-disposition: attachment'))
    ) {
        throw new RuntimeException('Unexpected portal download body or headers.');
    }
};

$wire->database->beginTransaction();
$storedPath = null;
try {
    $file = $files->fileManager()->upload($organization, 'portal-http-check.txt', $body);
    $storedPath = $files->fileRepository()->find($file['uid'])['path'];
    $download = $portal->files();
    $valid = $download->downloadUrl($file['uid'], new DateTimeImmutable('+5 minutes'));
    $expired = $download->downloadUrl($file['uid'], new DateTimeImmutable('-5 minutes'));
    $request($valid, 200, $body);
    $request($expired, 403, '');
    $request(explode('?', $valid, 2)[0], 403, '');
} finally {
    try {
        if ($storedPath !== null) {
            $files->storage()->delete($storedPath);
        }
    } finally {
        $wire->database->rollBack();
    }
}

echo "Portal signed download, expiration and unsigned rejection passed.\n";
