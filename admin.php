<?php
declare(strict_types=1);

require __DIR__ . '/src/Bootstrap.php';

use Wiki\Auth\Auth;
use Wiki\Models\AuditLog;
use Wiki\Models\Invite;
use Wiki\Models\NotFoundLog;
use Wiki\Models\Redirect;
use Wiki\Models\Settings;
use Wiki\Models\TranslationGroup;
use Wiki\Models\User;

// ------------------------------------------------------------------
// URL routing
//
// Accepts both forms:
//   /admin/users/update/5          (clean)
//   /admin.php?p=users&action=update&id=5   (legacy)
// Clean form wins when both are present.
// ------------------------------------------------------------------
(function () {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = rtrim($scriptDir, '/');
    if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . ltrim($uri, '/');

    if (preg_match('#^/admin(?:/(.*))?$#', $uri, $m)) {
        $path  = $m[1] ?? '';
        $parts = $path === ''
            ? []
            : array_values(array_filter(explode('/', trim($path, '/')), fn($p) => $p !== ''));

        $_GET['p']      = $parts[0] ?? 'dashboard';
        $_GET['action'] = $parts[1] ?? '';
        $_GET['id']     = $parts[2] ?? '';
    }
})();

$action = $_GET['p'] ?? 'dashboard';

// Support /admin/settings/rebuild as a shorthand for ?rebuild=1
if ($action === 'settings' && ($_GET['action'] ?? '') === 'rebuild') {
    $_GET['rebuild'] = 1;
    $_GET['action']  = '';
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $loginError = 'CSRF check failed';
    } else {
        if (Auth::login(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
            AuditLog::log('auth_login', 'user', (int)Auth::user()['id'], ['via' => 'password']);
            redirect('admin');
        }
        $loginError = 'Invalid username or password';
    }
}

if ($action === 'logout') { Auth::logout(); redirect('admin/login'); }

if (!Auth::check()) { require WIKI_ROOT . '/templates/admin/login.php'; exit; }
if (!Auth::isAdmin()) { http_response_code(403); exit('Forbidden'); }

// Settings save
if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['action'])) {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $saveMsg = ['type' => 'error', 'text' => 'CSRF check failed'];
    } else {
        $posted = $_POST['settings'] ?? [];
        $defaults = Settings::defaults();
        $count = 0;
        foreach ($posted as $k => $v) {
            if (!array_key_exists($k, $defaults)) continue;
            Settings::set($k, is_array($v) ? implode(',', $v) : (string)$v);
            $count++;
        }
        foreach ($defaults as $k => $defVal) {
            if ($defVal === '0' || $defVal === '1') {
                if (!array_key_exists($k, $posted)) Settings::set($k, '0');
            }
        }
        if (isset($posted['mail.password']) && (string)$posted['mail.password'] !== '') {
            Settings::set('mail.password', \Wiki\Services\MailService::encrypt((string)$posted['mail.password']));
        }
        \Wiki\Services\CacheService::flush();
        AuditLog::log('admin.settings.save', 'settings', null, ['count' => $count]);
        $saveMsg = ['type' => 'success', 'text' => "Saved $count settings"];
    }
}

if ($action === 'settings' && !empty($_GET['rebuild'])) {
    $rows = \Wiki\Services\SearchService::rebuildIndex();
    AuditLog::log('search_rebuild', 'settings', null, ['rows' => $rows]);
    redirect('admin/settings?rebuilt=' . $rows);
}

// Pages
if ($action === 'pages') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'delete' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        TranslationGroup::deleteCascade((int)$_GET['id']);
        AuditLog::log('page_delete_group', 'translation_group', (int)$_GET['id']);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Group deleted'];
        redirect('admin/pages');
    }
    if ($sub === 'move' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        TranslationGroup::move((int)$_GET['id'], $parentId, $sortOrder);
        AuditLog::log('page_move', 'translation_group', (int)$_GET['id'], ['parent' => $parentId, 'sort' => $sortOrder]);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Moved'];
        redirect('admin/pages');
    }
    if ($sub === 'lock' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $locked = (string)($_POST['locked'] ?? '0') === '1';
        TranslationGroup::setLocked((int)$_GET['id'], $locked);
        AuditLog::log('page_lock', 'translation_group', (int)$_GET['id'], ['locked' => $locked]);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => $locked ? 'Locked' : 'Unlocked'];
        redirect('admin/pages');
    }
}

