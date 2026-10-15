<?php
require_once __DIR__ . "/cors.php";


header("Content-Type: application/json");

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$username = trim($data["username"] ?? "");
$password = $data["password"] ?? "";

if ($username === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Username and password are required."
    ]);
    exit;
}

try {

    $sql = "SELECT employee_id, full_name, username, contact_number, role, password_hash
            FROM employees
            WHERE username = :username
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ":username" => $username
    ]);

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid username or password."
        ]);
        exit;
    }

    if (!password_verify($password, $employee["password_hash"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid username or password."
        ]);
        exit;
    }

    if (strtolower($employee["role"]) !== "admin") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Admin access required."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Admin login successful.",
        "employee" => [
            "employee_id" => $employee["employee_id"],
            "full_name" => $employee["full_name"],
            "username" => $employee["username"],
            "contact_number" => $employee["contact_number"],
            "role" => $employee["role"]
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);
}
?>