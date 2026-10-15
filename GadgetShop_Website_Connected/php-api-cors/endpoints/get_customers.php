<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

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
            ORDER BY customer_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "customers" => $customers
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve customers."
    ]);
}

?>