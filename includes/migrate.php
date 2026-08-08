<?php
/**
 * اجرای خودکار مایگریشن‌های دیتابیس.
 * وقتی پروژه آپدیت می‌شود و جدول‌های جدیدی لازم است، این تابع بدون نیاز
 * به اجرای دوباره‌ی install.php آن‌ها را روی نصب‌های قبلی اعمال می‌کند.
 */

declare(strict_types=1);

function run_pending_migrations(): void
{
    // هر session فقط یک‌بار چک می‌شود تا روی درخواست‌های polling سربار نگذارد
    if (!empty($_SESSION['migrations_ok'])) {
        return;
    }

    $pdo = db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(190) NOT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationsDir = ROOT_PATH . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        $_SESSION['migrations_ok'] = true;
        return;
    }

    $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files);

    $allOk = true;

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            continue;
        }

        try {
            run_sql_script($pdo, $sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
            $stmt->execute([$name]);
        } catch (Throwable $e) {
            $allOk = false;
            error_log('Chativa migration failed (' . $name . '): ' . $e->getMessage());
            break; // ترتیب مایگریشن‌ها مهم است؛ در صورت خطا متوقف شو
        }
    }

    if ($allOk) {
        $_SESSION['migrations_ok'] = true;
    }
}
