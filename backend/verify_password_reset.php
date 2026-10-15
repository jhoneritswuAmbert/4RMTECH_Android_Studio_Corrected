<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/reset_helpers.php';
$data = resetInput();
$id = $data['request_id'] ?? '';
$code = $data['code'] ?? '';
if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/', $id) || !is_string($code) || !preg_match('/^[0-9]{6}$/', $code)) {
    resetReply(422, ['success' => false, 'message' => 'Enter the six-digit code from your email.']);
}
require_once __DIR__ . '/db.php';
try {
    $conn->beginTransaction();
    if (!resetLimit($conn, 'verify-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30)) {
        $conn->commit();
        resetReply(429, ['success' => false, 'message' => 'Too many verification attempts. Please wait 15 minutes.']);
    }
    $stmt = $conn->prepare('SELECT *, (expires_at > CURRENT_TIMESTAMP) AS valid_time FROM api_password_resets WHERE request_id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $validTime = $row && in_array($row['valid_time'], [true, 1, '1', 't'], true);
    if (!$row || !$validTime || $row['used_at'] !== null || $row['verified_at'] !== null || (int)$row['attempts'] >= 5) {
        $conn->commit();
        resetReply(400, ['success' => false, 'message' => 'Code is invalid, expired, or already used. Request a new code.']);
    }
    $matches = password_verify($code, $row['code_hash']) && $row['customer_id'] !== null;
    if (!$matches) {
        $stmt = $conn->prepare('UPDATE api_password_resets SET attempts = attempts + 1 WHERE request_id = :id');
        $stmt->execute([':id' => $id]);
        $conn->commit();
        resetReply(400, ['success' => false, 'message' => 'Code is invalid, expired, or already used. Request a new code if needed.']);
    }
    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("UPDATE api_password_resets SET verified_at = CURRENT_TIMESTAMP, reset_token_hash = :hash, expires_at = CURRENT_TIMESTAMP + INTERVAL '10 minutes' WHERE request_id = :id");
    $stmt->execute([':hash' => hash('sha256', $token), ':id' => $id]);
    $conn->commit();
    resetReply(200, ['success' => true, 'message' => 'Code verified. Choose a new password.', 'reset_token' => $token]);
} catch (Throwable $e) { resetUnavailable($conn); }
