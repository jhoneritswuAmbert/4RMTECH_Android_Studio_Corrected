<?php
require_once __DIR__ . "/cors.php";
http_response_code(404);
header("Content-Type: application/json");
echo json_encode(["success" => false, "message" => "Not found."]);
