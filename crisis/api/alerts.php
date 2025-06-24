<?php
require __DIR__ . '/../init.php'; // Adjust path to init.php

// Set content type to JSON
header('Content-Type: application/json');

// --- Database Connection ---
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// We expect them to provide a secret "API key" in the web address.
$apiKey = $_GET['api_key'] ?? null;

if (!$apiKey) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'API key is missing.']);
    exit;
}

// Now we check if the key they gave us is a real one that exists in our user list.
$stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ?");
$stmt->execute([$apiKey]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid API key.']);
    exit;
}

// If they made it this far, they're a valid user! Let's get them the data
try {
    $alertsStmt = $pdo->query("SELECT id, source, title, lat, lng, radius, severity, created_at FROM alerts ORDER BY created_at DESC");
    $rawAlerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // We're going to re-format the data to be more clean and consistent for other programs to use
    $formattedAlerts = [];
    $romaniaTimezone = new DateTimeZone('Europe/Bucharest');

    foreach ($rawAlerts as $alert) {
        // Create a DateTime object from the DB string, specifying its original timezone
        $date = new DateTime($alert['created_at'], $romaniaTimezone);
        // How it will look at the end
        $formattedAlerts[] = [
            'id' => (int) $alert['id'],
            'source' => $alert['source'],
            'title' => $alert['title'],
            'message' => null, // Message is now explicitly null
            'location' => [
                'lat' => (float) $alert['lat'],
                'lng' => (float) $alert['lng'],
            ],
            'radius' => (int) $alert['radius'],
            'radius_unit' => 'meters',
            'severity' => (int) $alert['severity'],
            'created_at' => $date->format(DateTime::ATOM), // Convert to ISO 8601 format (e.g., 2025-06-23T14:30:00+03:00)
        ];
    }

    // Set response code and return the newly formatted data
    http_response_code(200);
    echo json_encode($formattedAlerts, JSON_PRETTY_PRINT); // Using JSON_PRETTY_PRINT for readability

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch alerts.', 'details' => $e->getMessage()]);
}