<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/reset_helpers.php';
$data = resetInput();
$email = $data['email'] ?? '';
if (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
    resetReply(422, ['success' => false, 'message' => 'Enter a valid email address.']);
}
$email = trim($email);
require_once __DIR__ . '/db.php';
try {
    $config = resetConfig();
    $requestId = bin2hex(random_bytes(32));
    $code = (string)random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $conn->beginTransaction();
    // Always rate-limit before looking up the account.
    $ipAllowed = resetLimit($conn, 'request-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10);
    $emailAllowed = resetLimit($conn, 'request-email:' . strtolower($email), 3);
    if (!$ipAllowed || !$emailAllowed) {
        $conn->commit();
        header('Retry-After: 900');
        resetReply(429, ['success' => false, 'message' => 'Too many reset requests. Please wait 15 minutes.']);
    }
    $stmt = $conn->prepare('SELECT customer_id, email FROM customers WHERE LOWER(email) = LOWER(:email) LIMIT 2');
    $stmt->execute([':email' => $email]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $customer = count($matches) === 1 ? $matches[0] : null;
    $customerId = $customer ? (int)$customer['customer_id'] : null;
    if ($customerId !== null) {
        $stmt = $conn->prepare('UPDATE api_password_resets SET used_at = CURRENT_TIMESTAMP, reset_token_hash = NULL WHERE customer_id = :id AND used_at IS NULL');
        $stmt->execute([':id' => $customerId]);
    }
    $stmt = $conn->prepare("INSERT INTO api_password_resets (request_id, customer_id, code_hash, expires_at) VALUES (:id, :customer, :hash, CURRENT_TIMESTAMP + INTERVAL '10 minutes')");
    $stmt->execute([':id' => $requestId, ':customer' => $customerId, ':hash' => $codeHash]);
    // Sending is asynchronous, so SMTP latency cannot disclose account existence.
    if ($customer) resetQueue($conn, $requestId, ['type' => 'reset', 'email' => $customer['email'], 'code' => $code], $config);
    $conn->commit();
    resetReply(200, ['success' => true, 'message' => 'If this email is registered, a reset code will arrive shortly. Check your inbox and spam folder.', 'request_id' => $requestId]);
} catch (Throwable $e) { resetUnavailable($conn); }