// Attachments
if ($action === 'attachments' && ($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
    \Wiki\Services\AttachmentService::delete((int)$_GET['id']);
    AuditLog::log('attachment_delete', 'attachment', (int)$_GET['id']);
    redirect('admin/attachments');
}

// Users
if ($action === 'users') {
    $sub = $_GET['action'] ?? '';

    // Preserve filters on redirect
    $backParams = $_POST['back'] ?? [];
    if (!is_array($backParams)) $backParams = [];
    $backParams = array_filter($backParams, fn($v) => is_scalar($v));
    $returnUrl = 'admin/users';
    if (!empty($backParams)) {
        $returnUrl .= '?' . http_build_query($backParams);
    }

    if ($sub === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $username    = trim((string)($_POST['username'] ?? ''));
        $email       = trim((string)($_POST['email'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $role        = (string)($_POST['role'] ?? 'user');
        $status      = (string)($_POST['status'] ?? 'active');
        $verified    = !empty($_POST['email_verified']) ? 1 : 0;

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_\-\.]{3,64}$/', $username)) $errors[] = 'invalid_username';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'invalid_email';
        if (strlen($password) < 6) $errors[] = 'weak_password';
        if (User::findByUsername($username)) $errors[] = 'username_taken';
        if (User::findByEmail($email)) $errors[] = 'email_taken';
        if (!in_array($role, ['user', 'editor', 'admin'], true)) $errors[] = 'invalid_role';
        if (!in_array($status, ['active', 'pending', 'banned'], true)) $errors[] = 'invalid_status';

        if ($errors) {
            $_SESSION['flash_create_user'] = [
                'username' => $username, 'email' => $email,
                'display_name' => $displayName, 'role' => $role,
                'status' => $status, 'email_verified' => $verified,
            ];
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Failed: ' . implode(', ', $errors)];
            redirect($returnUrl);
        }

        $newId = User::create([
            'username'       => $username,
            'email'          => $email,
            'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
            'display_name'   => $displayName !== '' ? $displayName : $username,
            'role'           => $role,
            'status'         => $status,
            'email_verified' => $verified,
        ]);

        AuditLog::log('user_create', 'user', $newId, ['username' => $username, 'role' => $role, 'status' => $status]);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'User created'];
        redirect($returnUrl);
    }

    if ($sub === 'update' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $targetId  = (int)$_GET['id'];
        $selfId    = (int)Auth::user()['id'];
        $newRole   = (string)($_POST['role'] ?? 'user');
        $newStatus = (string)($_POST['status'] ?? 'active');

        if ($targetId === $selfId) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'You cannot change your own role or status.'];
            redirect($returnUrl);
        }

        $target = User::find($targetId);
        if ($target && $target['role'] === 'admin'
            && ($newRole !== 'admin' || $newStatus !== 'active')
            && User::countActiveAdmins([$targetId]) <= 0) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Cannot demote or deactivate the last active administrator.'];
            redirect($returnUrl);
        }

        User::update($targetId, [
            'role'           => $newRole,
            'status'         => $newStatus,
            'email_verified' => !empty($_POST['email_verified']) ? 1 : 0,
        ]);
        AuditLog::log('user_status_update', 'user', $targetId, ['role' => $newRole, 'status' => $newStatus]);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'User updated'];
        redirect($returnUrl);
    }

    if ($sub === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        // Support both /admin/users/delete/5 and POST id=5
        $targetId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $selfId   = (int)Auth::user()['id'];

        if (!$targetId) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'No user selected.'];
            redirect($returnUrl);
        }
        if ($targetId === $selfId) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'You cannot delete your own account.'];
            redirect($returnUrl);
        }

        $target = User::find($targetId);
        if ($target && $target['role'] === 'admin' && User::countActiveAdmins([$targetId]) <= 0) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Cannot delete the last active administrator.'];
            redirect($returnUrl);
        }

        User::delete($targetId);
        AuditLog::log('user_delete', 'user', $targetId);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'User deleted'];
        redirect($returnUrl);
    }

    if ($sub === 'batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $ids = $_POST['ids'] ?? [];
        $op  = (string)($_POST['op'] ?? '');
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));

        $selfId = (int)Auth::user()['id'];
        $ids = array_values(array_diff($ids, [$selfId]));

        if (!$ids) {
            $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'No users selected (you cannot select yourself).'];
            redirect($returnUrl);
        }

        $count = count($ids);

        switch ($op) {
            case 'set_role':
                $role = (string)($_POST['role'] ?? '');
                if (!in_array($role, ['user', 'editor', 'admin'], true)) {
                    $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Invalid role.'];
                    redirect($returnUrl);
                }
                if ($role !== 'admin') {
                    $adminIds = User::filterAdminIds($ids);
                    if ($adminIds && User::countActiveAdmins($adminIds) <= 0) {
                        $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Cannot demote the last active administrator.'];
                        redirect($returnUrl);
                    }
                }
                User::batchUpdate($ids, ['role' => $role]);
                AuditLog::log('user_batch_role', 'user', null, ['count' => $count, 'role' => $role, 'ids' => $ids]);
                $_SESSION['flash_admin'] = ['type' => 'success', 'text' => "Updated $count users."];
                break;

            case 'set_status':
                $status = (string)($_POST['status'] ?? '');
                if (!in_array($status, ['active', 'pending', 'banned'], true)) {
                    $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Invalid status.'];
                    redirect($returnUrl);
                }
                if ($status !== 'active') {
                    $adminIds = User::filterAdminIds($ids);
                    if ($adminIds && User::countActiveAdmins($adminIds) <= 0) {
                        $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Cannot deactivate the last active administrator.'];
                        redirect($returnUrl);
                    }
                }
                User::batchUpdate($ids, ['status' => $status]);
                AuditLog::log('user_batch_status', 'user', null, ['count' => $count, 'status' => $status, 'ids' => $ids]);
                $_SESSION['flash_admin'] = ['type' => 'success', 'text' => "Updated $count users."];
                break;

            case 'verify':
                User::batchUpdate($ids, ['email_verified' => 1]);
                AuditLog::log('user_batch_verify', 'user', null, ['count' => $count, 'ids' => $ids]);
                $_SESSION['flash_admin'] = ['type' => 'success', 'text' => "Verified $count users."];
                break;

            case 'unverify':
                User::batchUpdate($ids, ['email_verified' => 0]);
                AuditLog::log('user_batch_unverify', 'user', null, ['count' => $count, 'ids' => $ids]);
                $_SESSION['flash_admin'] = ['type' => 'success', 'text' => "Unverified $count users."];
                break;

            case 'delete':
                $adminIds = User::filterAdminIds($ids);
                if ($adminIds && User::countActiveAdmins($adminIds) <= 0) {
                    $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Cannot delete the last active administrator.'];
                    redirect($returnUrl);
                }
                User::batchDelete($ids);
                AuditLog::log('user_batch_delete', 'user', null, ['count' => $count, 'ids' => $ids]);
                $_SESSION['flash_admin'] = ['type' => 'success', 'text' => "Deleted $count users."];
                break;

            default:
                $_SESSION['flash_admin'] = ['type' => 'error', 'text' => 'Unknown operation.'];
        }

        redirect($returnUrl);
    }
}

