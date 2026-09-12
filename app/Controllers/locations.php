<?php
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../Models/locations.php';
try {
    $data = sbcis_locations();
    header('Cache-Control: public, max-age=300');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Locations endpoint: ' . $error->getMessage());
    http_response_code(503); echo json_encode(['error' => 'Location lists are unavailable. Please retry.']);
}
