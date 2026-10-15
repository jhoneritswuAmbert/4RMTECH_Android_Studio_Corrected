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

 $authUser = authRequire($conn, "customer");


header("Content-Type: application/json");

try {

    // Get JSON request
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

    // Get values
    $customerId =
        (int) ($input["customer_id"] ?? 0);
    authOwn($authUser, $customerId);

    $firstName =
        trim($input["first_name"] ?? "");

    $lastName =
        trim($input["last_name"] ?? "");

    $contactNumber =
        trim($input["contact_number"] ?? "");

    $street =
        trim($input["street"] ?? "");

    $barangay =
        trim($input["barangay"] ?? "");

    $city =
        trim($input["city"] ?? "");

    $postalCode =
        trim($input["postal_code"] ?? "");

    // Validate customer ID
    if ($customerId <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid customer ID is required."
        ]);

        exit;
    }

    // Validate required name fields
    if ($firstName === "" || $lastName === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "First name and last name are required."
        ]);

        exit;
    }

    // Check customer exists
    $checkStmt = $conn->prepare(
        "SELECT customer_id
         FROM customers
         WHERE customer_id = :customer_id"
    );

    $checkStmt->execute([
        ":customer_id" => $customerId
    ]);

    if (!$checkStmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);

        exit;
    }

    // Update customer
    $updateStmt = $conn->prepare(
        "UPDATE customers
         SET
            first_name = :first_name,
            last_name = :last_name,
            contact_number = :contact_number,
            street = :street,
            barangay = :barangay,
            city = :city,
            postal_code = :postal_code
         WHERE customer_id = :customer_id"
    );

    $updateStmt->execute([
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":contact_number" => $contactNumber,
        ":street" => $street,
        ":barangay" => $barangay,
        ":city" => $city,
        ":postal_code" => $postalCode,
        ":customer_id" => $customerId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Customer profile updated successfully."
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to update customer profile."
    ]);
}

?>