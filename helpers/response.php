<?php

function sendResponse($status, $message, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);

    header("Content-Type: application/json");

    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ], JSON_PRETTY_PRINT);

    exit;
}

?>