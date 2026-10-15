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
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "GET") {
    header("Allow: GET, OPTIONS");
    authFail(405, "Only GET requests are allowed.");
}

 $authUser = authRequire($conn, "customer");


header("Content-Type: application/json");

try {

    $customerId = isset($_GET["id"])
        ? (int) $_GET["id"]
        : 0;
    authOwn($authUser, $customerId);

    if ($customerId <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid customer ID is required."
        ]);

        exit;
    }

    $sql = "SELECT
                customer_id,
                first_name,
                last_name,
                email,
                contact_number,
                street,
                barangay,
                city,
                postal_code
            FROM customers
            WHERE customer_id = :customer_id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":customer_id" => $customerId
    ]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);

        exit;
    }

    // Build the complete address for the existing profile display
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

    echo json_encode([
        "success" => true,

        "customer_id" => (int) $customer["customer_id"],

        "first_name" => $customer["first_name"],

        "last_name" => $customer["last_name"],

        "email" => $customer["email"],

        "contact_number" =>
            $customer["contact_number"] ?? "",

        "address" => $address,

        // Separate address fields
        "street" =>
            $customer["street"] ?? "",

        "barangay" =>
            $customer["barangay"] ?? "",

        "city" =>
            $customer["city"] ?? "",

        "postal_code" =>
            $customer["postal_code"] ?? "",

        "joined_date" => ""
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve customer."
    ]);
}

?>