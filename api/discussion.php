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

try {
    $pdo = get_db();
    $pasteStmt = $pdo->prepare('SELECT code, modes_discussion, kdfIterations FROM pastes WHERE code = :code');
    $pasteStmt->execute([':code' => $code]);
    $paste = $pasteStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    app_log('error', 'discussion_storage_read_failed', ['code' => $code, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'discussion_unavailable'], 404);
}

if (!is_array($paste) || ((bool) ($paste['modes_discussion'] ?? false) !== true)) {
    app_log('info', 'discussion_unavailable', ['code' => $code]);
    json_response(['ok' => false, 'error' => 'discussion_unavailable'], 404);
}

if ($method === 'GET') {
    $since = max(0, (int) ($_GET['since'] ?? 0));
    $stmt = $pdo->prepare('SELECT id, createdAt, message_ciphertext, message_iv FROM discussions WHERE paste_code = :code AND id > :since ORDER BY id ASC LIMIT 200');
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
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
$envelope = $input['envelope'] ?? null;
if (!is_array($envelope)) {
    json_response(['ok' => false, 'error' => 'missing_envelope'], 400);
}

$ciphertext = (string) ($envelope['ciphertext'] ?? '');
$iv = (string) ($envelope['iv'] ?? '');
if ($ciphertext === '' || $iv === '' || strlen($ciphertext) > MAX_MESSAGE_BYTES * 2 || strlen($iv) > 128) {
    json_response(['ok' => false, 'error' => 'invalid_message'], 400);
}

try {
    $insert = $pdo->prepare('INSERT INTO discussions (paste_code, message_ciphertext, message_iv, message_kdfIterations, createdAt) VALUES (:code, :ciphertext, :iv, :kdfIterations, :createdAt)');
    $insert->execute([
        ':code' => $code,
        ':ciphertext' => $ciphertext,
        ':iv' => $iv,
        ':kdfIterations' => (int) ($paste['kdfIterations'] ?? MIN_KDF_ITERATIONS),
        ':createdAt' => now(),
    ]);

    $newId = (int) $pdo->lastInsertId();

    $trim = $pdo->prepare('DELETE FROM discussions WHERE paste_code = :code AND id NOT IN (SELECT id FROM (SELECT id FROM discussions WHERE paste_code = :code2 ORDER BY id DESC LIMIT 200) AS recent_ids)');
    $trim->execute([
        ':code' => $code,
        ':code2' => $code,
    ]);
} catch (Throwable $e) {
    app_log('error', 'discussion_storage_write_failed', ['code' => $code, 'error' => $e->getMessage()]);
    json_response(['ok' => false, 'error' => 'storage_write_failed'], 500);
}

app_log('info', 'discussion_message_posted', ['code' => $code, 'id' => $newId]);

json_response(['ok' => true, 'id' => $newId]);