// Invites
if ($action === 'invites') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $code = trim((string)($_POST['code'] ?? ''));
        $expires = trim((string)($_POST['expires_at'] ?? ''));
        Invite::create([
            'code'       => $code !== '' ? $code : strtoupper(bin2hex(random_bytes(6))),
            'created_by' => (int)Auth::user()['id'],
            'max_uses'   => max(1, (int)($_POST['max_uses'] ?? 1)),
            'expires_at' => $expires !== '' ? $expires . ' 23:59:59' : null,
        ]);
        AuditLog::log('invite_create', 'invite', null, ['code' => $code]);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Invite created'];
        redirect('admin/invites');
    }
    if ($sub === 'delete' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        Invite::delete((int)$_GET['id']);
        AuditLog::log('invite_delete', 'invite', (int)$_GET['id']);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Invite deleted'];
        redirect('admin/invites');
    }
}

// Mail templates
if ($action === 'mail_templates') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $to = trim((string)($_POST['test_to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_mail_test'] = ['ok' => false, 'message' => 'Invalid email'];
        } else {
            $r = \Wiki\Services\MailService::send(
                $to,
                'Test from ' . Settings::get('site.name'),
                "This is a test message.\n\nIf you can read this, SMTP works."
            );
            $_SESSION['flash_mail_test'] = $r;
        }
        redirect('admin/mail_templates');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sub === '') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
            $saveMsg = ['type' => 'error', 'text' => 'CSRF check failed'];
        } else {
            $posted = $_POST['settings'] ?? [];
            $count = 0;
            foreach ($posted as $k => $v) {
                if (!str_starts_with($k, 'mail.tpl.')) continue;
                Settings::set($k, (string)$v);
                $count++;
            }
            AuditLog::log('mail_template_save', 'settings', null, ['count' => $count]);
            $saveMsg = ['type' => 'success', 'text' => "Saved $count templates"];
        }
    }
}

