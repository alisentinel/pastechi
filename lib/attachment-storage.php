<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Encrypted attachment blobs live outside the database: in S3-compatible storage
// (e.g. ArvanCloud Object Storage) when configured, otherwise under storage/attachments.
// Paste text ciphertext always stays in the database.

define('S3_ENDPOINT', rtrim(env_string('S3_ENDPOINT', ''), '/'));
define('S3_BUCKET', env_string('S3_BUCKET', ''));
define('S3_ACCESS_KEY', env_string('S3_ACCESS_KEY', ''));
define('S3_SECRET_KEY', env_string('S3_SECRET_KEY', ''));
define('S3_REGION', env_string('S3_REGION', 'us-east-1'));

function s3_enabled(): bool
{
    return S3_ENDPOINT !== '' && S3_BUCKET !== '' && S3_ACCESS_KEY !== '' && S3_SECRET_KEY !== '';
}

/**
 * @return array{0:int,1:string} HTTP status and response body
 */
function s3_request(string $method, string $key, string $body = ''): array
{
    $host = (string) parse_url(S3_ENDPOINT, PHP_URL_HOST);
    $port = parse_url(S3_ENDPOINT, PHP_URL_PORT);
    if ($port !== null) {
        $host .= ':' . $port;
    }
    $path = '/' . rawurlencode(S3_BUCKET) . '/' . str_replace('%2F', '/', rawurlencode($key));
    $amzDate = gmdate('Ymd\THis\Z');
    $date = substr($amzDate, 0, 8);
    $payloadHash = hash('sha256', $body);

    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    $signedHeaders = implode(';', array_keys($headers));
    $canonicalHeaders = '';
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= $name . ':' . $value . "\n";
    }

    $canonicalRequest = $method . "\n" . $path . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    $scope = $date . '/' . S3_REGION . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);

    $signingKey = hash_hmac('sha256', $date, 'AWS4' . S3_SECRET_KEY, true);
    $signingKey = hash_hmac('sha256', S3_REGION, $signingKey, true);
    $signingKey = hash_hmac('sha256', 's3', $signingKey, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $signingKey, true);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $curlHeaders = [
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
        'Authorization: AWS4-HMAC-SHA256 Credential=' . S3_ACCESS_KEY . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
    ];

    $ch = curl_init(S3_ENDPOINT . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($response === false) {
        throw new RuntimeException('s3_request_failed: ' . $error);
    }

    return [$status, (string) $response];
}

const ATTACHMENTS_DIR = STORAGE_ROOT . '/attachments';

function attachment_store(string $codeHash, string $data): void
{
    if (s3_enabled()) {
        [$status] = s3_request('PUT', 'attachments/' . $codeHash, $data);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('s3_put_failed: HTTP ' . $status);
        }
        return;
    }

    if (!is_dir(ATTACHMENTS_DIR) && !mkdir(ATTACHMENTS_DIR, 0700, true) && !is_dir(ATTACHMENTS_DIR)) {
        throw new RuntimeException('attachment_dir_unavailable');
    }
    if (file_put_contents(ATTACHMENTS_DIR . '/' . $codeHash, $data, LOCK_EX) === false) {
        throw new RuntimeException('attachment_write_failed');
    }
}

function attachment_load(string $codeHash): string
{
    if (s3_enabled()) {
        [$status, $body] = s3_request('GET', 'attachments/' . $codeHash);
        if ($status !== 200) {
            throw new RuntimeException('s3_get_failed: HTTP ' . $status);
        }
        return $body;
    }

    $data = @file_get_contents(ATTACHMENTS_DIR . '/' . $codeHash);
    if ($data === false) {
        throw new RuntimeException('attachment_read_failed');
    }
    return $data;
}

function attachment_delete(string $codeHash): void
{
    try {
        if (s3_enabled()) {
            s3_request('DELETE', 'attachments/' . $codeHash);
            return;
        }
        @unlink(ATTACHMENTS_DIR . '/' . $codeHash);
    } catch (Throwable $e) {
        app_log('warn', 'attachment_delete_failed', ['message' => $e->getMessage()]);
    }
}

// Round-trip check against the configured storage: php lib/attachment-storage.php
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/utils.php';
    $probe = 'selfcheck-' . bin2hex(random_bytes(8));
    attachment_store($probe, 'hello');
    if (attachment_load($probe) !== 'hello') {
        fwrite(STDERR, "load mismatch\n");
        exit(1);
    }
    attachment_delete($probe);
    echo (s3_enabled() ? 's3' : 'local') . " ok\n";
}
