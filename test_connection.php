<?php
require_once __DIR__ . '/backend/database.php'; // path relatif ke backend/database.php

echo "<pre>";

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        echo "✅ Database connection successful!\n";
        echo "Connected to database: mp_development\n\n";

        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "Tables in database:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    } else {
        echo "❌ Failed to connect to database\n";
    }
} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
