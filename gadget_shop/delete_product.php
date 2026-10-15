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

header("Content-Type: application/json");

require_once "db.php";
require_once __DIR__ . "/auth.php";
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST, OPTIONS");
    authFail(405, "Only POST requests are allowed.");
}

 $authUser = authRequire($conn, "admin");


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

    $productId = $data["product_id"] ?? null;

    if (!$productId || !is_numeric($productId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Product ID is required."
        ]);

        exit;
    }

    // Check if product exists
    $checkProduct = $conn->prepare(
        "SELECT product_id, product_name
         FROM products
         WHERE product_id = :product_id"
    );

    $checkProduct->execute([
        ":product_id" => $productId
    ]);

    $product = $checkProduct->fetch(PDO::FETCH_ASSOC);

    if (!$product) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found."
        ]);

        exit;
    }

    // Check whether the product is used in inquiry_products
    $checkInquiry = $conn->prepare(
        "SELECT COUNT(*) AS inquiry_count
         FROM inquiry_products
         WHERE product_id = :product_id"
    );

    $checkInquiry->execute([
        ":product_id" => $productId
    ]);

    $inquiryCount = (int) $checkInquiry
        ->fetch(PDO::FETCH_ASSOC)["inquiry_count"];

    if ($inquiryCount > 0) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Cannot delete product because it is used in an inquiry.",
            "inquiry_count" => $inquiryCount
        ]);

        exit;
    }

    // Delete product
    $delete = $conn->prepare(
        "DELETE FROM products
         WHERE product_id = :product_id"
    );

    $delete->execute([
        ":product_id" => $productId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product deleted successfully.",
        "product_id" => (int) $productId,
        "product_name" => $product["product_name"]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to delete product."
    ]);
}

?>