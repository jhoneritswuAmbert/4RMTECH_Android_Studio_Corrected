<?php

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://localhost:5173",
    "http://127.0.0.1:5173"
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once "db.php";
require_once __DIR__ . "/auth.php";
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST, OPTIONS");
    authFail(405, "Only POST requests are allowed.");
}



header("Content-Type: application/json");

try {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid request."
        ]);

        exit;
    }

    $firstName = trim($input["first_name"] ?? "");
    $lastName = trim($input["last_name"] ?? "");
    $email = trim($input["email"] ?? "");
    $password = $input["password"] ?? "";

    // Validate required fields
    if ($firstName === "" ||
        $lastName === "" ||
        $email === "" ||
        $password === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "All required fields must be provided."
        ]);

        exit;
    }

    // Basic email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email address."
        ]);

        exit;
    }

    // Check if email already exists
    $checkSql = "SELECT customer_id
                 FROM customers
                 WHERE email = :email";

    $checkStmt = $conn->prepare($checkSql);

    $checkStmt->execute([
        ":email" => $email
    ]);

    if ($checkStmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Email is already registered."
        ]);

        exit;
    }

    // Hash password securely
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Insert customer
    $sql = "INSERT INTO customers
            (
                first_name,
                last_name,
                email,
                password_hash
            )
            VALUES
            (
                :first_name,
                :last_name,
                :email,
                :password_hash
            )
            RETURNING customer_id";

    $stmt = $conn->prepare($sql);

    $conn->beginTransaction();
    $stmt->execute([
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":email" => $email,
        ":password_hash" => $passwordHash
    ]);

    $customerId = $stmt->fetchColumn();

    $session = authIssue($conn, "customer", (int)$customerId);
    $conn->commit();
    echo json_encode([
    "token" => $session["token"],
    "expires_at" => $session["expires_at"],
    "success" => true,
    "message" => "Registration successful.",
    "customer_id" => $customerId,
    "first_name" => $firstName,
    "last_name" => $lastName
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Registration failed."
    ]);
}

?>