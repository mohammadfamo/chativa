<?php
/**
 * اتصال به دیتابیس با PDO (Singleton)
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $cfg = DB_CONFIG;
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('خطا در اتصال به دیتابیس: ' . $e->getMessage());
            }
            die('خطا در اتصال به دیتابیس. لطفاً بعداً دوباره تلاش کنید.');
        }
    }

    return $pdo;
}
