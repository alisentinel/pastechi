<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    enforce_rate_limit('discussion_post');
} else {
    enforce_rate_limit('discussion_get');
}
random_delay();

$code = (string) ($_GET['code'] ?? ($_POST['code'] ?? ''));
if (!verify_code($code)) {
    app_log('warn', 'discussion_invalid_code');
    json_response(['ok' => false, 'error' => 'invalid_code'], 400);
}

$codeHash = code_hash($code);

try {
    $pdo = get_db();
    $pasteStmt = $pdo->prepare('SELECT modes_discussion, kdfIterations FROM pastes WHERE codeHash = :codeHash');
    $pasteStmt->execute([':codeHash' => $codeHash]);
    $paste = $pasteStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    app_log('error', 'discussion_storage_read_failed', ['codeHash' => $codeHash, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'discussion_unavailable'], 404);
}

if (!is_array($paste) || ((bool) ($paste['modes_discussion'] ?? false) !== true)) {
    app_log('info', 'discussion_unavailable', ['codeHash' => $codeHash]);
    json_response(['ok' => false, 'error' => 'discussion_unavailable'], 404);
}

if ($method === 'GET') {
    $since = max(0, (int) ($_GET['since'] ?? 0));
    $stmt = $pdo->prepare('SELECT id, createdAt, message_ciphertext, message_iv FROM discussions WHERE paste_codeHash = :codeHash AND id > :since ORDER BY id ASC LIMIT 200');
    $stmt->bindValue(':codeHash', $codeHash, PDO::PARAM_STR);
    $stmt->bindValue(':since', $since, PDO::PARAM_INT);
    $stmt->execute();

    $messages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $messages[] = [
            'id' => (int) ($row['id'] ?? 0),
            'ts' => (int) ($row['createdAt'] ?? 0),
            'ciphertext' => (string) ($row['message_ciphertext'] ?? ''),
            'iv' => (string) ($row['message_iv'] ?? ''),
        ];
    }

    json_response([
        'ok' => true,
        'messages' => $messages,
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$input = read_json_input();
$requestToken = (string) ($input['requestToken'] ?? '');
if (!verify_api_request_token($requestToken, 'discussion_post')) {
    app_log('warn', 'discussion_invalid_request_token');
    random_delay();
    json_response(['ok' => false, 'error' => 'invalid_request_token'], 403);
}

$envelope = $input['envelope'] ?? null;
if (!is_array($envelope)) {
    json_response(['ok' => false, 'error' => 'missing_envelope'], 400);
}

$ciphertext = (string) ($envelope['ciphertext'] ?? '');
$iv = (string) ($envelope['iv'] ?? '');
if ($ciphertext === '' || !is_base64url_string($ciphertext, MAX_MESSAGE_BYTES * 2) || !preg_match('/^[A-Za-z0-9\-_]{16}$/', $iv)) {
    json_response(['ok' => false, 'error' => 'invalid_message'], 400);
}

try {
    $insert = $pdo->prepare('INSERT INTO discussions (paste_codeHash, message_ciphertext, message_iv, message_kdfIterations, createdAt) VALUES (:codeHash, :ciphertext, :iv, :kdfIterations, :createdAt)');
    $insert->execute([
        ':codeHash' => $codeHash,
        ':ciphertext' => $ciphertext,
        ':iv' => $iv,
        ':kdfIterations' => (int) ($paste['kdfIterations'] ?? MIN_KDF_ITERATIONS),
        ':createdAt' => now(),
    ]);

    $newId = (int) $pdo->lastInsertId();

    $trim = $pdo->prepare('DELETE FROM discussions WHERE paste_codeHash = :codeHash AND id NOT IN (SELECT id FROM (SELECT id FROM discussions WHERE paste_codeHash = :codeHash2 ORDER BY id DESC LIMIT 200) AS recent_ids)');
    $trim->execute([
        ':codeHash' => $codeHash,
        ':codeHash2' => $codeHash,
    ]);
} catch (Throwable $e) {
    app_log('error', 'discussion_storage_write_failed', ['codeHash' => $codeHash, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'storage_write_failed'], 500);
}

app_log('info', 'discussion_message_posted', ['codeHash' => $codeHash, 'id' => $newId]);

json_response(['ok' => true, 'id' => $newId]);
