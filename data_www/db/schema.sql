BEGIN TRANSACTION;
CREATE TABLE incidentes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    servidor_id INTEGER NOT NULL,
    desconectado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reconectado_en DATETIME NULL,
    duracion_segundos INTEGER NULL,
    motivo TEXT NULL,
    FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE CASCADE
);
CREATE TABLE metricas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    servidor_id INTEGER NOT NULL,
    cpu_usage TEXT,
    ram_usage TEXT,
    uptime INTEGER,
    raw_json TEXT NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE CASCADE
);
INSERT INTO "metricas" VALUES(1,1,'15%','35%',86400,'{"time": 1793365241, "system_uptime": 86400, "hostname": "servidor-demo", "operating_system": {"name": "Debian GNU/Linux 12 (bookworm)", "version": "12", "kernel_version": "6.1.0-21-amd64"}, "ip_public_address": "203.0.113.10", "city": "Buenos Aires", "country": "Argentina", "CPU": "Intel Core i7-12700", "cores": 8, "cpu_speed": "2.50 GHz", "cpu_usage": "15%", "RAM": "16 GB", "ram_usage": "35%", "GPU": {"name": "N/A"}, "disk_space": [{"filesystem": "/dev/sda1", "total_space": "250 GB", "used_space": "45 GB", "usage_percentage": "18%", "mounted_on": "/"}], "network_interfaces": [{"interface": "eth0", "ip_address": "192.168.1.100", "mac_address": "52:54:00:12:34:56"}]}','2026-10-30 13:00:41');
CREATE TABLE servidores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    host TEXT NOT NULL,
    puerto INTEGER DEFAULT 22,
    usuario TEXT NOT NULL,
    auth_tipo TEXT NOT NULL DEFAULT 'password',
    password TEXT NULL,
    clave_privada TEXT NULL,
    ruta_binario TEXT DEFAULT 'gosvrinfo',
    activo INTEGER DEFAULT 1,
    ultimo_estado TEXT DEFAULT 'pendiente',
    ultimo_check DATETIME NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO "servidores" VALUES(1,'Servidor-Demo','192.168.1.100',22,'demo','password','demo123',NULL,'gosvrinfo',1,'online','2026-10-30 10:00:41','2026-10-30 13:00:41');
CREATE INDEX idx_metricas_servidor ON metricas(servidor_id);
CREATE INDEX idx_incidentes_servidor ON incidentes(servidor_id);
DELETE FROM "sqlite_sequence";
INSERT INTO "sqlite_sequence" VALUES('servidores',1);
INSERT INTO "sqlite_sequence" VALUES('metricas',1);
COMMIT;