// Redirects
if ($action === 'redirects') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $from = trim((string)($_POST['from_path'] ?? ''));
        $to   = trim((string)($_POST['to_path'] ?? ''));
        $code = (int)($_POST['status_code'] ?? 301);
        if ($from !== '' && $to !== '') {
            Redirect::create($from, $to, $code, null, (int)Auth::user()['id']);
            AuditLog::log('redirect_create', 'redirect', null, ['from' => $from, 'to' => $to, 'status' => $code]);
            $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Redirect created'];
        }
        redirect('admin/redirects');
    }
    if ($sub === 'delete' && !empty($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        Redirect::delete((int)$_GET['id']);
        AuditLog::log('redirect_delete', 'redirect', (int)$_GET['id']);
        $_SESSION['flash_admin'] = ['type' => 'success', 'text' => 'Redirect deleted'];
        redirect('admin/redirects');
    }
}

// 404 log
if ($action === 'not_found') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        NotFoundLog::clear();
        redirect('admin/not_found');
    }
}

// Audit log
if ($action === 'audit') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        AuditLog::clear();
        AuditLog::log('audit_clear');
        redirect('admin/audit?cleared=1');
    }
}

// Storage
if ($action === 'storage') {
    $sub = $_GET['action'] ?? '';
    if ($sub === 'test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $cfg = require WIKI_ROOT . '/config/config.php';
        $driver = (string)($cfg['db']['driver'] ?? 'sqlite');
        if ($driver === 'http') {
            $r = \Wiki\Database\HttpDatabase::testConnection($cfg['db']);
        } else {
            try {
                $db = \Wiki\Database\DatabaseFactory::make($cfg['db']);
                $db->fetchValue('SELECT 1');
                $r = ['ok' => true, 'message' => 'Connection OK'];
            } catch (\Throwable $e) {
                $r = ['ok' => false, 'message' => $e->getMessage()];
            }
        }
        $_SESSION['flash_storage'] = $r;
        redirect('admin/storage');
    }
    if ($sub === 'cache_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $_SESSION['flash_storage'] = \Wiki\Services\CacheService::test();
        redirect('admin/storage');
    }
    if ($sub === 'cache_flush' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        \Wiki\Services\CacheService::flush();
        $_SESSION['flash_storage'] = ['ok' => true, 'message' => 'Cache cleared'];
        redirect('admin/storage');
    }
    if ($sub === 'vacuum' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }
        $cfg = require WIKI_ROOT . '/config/config.php';
        if (($cfg['db']['driver'] ?? '') === 'sqlite') {
            try {
                $db = \Wiki\Database\DatabaseFactory::make($cfg['db']);
                $db->pdo()->exec('VACUUM');
                $_SESSION['flash_storage'] = ['ok' => true, 'message' => 'VACUUM completed'];
            } catch (\Throwable $e) {
                $_SESSION['flash_storage'] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }
        redirect('admin/storage');
    }
}

$allowed = ['dashboard', 'pages', 'settings', 'attachments', 'users', 'invites', 'mail_templates', 'redirects', 'not_found', 'audit', 'storage'];
if (!in_array($action, $allowed, true)) $action = 'dashboard';

$pageTitleKey = [
    'dashboard'      => 'admin.menu.dashboard',
    'pages'          => 'admin.menu.pages',
    'settings'       => 'admin.menu.settings',
    'attachments'    => 'admin.menu.attachments',
    'users'          => 'admin.menu.users',
    'invites'        => 'admin.menu.invites',
    'mail_templates' => 'admin.menu.mail_templates',
    'redirects'      => 'admin.menu.redirects',
    'not_found'      => 'admin.menu.not_found',
    'audit'          => 'admin.menu.audit',
    'storage'        => 'admin.menu.storage',
][$action] ?? 'admin.menu.dashboard';

$pageTitle = \Wiki\Services\I18nService::t($pageTitleKey);

require WIKI_ROOT . '/templates/admin/layout.php';
