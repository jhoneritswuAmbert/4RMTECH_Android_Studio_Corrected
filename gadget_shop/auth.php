<?php
function authFail(int $code, string $message): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
function authIssue(PDO $conn, string $kind, int $id): array {
    $token = bin2hex(random_bytes(32));
    $customer = $kind === 'customer' ? $id : null;
    $employee = $kind === 'admin' ? $id : null;
    $stmt = $conn->prepare("INSERT INTO api_auth_tokens (token_hash, customer_id, employee_id, expires_at) VALUES (:hash, :customer, :employee, CURRENT_TIMESTAMP + INTERVAL '12 hours') RETURNING expires_at");
    $stmt->execute([':hash' => hash('sha256', $token), ':customer' => $customer, ':employee' => $employee]);
    return ['token' => $token, 'expires_at' => $stmt->fetchColumn()];
}
function authRequire(PDO $conn, string $kind): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === 'authorization') $header = $value;
        }
    }
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', $header, $match)) authFail(401, 'Please sign in again.');
    try {
        $stmt = $conn->prepare("SELECT t.token_hash, t.customer_id, t.employee_id, c.customer_id AS active_customer, e.employee_id AS active_employee, e.role FROM api_auth_tokens t LEFT JOIN customers c ON c.customer_id = t.customer_id LEFT JOIN employees e ON e.employee_id = t.employee_id WHERE t.token_hash = :hash AND t.expires_at > CURRENT_TIMESTAMP");
        $stmt->execute([':hash' => hash('sha256', $match[1])]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || (!$user['active_customer'] && !$user['active_employee'])) authFail(401, 'Your session expired. Please sign in again.');
        if ($kind === 'admin' && (!$user['active_employee'] || strtolower($user['role'] ?? '') !== 'admin')) authFail(403, 'Admin access required.');
        if ($kind === 'customer' && !$user['active_customer']) authFail(403, 'Customer access required.');
        return $user;
    } catch (PDOException $e) { authFail(503, 'Authentication is unavailable. Check the database migration.'); }
}
function authOwn(array $user, $id): void {
    $parsed = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($parsed === false || $parsed !== (int)$user['customer_id']) authFail(403, 'You can only access your own customer records.');
}
