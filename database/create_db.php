<?php

try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'noo_v2_db'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE DATABASE noo_v2_db");
        echo "SUCCESS: Database noo_v2_db berhasil dibuat di PostgreSQL!\n";
    } else {
        echo "INFO: Database noo_v2_db sudah ada di PostgreSQL.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
