<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/reset_helpers.php';
$data = resetInput();
$token = $data['reset_token'] ?? '';
$password = $data['password'] ?? '';
$confirmation = $data['confirm_password'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) resetReply(422, ['success' => false, 'message' => 'Verify a new reset code first.']);
if (!is_string($password) || !is_string($confirmation) || !preg_match('/\A.{6,}\z/us', $password) || strlen($password) > 72 || $password !== $confirmation) {
    resetReply(422, ['success' => false, 'message' => 'Passwords must match, contain at least 6 characters, and use at most 72 bytes.']);
}
require_once __DIR__ . '/db.php';
try {
    $config = resetConfig();
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $conn->beginTransaction();
    if (!resetLimit($conn, 'finish-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30)) {
        $conn->commit();
        resetReply(429, ['success' => false, 'message' => 'Too many reset attempts. Please wait 15 minutes.']);
    }
    $stmt = $conn->prepare('SELECT *, (expires_at > CURRENT_TIMESTAMP) AS valid_time FROM api_password_resets WHERE reset_token_hash = :hash FOR UPDATE');
    $stmt->execute([':hash' => hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array($row['valid_time'], [true, 1, '1', 't'], true) || $row['used_at'] !== null || $row['verified_at'] === null || !$row['customer_id']) {
        $conn->commit();
        resetReply(400, ['success' => false, 'message' => 'Reset session is invalid or expired. Request a new code.']);
    }
    $stmt = $conn->prepare('UPDATE customers SET password_hash = :hash WHERE customer_id = :id RETURNING email');
    $stmt->execute([':hash' => $newHash, ':id' => $row['customer_id']]);
    $email = $stmt->fetchColumn();
    if (!$email) throw new RuntimeException('Customer no longer exists.');
    $stmt = $conn->prepare('UPDATE api_password_resets SET used_at = CURRENT_TIMESTAMP, reset_token_hash = NULL WHERE customer_id = :id AND used_at IS NULL');
    $stmt->execute([':id' => $row['customer_id']]);
    $stmt = $conn->prepare('DELETE FROM api_auth_tokens WHERE customer_id = :id');
    $stmt->execute([':id' => $row['customer_id']]);
    resetQueue($conn, null, ['type' => 'changed', 'email' => $email], $config);
    $conn->commit();
    resetReply(200, ['success' => true, 'message' => 'Password changed. Sign in with your new password. Other sessions have been signed out.']);
} catch (Throwable $e) { resetUnavailable($conn); }
