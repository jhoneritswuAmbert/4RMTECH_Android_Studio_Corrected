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

    $categoryName = trim(
        $data["category_name"] ?? ""
    );

    $description = trim(
        $data["description"] ?? ""
    );

    // Validate category name
    if ($categoryName === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category name is required."
        ]);

        exit;
    }

    if (strlen($categoryName) > 100) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Category name must not exceed 100 characters."
        ]);

        exit;
    }

    // Check for duplicate category name
    $check = $conn->prepare(
        "SELECT category_id
         FROM categories
         WHERE LOWER(category_name) = LOWER(:category_name)"
    );

    $check->execute([
        ":category_name" => $categoryName
    ]);

    if ($check->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Category already exists."
        ]);

        exit;
    }

    // Insert category
    $sql = "
        INSERT INTO categories (
            category_name,
            description
        )
        VALUES (
            :category_name,
            :description
        )
        RETURNING
            category_id,
            category_name,
            description
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":category_name" => $categoryName,
        ":description" => $description
    ]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Category added successfully.",
        "category" => $category
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to add category."
    ]);
}

?>