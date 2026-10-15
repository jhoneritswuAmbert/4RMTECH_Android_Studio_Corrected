<?php
require_once __DIR__ . "/cors.php";


require_once "db.php";

header("Content-Type: application/json");

try {

    // If an ID is provided, return one product
    if (isset($_GET["id"])) {

        $productId = filter_input(
            INPUT_GET,
            "id",
            FILTER_VALIDATE_INT
        );

        if ($productId === false || $productId === null) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Invalid product ID."
            ]);

            exit;
        }

        $sql = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.brand,
                    p.description,
                    p.price,
                    p.stock_quantity,
                    p.category_id,
                    p.image_url,
                    c.category_name
                FROM products p
                INNER JOIN categories c
                    ON p.category_id = c.category_id
                WHERE p.product_id = :product_id";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ":product_id" => $productId
        ]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Product not found."
            ]);

            exit;
        }

        echo json_encode([
            "success" => true,
            "product" => $product
        ]);

        exit;
    }

    // Otherwise, return all products
    $sql = "SELECT 
                p.product_id,
                p.product_name,
                p.brand,
                p.description,
                p.price,
                p.stock_quantity,
                p.category_id,
                p.image_url,
                c.category_name
            FROM products p
            INNER JOIN categories c
                ON p.category_id = c.category_id
            ORDER BY p.product_id ASC";

    $stmt = $conn->query($sql);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error occurred."
    ]);
}

?>