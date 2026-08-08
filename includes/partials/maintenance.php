<?php
declare(strict_types=1);
$maintenanceMessage = get_setting('maintenance_message', 'سایت موقتاً در حال تعمیر و نگهداری است. لطفاً کمی بعد دوباره سر بزنید.');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>در حال تعمیر | Chativa</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="text-align:center;">
    <div class="brand" style="justify-content:center;">
      <span class="brand-logo"><?= icon_svg('settings') ?></span>
      <h1>در حال تعمیر</h1>
    </div>
    <p class="auth-subtitle"><?= e($maintenanceMessage) ?></p>
  </div>
</div>
</body>
</html>
