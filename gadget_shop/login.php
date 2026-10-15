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

header("Access-Control-Allow-Methods: POST, OPTIONS");
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

    // Get JSON request from Android
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$input) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid request."
        ]);

        exit;
    }

    // Get login information
    $email = trim($input["email"] ?? "");
    $password = $input["password"] ?? "";

    // Validate required fields
    if ($email === "" || $password === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Email and password are required."
        ]);

        exit;
    }

    // Find customer by email
    $sql = "SELECT
                customer_id,
                first_name,
                last_name,
                email,
                contact_number,
                street,
                barangay,
                city,
                postal_code,
                password_hash
            FROM customers
            WHERE email = :email";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":email" => $email
    ]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Customer does not exist
    if (!$customer) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);

        exit;
    }

    // Check password
    if (!password_verify(
        $password,
        $customer["password_hash"]
    )) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);

        exit;
    }

    // Create address from database fields
    $addressParts = [];

    if (!empty($customer["street"])) {
        $addressParts[] = $customer["street"];
    }

    if (!empty($customer["barangay"])) {
        $addressParts[] = $customer["barangay"];
    }

    if (!empty($customer["city"])) {
        $addressParts[] = $customer["city"];
    }

    if (!empty($customer["postal_code"])) {
        $addressParts[] = $customer["postal_code"];
    }

    $address = implode(", ", $addressParts);

    // Successful login
    $session = authIssue($conn, "customer", (int)$customer["customer_id"]);
    echo json_encode([
    "token" => $session["token"],
    "expires_at" => $session["expires_at"],
    "success" => true,
    "message" => "Login successful.",
    "customer_id" => (int) $customer["customer_id"],
    "first_name" => $customer["first_name"],
    "last_name" => $customer["last_name"],
    "email" => $customer["email"],
    "contact_number" => $customer["contact_number"] ?? "",
    "address" => $address,

    "street" => $customer["street"] ?? "",
    "barangay" => $customer["barangay"] ?? "",
    "city" => $customer["city"] ?? "",
    "postal_code" => $customer["postal_code"] ?? "",

    "joined_date" => ""
]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Login failed."
    ]);
}

?>