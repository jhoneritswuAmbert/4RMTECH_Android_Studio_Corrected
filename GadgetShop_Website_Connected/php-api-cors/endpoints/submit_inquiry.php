<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON request."
        ]);

        exit;
    }

    $customerId = $data["customer_id"] ?? null;
    $message = trim($data["message"] ?? "");

    // Validate customer ID
    if (
        $customerId === null
        || !is_numeric($customerId)
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Customer ID is required."
        ]);

        exit;
    }

    // Validate message
    if ($message === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Inquiry message is required."
        ]);

        exit;
    }

    if (strlen($message) > 1000) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Inquiry message must not exceed 1000 characters."
        ]);

        exit;
    }

    // Verify customer exists
    $check = $conn->prepare(
        "SELECT customer_id
         FROM customers
         WHERE customer_id = :customer_id"
    );

    $check->execute([
        ":customer_id" => (int) $customerId
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Customer not found."
        ]);

        exit;
    }

    // Insert inquiry
    $sql = "
        INSERT INTO inquiries (
            customer_id,
            message,
            date_submitted
        )
        VALUES (
            :customer_id,
            :message,
            CURRENT_TIMESTAMP
        )
        RETURNING inquiry_id
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":customer_id" => (int) $customerId,
        ":message" => $message
    ]);

    $inquiryId = $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "message" => "Inquiry submitted successfully.",
        "inquiry_id" => (int) $inquiryId
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to submit inquiry."
    ]);
}

?>