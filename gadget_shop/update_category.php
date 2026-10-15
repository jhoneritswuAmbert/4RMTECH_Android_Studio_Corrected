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

    // Check if JSON is valid
    if (!is_array($data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON request."
        ]);

        exit;
    }

    // Get values
    $categoryId = $data["category_id"] ?? null;
    $categoryName = trim($data["category_name"] ?? "");
    $description = trim($data["description"] ?? "");

    // Validate category ID
    if (!$categoryId || !is_numeric($categoryId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category ID is required."
        ]);

        exit;
    }

    // Validate category name
    if ($categoryName === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category name is required."
        ]);

        exit;
    }

    // Check category name length
    if (strlen($categoryName) > 100) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category name must not exceed 100 characters."
        ]);

        exit;
    }

    // Check if category exists
    $check = $conn->prepare(
        "SELECT category_id
         FROM categories
         WHERE category_id = :category_id"
    );

    $check->execute([
        ":category_id" => $categoryId
    ]);

    if (!$check->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Category not found."
        ]);

        exit;
    }

    // Update category
    $sql = "
        UPDATE categories
        SET
            category_name = :category_name,
            description = :description
        WHERE category_id = :category_id
        RETURNING category_id, category_name, description
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":category_id" => $categoryId,
        ":category_name" => $categoryName,
        ":description" => $description
    ]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Category updated successfully.",
        "category" => $category
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to update category."
    ]);
}
?>