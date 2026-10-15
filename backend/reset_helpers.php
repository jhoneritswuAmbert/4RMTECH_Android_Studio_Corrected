<?php
function resetReply(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}
function resetInput(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST, OPTIONS');
        resetReply(405, ['success' => false, 'message' => 'Only POST requests are allowed.']);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) resetReply(400, ['success' => false, 'message' => 'Invalid JSON request.']);
    return $data;
}
function resetConfig(): array {
    $path = getenv('GADGETSHOP_MAIL_CONFIG') ?: dirname(__DIR__, 2) . '/gadget_shop_private/mail_config.php';
    if (!is_file($path)) throw new RuntimeException('Mail configuration is missing.');
    $config = require $path;
    if (!is_array($config) || !preg_match('/^[a-f0-9]{64}$/i', $config['encryption_key'] ?? '') ||
        !filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL) || empty($config['host']) ||
        empty($config['username']) || empty($config['password']) ||
        str_contains($config['password'], 'YOUR_') ||
        !in_array($config['security'] ?? '', ['tls', 'ssl'], true)) {
        throw new RuntimeException('Mail configuration is incomplete.');
    }
    return $config;
}
function resetSeal(array $payload, array $config): string {
    $iv = random_bytes(12);
    $tag = '';
    $text = json_encode($payload, JSON_THROW_ON_ERROR);
    $cipher = openssl_encrypt($text, 'aes-256-gcm', hex2bin($config['encryption_key']), OPENSSL_RAW_DATA, $iv, $tag, 'gadget-shop-reset', 16);
    if ($cipher === false) throw new RuntimeException('Mail payload encryption failed.');
    return base64_encode($iv . $tag . $cipher);
}
function resetOpen(string $sealed, array $config): array {
    $bytes = base64_decode($sealed, true);
    if ($bytes === false || strlen($bytes) < 29) throw new RuntimeException('Invalid mail payload.');
    $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', hex2bin($config['encryption_key']), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'gadget-shop-reset');
    if ($plain === false) throw new RuntimeException('Invalid mail payload.');
    return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
}
function resetLimit(PDO $conn, string $key, int $max): bool {
    $sql = "INSERT INTO api_reset_limits (limit_key, hits) VALUES (:key, 1)
            ON CONFLICT (limit_key) DO UPDATE SET
            hits = CASE WHEN api_reset_limits.window_start < CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN 1 ELSE api_reset_limits.hits + 1 END,
            window_start = CASE WHEN api_reset_limits.window_start < CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN CURRENT_TIMESTAMP ELSE api_reset_limits.window_start END
            RETURNING hits";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':key' => hash('sha256', $key)]);
    return (int)$stmt->fetchColumn() <= $max;
}
function resetQueue(PDO $conn, ?string $requestId, array $payload, array $config): void {
    $stmt = $conn->prepare('INSERT INTO api_mail_outbox (request_id, payload) VALUES (:id, :payload)');
    $stmt->execute([':id' => $requestId, ':payload' => resetSeal($payload, $config)]);
}
function resetUnavailable(PDO $conn): void {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Gadget Shop: password recovery failed; check migration and private mail configuration.');
    resetReply(503, ['success' => false, 'message' => 'Password recovery is temporarily unavailable. Please try again later.']);
}
