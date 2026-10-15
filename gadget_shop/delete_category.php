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

    // Get JSON request
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    // Check JSON
    if (!is_array($data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON request."
        ]);

        exit;
    }

    // Get category ID
    $categoryId = $data["category_id"] ?? null;

    // Validate category ID
    if (!$categoryId || !is_numeric($categoryId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category ID is required."
        ]);

        exit;
    }

    // Check if category exists
    $checkCategory = $conn->prepare(
        "SELECT category_id, category_name
         FROM categories
         WHERE category_id = :category_id"
    );

    $checkCategory->execute([
        ":category_id" => $categoryId
    ]);

    $category = $checkCategory->fetch(PDO::FETCH_ASSOC);

    if (!$category) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Category not found."
        ]);

        exit;
    }

    // Check whether products are using this category
    $checkProducts = $conn->prepare(
        "SELECT COUNT(*) AS product_count
         FROM products
         WHERE category_id = :category_id"
    );

    $checkProducts->execute([
        ":category_id" => $categoryId
    ]);

    $productCount = (int) $checkProducts
        ->fetch(PDO::FETCH_ASSOC)["product_count"];

    // Do not delete categories that have products
    if ($productCount > 0) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Cannot delete category because it has products.",
            "product_count" => $productCount
        ]);

        exit;
    }

    // Delete category
    $delete = $conn->prepare(
        "DELETE FROM categories
         WHERE category_id = :category_id"
    );

    $delete->execute([
        ":category_id" => $categoryId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Category deleted successfully.",
        "category_id" => (int) $categoryId,
        "category_name" => $category["category_name"]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to delete category."
    ]);
}

?>