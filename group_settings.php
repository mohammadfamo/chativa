<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$groupId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($groupId <= 0 || !is_group_member($groupId, (int) $me['id'])) {
    set_flash('error', 'گروه مورد نظر پیدا نشد.');
    redirect('chat.php');
}

$activeConversationId = -1;
$activeGroupId = $groupId;
$group = get_group_row($groupId);

if ($group === null) {
    redirect('chat.php');
}

$myRole = group_member_role($groupId, (int) $me['id']);
$isOwner = $myRole === 'owner';
$isGroupAdmin = in_array($myRole, ['owner', 'admin'], true);
$members = list_group_members($groupId);
$inviteUrl = url('join_group.php?code=' . $group['invite_code']);
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>تنظیمات <?= e($group['name']) ?> | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell page-static-scroll">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <a href="<?= e(url('group.php?id=' . $groupId)) ?>" class="btn-back-mobile" aria-label="بازگشت به گروه"><?= icon_svg('chevron-back') ?></a>
      <div class="chat-header-title">
        <span class="username">تنظیمات گروه</span>
      </div>
    </header>

    <div class="new-chat-body settings-page">
      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>

      <div class="settings-card">
        <div class="settings-profile-header">
          <?= avatar_html(['username' => $group['name'], 'avatar_color' => $group['avatar_color'], 'avatar_path' => $group['avatar_path']], 'avatar-lg', false) ?>
          <div class="settings-profile-header-name"><?= e($group['name']) ?></div>
          <div class="settings-profile-header-username"><?= count($members) ?> عضو</div>
        </div>

        <?php if ($isGroupAdmin): ?>
          <form id="group-profile-form" class="settings-form">
            <input type="hidden" id="group-id-field" value="<?= (int) $groupId ?>">
            <div class="settings-field-group">
              <label>نام گروه
                <input type="text" id="group-name-input" value="<?= e($group['name']) ?>" maxlength="100" required>
              </label>
              <label>توضیحات
                <textarea id="group-desc-input" rows="3" maxlength="300" placeholder="درباره‌ی این گروه..."><?= e((string) ($group['description'] ?? '')) ?></textarea>
              </label>
            </div>
            <div class="settings-form-actions">
              <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
              <span id="group-profile-result" class="field-hint"></span>
            </div>
          </form>
        <?php elseif (!empty($group['description'])): ?>
          <p class="settings-card-desc"><?= e($group['description']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($isGroupAdmin): ?>
        <div class="settings-card">
          <h2 class="settings-card-title">لینک دعوت</h2>
          <p class="settings-card-desc">هر کسی این لینک را داشته باشد می‌تواند بدون تایید عضو گروه شود.</p>
          <div class="invite-link-row">
            <input type="text" id="invite-link-input" value="<?= e($inviteUrl) ?>" readonly dir="ltr">
            <button type="button" id="btn-copy-invite" class="btn-mini">کپی</button>
            <button type="button" id="btn-regen-invite" class="btn-mini">لینک جدید</button>
          </div>
          <span id="invite-result" class="field-hint"></span>
        </div>

        <div class="settings-card">
          <h2 class="settings-card-title">افزودن عضو</h2>
          <form id="add-member-form" class="settings-form settings-form-inline">
            <input type="text" id="add-member-username" placeholder="آیدی کاربر (بدون @)" required autocomplete="off">
            <button type="submit" class="btn-mini btn-mini-primary">افزودن</button>
          </form>
          <span id="add-member-result" class="field-hint"></span>
        </div>
      <?php endif; ?>

      <div class="settings-card">
        <h2 class="settings-card-title">اعضای گروه</h2>
        <ul class="group-member-list" id="group-member-list">
          <?php foreach ($members as $member): ?>
            <?php
              $memberRole = (string) $member['role'];
              $isMe = (int) $member['id'] === (int) $me['id'];
              $canManage = $isGroupAdmin && !$isMe && $memberRole !== 'owner' && ($isOwner || $memberRole === 'member');
            ?>
            <li class="group-member-item" data-user-id="<?= (int) $member['id'] ?>">
              <?= avatar_html($member, 'avatar-sm') ?>
              <span class="conv-info">
                <span class="conv-name-row">
                  <span class="conv-name"><?= e(display_name_of($member)) ?></span>
                  <?php if ($memberRole === 'owner'): ?><?= icon_svg('crown') ?><?php endif; ?>
                </span>
                <span class="conv-preview">
                  <?= $memberRole === 'owner' ? 'مالک گروه' : ($memberRole === 'admin' ? 'ادمین' : 'عضو') ?>
                  <?= $isMe ? ' (خودت)' : '' ?>
                </span>
              </span>
              <?php if ($canManage): ?>
                <span class="group-member-actions">
                  <?php if ($isOwner): ?>
                    <button type="button" class="btn-mini js-set-role" data-role="<?= $memberRole === 'admin' ? 'member' : 'admin' ?>">
                      <?= $memberRole === 'admin' ? 'خلع ادمین' : 'ادمین کن' ?>
                    </button>
                  <?php endif; ?>
                  <button type="button" class="btn-mini btn-mini-danger js-remove-member">حذف</button>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="danger-zone">
        <h3 class="danger-zone-title"><?= icon_svg('warning') ?> <?= $isOwner ? 'حذف گروه' : 'خروج از گروه' ?></h3>
        <?php if ($isOwner): ?>
          <p>با حذف گروه، تمام پیام‌ها و پیوست‌های آن برای همیشه پاک می‌شوند. این عمل غیرقابل‌بازگشت است.</p>
          <form method="post" action="<?= e(url('delete_group_action.php')) ?>" data-confirm="آیا کاملاً مطمئنید؟ این گروه و تمام پیام‌های آن برای همیشه حذف خواهند شد.">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
            <button type="submit" class="btn-mini btn-mini-danger">حذف همیشگی گروه</button>
          </form>
        <?php else: ?>
          <p>با خروج از گروه، دیگر پیام‌های جدید آن را نمی‌بینی.</p>
          <form method="post" action="<?= e(url('leave_group_action.php')) ?>" data-confirm="آیا مطمئنی می‌خواهی از این گروه خارج شوی؟" data-confirm-danger="0">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
            <button type="submit" class="btn-mini btn-mini-danger">خروج از گروه</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script>
    (function () {
      var tokenEl = document.getElementById('global-csrf-token');
      var baseUrl = (tokenEl && tokenEl.dataset.baseUrl) || '/';
      var csrfToken = tokenEl ? tokenEl.value : '';
      var groupId = <?= (int) $groupId ?>;

      function post(url, body) {
        return fetch(baseUrl + url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(Object.assign({ csrf_token: csrfToken }, body)),
        }).then(function (res) { return res.json(); });
      }

      var profileForm = document.getElementById('group-profile-form');
      if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var resultEl = document.getElementById('group-profile-result');
          resultEl.textContent = 'در حال ذخیره...';
          post('api/update_group_profile.php', {
            group_id: groupId,
            name: document.getElementById('group-name-input').value,
            description: document.getElementById('group-desc-input').value,
          }).then(function (data) {
            resultEl.textContent = data.ok ? 'ذخیره شد.' : 'خطا در ذخیره‌سازی.';
          }).catch(function () {
            resultEl.textContent = 'خطا در ارتباط با سرور.';
          });
        });
      }

      var copyBtn = document.getElementById('btn-copy-invite');
      if (copyBtn) {
        copyBtn.addEventListener('click', function () {
          var input = document.getElementById('invite-link-input');
          input.select();
          navigator.clipboard && navigator.clipboard.writeText(input.value).catch(function () {});
          document.getElementById('invite-result').textContent = 'لینک کپی شد.';
        });
      }

      var regenBtn = document.getElementById('btn-regen-invite');
      if (regenBtn) {
        regenBtn.addEventListener('click', function () {
          if (!window.ChativaDialog) return;
          window.ChativaDialog.confirm('لینک قبلی از کار می‌افتد و فقط لینک جدید معتبر خواهد بود. ادامه می‌دهی؟', { danger: false }).then(function (ok) {
            if (!ok) return;
            post('api/regenerate_group_invite.php', {}).then(function (data) {
              if (data.invite_url) {
                document.getElementById('invite-link-input').value = data.invite_url;
                document.getElementById('invite-result').textContent = 'لینک جدید ساخته شد.';
              }
            });
          });
        });
      }

      var addMemberForm = document.getElementById('add-member-form');
      if (addMemberForm) {
        addMemberForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var input = document.getElementById('add-member-username');
          var resultEl = document.getElementById('add-member-result');
          var username = input.value.trim();
          if (!username) return;
          resultEl.textContent = 'در حال افزودن...';
          post('api/group_add_member.php', { group_id: groupId, username: username }).then(function (data) {
            if (data.member) {
              resultEl.textContent = 'عضو شد. صفحه را رفرش کن تا در لیست ببینیش.';
              input.value = '';
            } else if (data.error === 'user_not_found') {
              resultEl.textContent = 'کاربری با این آیدی پیدا نشد.';
            } else if (data.error === 'already_member') {
              resultEl.textContent = 'این کاربر از قبل عضو گروه است.';
            } else {
              resultEl.textContent = 'خطا در افزودن عضو.';
            }
          });
        });
      }

      var memberList = document.getElementById('group-member-list');
      if (memberList) {
        memberList.addEventListener('click', function (e) {
          var item = e.target.closest('.group-member-item');
          if (!item) return;
          var userId = parseInt(item.dataset.userId, 10);

          if (e.target.closest('.js-remove-member')) {
            if (!window.ChativaDialog) return;
            window.ChativaDialog.confirm('این عضو از گروه حذف شود؟', { danger: true }).then(function (ok) {
              if (!ok) return;
              post('api/group_remove_member.php', { group_id: groupId, user_id: userId }).then(function (data) {
                if (data.ok) item.remove();
              });
            });
          } else if (e.target.closest('.js-set-role')) {
            var btn = e.target.closest('.js-set-role');
            var role = btn.dataset.role;
            post('api/group_set_role.php', { group_id: groupId, user_id: userId, role: role }).then(function (data) {
              if (data.ok) location.reload();
            });
          }
        });
      }
    })();
  </script>

</div>
</div>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/sidebar-unread.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
