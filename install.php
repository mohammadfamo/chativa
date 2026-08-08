<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$already_installed = file_exists(DB_CONFIG_FILE);
$errors = [];
$success = false;

if (!$already_installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string) ($_POST['pass'] ?? '');

    if ($host === '' || $name === '' || $user === '') {
        $errors[] = 'لطفاً فیلدهای ضروری (هاست، نام دیتابیس، یوزرنیم) را پر کنید.';
    } else {
        try {
            // ابتدا مستقیم با نام دیتابیس وصل می‌شویم (حالت رایج در cPanel که
            // دیتابیس و کاربر از قبل توسط کاربر ساخته شده‌اند و دسترسی CREATE ندارند)
            try {
                $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException $connectError) {
                // اتصال مستقیم ناموفق بود؛ شاید دیتابیس هنوز وجود ندارد (حالت لوکال با
                // کاربر root) - تلاش می‌کنیم بدون انتخاب دیتابیس وصل شده و آن را بسازیم
                $pdoRoot = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            }

            $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('فایل schema.sql پیدا نشد.');
            }

            run_sql_script($pdo, $schema);

            // همه‌ی مایگریشن‌های موجود را به‌عنوان اجراشده علامت بزن، چون
            // schema.sql از قبل شامل آخرین ساختار جداول است.
            $migrationFiles = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
            if ($migrationFiles !== []) {
                $insertMigration = $pdo->prepare(
                    'INSERT IGNORE INTO schema_migrations (migration) VALUES (?)'
                );
                foreach ($migrationFiles as $migrationFile) {
                    $insertMigration->execute([basename($migrationFile)]);
                }
            }

            $configContent = "<?php\n\nreturn [\n"
                . "    'host'    => " . var_export($host, true) . ",\n"
                . "    'name'    => " . var_export($name, true) . ",\n"
                . "    'user'    => " . var_export($user, true) . ",\n"
                . "    'pass'    => " . var_export($pass, true) . ",\n"
                . "    'charset' => 'utf8mb4',\n"
                . "];\n";

            if (file_put_contents(DB_CONFIG_FILE, $configContent) === false) {
                throw new RuntimeException('نوشتن فایل تنظیمات با خطا مواجه شد. مجوز پوشه config را بررسی کنید.');
            }

            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'خطا: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>نصب Chativa</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <span class="brand-logo">C</span>
      <h1>نصب Chativa</h1>
    </div>

    <?php if ($already_installed): ?>
      <div class="alert alert-info">
        برنامه قبلاً نصب شده است. برای نصب مجدد، فایل <code>config/db_config.php</code> را حذف کنید.
      </div>
      <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">رفتن به صفحه ورود</a>

    <?php elseif ($success): ?>
      <div class="alert alert-success">
        نصب با موفقیت انجام شد! دیتابیس آماده است.
      </div>
      <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">ساخت اولین حساب کاربری</a>

    <?php else: ?>
      <p class="auth-subtitle">اطلاعات اتصال به دیتابیس MySQL خود را وارد کنید.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" class="auth-form">
        <label>هاست دیتابیس
          <input type="text" name="host" value="<?= e($_POST['host'] ?? 'localhost') ?>" required>
        </label>
        <label>نام دیتابیس
          <input type="text" name="name" value="<?= e($_POST['name'] ?? 'chativa') ?>" required>
        </label>
        <label>یوزرنیم دیتابیس
          <input type="text" name="user" value="<?= e($_POST['user'] ?? 'root') ?>" required>
        </label>
        <label>پسورد دیتابیس
          <input type="password" name="pass" value="">
        </label>
        <button type="submit" class="btn btn-primary">نصب و ساخت جداول</button>
      </form>
      <p class="auth-hint">در هاست‌های cPanel معمولاً باید ابتدا دیتابیس و کاربر را از بخش MySQL Databases بسازید.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
