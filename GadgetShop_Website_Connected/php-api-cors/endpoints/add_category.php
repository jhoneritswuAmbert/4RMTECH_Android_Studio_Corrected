<?php
require_once __DIR__ . "/cors.php";


header("Content-Type: application/json");

require_once "db.php";

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