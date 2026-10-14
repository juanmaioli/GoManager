<?php
// includes/db.php - Conexión y esquema SQLite

declare(strict_types=1);

$dbPath = __DIR__ . '/../db/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Inicializar tablas si no existen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS servidores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            host TEXT NOT NULL,
            puerto INTEGER DEFAULT 22,
            usuario TEXT NOT NULL,
            auth_tipo TEXT NOT NULL DEFAULT 'password', -- 'password' o 'key'
            password TEXT NULL,
            clave_privada TEXT NULL,
            ruta_binario TEXT DEFAULT 'gosvrinfo',
            activo INTEGER DEFAULT 1,
            ultimo_estado TEXT DEFAULT 'pendiente', -- 'online', 'offline', 'error', 'pendiente'
            ultimo_check DATETIME NULL,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS metricas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            servidor_id INTEGER NOT NULL,
            cpu_usage TEXT,
            ram_usage TEXT,
            uptime INTEGER,
            raw_json TEXT NOT NULL,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_metricas_servidor ON metricas(servidor_id);
    ");

} catch (PDOException $e) {
    die("Error al conectar con SQLite: " . htmlspecialchars($e->getMessage()));
}
