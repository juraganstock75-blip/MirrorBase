<?php
// config/db.php — PDO + auto-migrate + auto-patch kolom & index
declare(strict_types=1);

class Database {
    private $host     = "sql105.ezyro.com";
    private $username = "ezyro_42987016";
    private $password = "315284eb";
    private $db_name  = "ezyro_42987016_zone_xsec";
    public  $conn;

    public function connect(): void {
        try {
            $root = new PDO(
                "mysql:host={$this->host};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $root->exec(
                "CREATE DATABASE IF NOT EXISTS `{$this->db_name}`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (PDOException $e) {
            // ignore — biasanya dibuat manual di vPanel
        }

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $this->migrate();
        } catch (PDOException $e) {
            http_response_code(500);
            exit('DB connection failed: ' . $e->getMessage());
        }
    }

    private function migrate(): void {
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS `teams` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `name` VARCHAR(128) NOT NULL UNIQUE,
              `note` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS `archives` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `uid` CHAR(7) NULL UNIQUE,
              `target_url` VARCHAR(512) NOT NULL,
              `target_domain` VARCHAR(255) NOT NULL,
              `category` ENUM('special','archive','onhold') NOT NULL DEFAULT 'onhold',
              `flag_home` TINYINT(1) DEFAULT 0,
              `flag_mass` TINYINT(1) DEFAULT 0,
              `flag_redef` TINYINT(1) DEFAULT 0,
              `country_code` VARCHAR(2) NULL,
              `isp` VARCHAR(255) NULL,
              `asn` VARCHAR(32) NULL,
              `is_special` TINYINT(1) DEFAULT 0,
              `defacer` VARCHAR(128) NULL,
              `team_id` INT UNSIGNED NULL,
              `ip_address` VARCHAR(45) NULL,
              `ip_mass` VARCHAR(45) NULL,
              `server_type` VARCHAR(128) NULL,
              `target_os` VARCHAR(64) NULL,
              `poc` TINYINT UNSIGNED NULL,
              `reason` TINYINT UNSIGNED NULL,
              `mirror_url` VARCHAR(512) NULL,
              `screenshot` VARCHAR(255) NULL,
              `screenshot_url` VARCHAR(512) NULL,
              `archive_date` DATE NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_domain` (`target_domain`),
              INDEX `idx_date` (`archive_date`),
              INDEX `idx_team` (`team_id`),
              INDEX `idx_poc` (`poc`),
              INDEX `idx_reason` (`reason`),
              INDEX `idx_category` (`category`),
              INDEX `idx_defacer` (`defacer`),
              INDEX `idx_country` (`country_code`),
              INDEX `idx_server` (`server_type`),
              FULLTEXT KEY `ft_search` (`target_url`, `defacer`),
              CONSTRAINT `fk_team` FOREIGN KEY (`team_id`)
                REFERENCES `teams`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addMissingColumns('archives', [
            'uid'            => "CHAR(7) NULL UNIQUE AFTER `id`",
            'flag_home'      => "TINYINT(1) DEFAULT 0 AFTER `category`",
            'flag_mass'      => "TINYINT(1) DEFAULT 0 AFTER `flag_home`",
            'flag_redef'     => "TINYINT(1) DEFAULT 0 AFTER `flag_mass`",
            'country_code'   => "VARCHAR(2) NULL AFTER `flag_redef`",
            'isp'            => "VARCHAR(255) NULL AFTER `country_code`",
            'asn'            => "VARCHAR(32) NULL AFTER `isp`",
            'is_special'     => "TINYINT(1) DEFAULT 0 AFTER `asn`",
            'ip_mass'        => "VARCHAR(45) NULL AFTER `ip_address`",
            'target_os'      => "VARCHAR(64) NULL AFTER `server_type`",
            'screenshot_url' => "VARCHAR(512) NULL AFTER `screenshot`",
        ]);

        $this->addMissingIndexes('archives', [
            'idx_defacer' => '(`defacer`)',
            'idx_country' => '(`country_code`)',
            'idx_server'  => '(`server_type`)',
            'uid'         => '(`uid`)',
        ]);
    }

    private function addMissingColumns(string $table, array $columns): void {
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM `{$table}`");
            $existing = [];
            foreach ($stmt->fetchAll() as $row) {
                $existing[strtolower($row['Field'])] = true;
            }
        } catch (PDOException $e) {
            return;
        }

        foreach ($columns as $name => $def) {
            if (isset($existing[strtolower($name)])) continue;
            try {
                $this->conn->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
            } catch (PDOException $e) {
                error_log("addMissingColumns {$table}.{$name}: " . $e->getMessage());
            }
        }
    }

    private function addMissingIndexes(string $table, array $indexes): void {
        try {
            $stmt = $this->conn->query("SHOW INDEX FROM `{$table}`");
            $existing = [];
            foreach ($stmt->fetchAll() as $row) {
                $existing[strtolower($row['Key_name'])] = true;
            }
        } catch (PDOException $e) {
            return;
        }

        foreach ($indexes as $name => $cols) {
            if (isset($existing[strtolower($name)])) continue;
            try {
                $this->conn->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` {$cols}");
            } catch (PDOException $e) {
                error_log("addMissingIndexes {$table}.{$name}: " . $e->getMessage());
            }
        }
    }
}

$db = new Database();
$db->connect();
$pdo = $db->conn;