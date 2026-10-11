<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Auth\Auth;
use Wiki\Auth\GitHubOAuth;
use Wiki\Models\AuditLog;
use Wiki\Models\EmailToken;
use Wiki\Models\Invite;
use Wiki\Models\Settings;
use Wiki\Models\User;
use Wiki\Services\I18nService;
use Wiki\Services\MailService;

class UserController
{
    public function loginForm(): void
    {
        if (Auth::check()) { redirect(''); return; }
        $error = $_GET['error'] ?? '';
        $next  = (string)($_GET['next'] ?? '');
        require WIKI_ROOT . '/templates/auth/login.php';
    }

    public function login(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { redirect('login?error=csrf'); return; }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $next     = (string)($_POST['next'] ?? '');

        if (!Auth::login($username, $password)) {
            redirect('login?error=invalid' . ($next !== '' ? '&next=' . urlencode($next) : ''));
            return;
        }

        $u = Auth::user();
        if ((int)$u['email_verified'] === 0 && Settings::getBool('auth.email_verify')) {
            redirect('verify?pending=1');
            return;
        }

        AuditLog::log('auth.login', 'user', (int)$u['id']);
        redirect($next !== '' ? $next : '');
    }

    public function registerForm(): void
    {
        if (Auth::check()) { redirect(''); return; }
        if (!Settings::getBool('auth.allow_register') && !Settings::getBool('auth.invite_only')) {
            http_response_code(403); exit('Registration is disabled');
        }
        $error = $_GET['error'] ?? '';
        require WIKI_ROOT . '/templates/auth/register.php';
    }

    public function register(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { redirect('register?error=csrf'); return; }
        if (!Settings::getBool('auth.allow_register') && !Settings::getBool('auth.invite_only')) {
            http_response_code(403); exit('Registration is disabled');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $invite   = trim((string)($_POST['invite'] ?? ''));

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_\-\.]{3,64}$/', $username)) $errors[] = 'invalid_username';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'invalid_email';
        if (strlen($password) < 6) $errors[] = 'weak_password';
        if (User::findByUsername($username)) $errors[] = 'username_taken';
        if (User::findByEmail($email)) $errors[] = 'email_taken';

        $inviteRequired = Settings::getBool('auth.invite_only');
        if ($inviteRequired) {
            if ($invite === '' || !Invite::isValid($invite)) $errors[] = 'invalid_invite';
        }

        if ($errors) {
            $_SESSION['flash_register'] = $errors;
            $_SESSION['flash_register_data'] = ['username' => $username, 'email' => $email, 'invite' => $invite];
            redirect('register?error=' . urlencode($errors[0]));
            return;
        }

        $needsVerify = Settings::getBool('auth.email_verify');
        $needsReview = Settings::getBool('auth.register_review');

        $userId = User::create([
            'username'       => $username,
            'email'          => $email,
            'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
            'display_name'   => $username,
            'role'           => (string)Settings::get('auth.default_role', 'user'),
            'status'         => $needsReview ? 'pending' : 'active',
            'email_verified' => $needsVerify ? 0 : 1,
        ]);

        if ($inviteRequired) Invite::consume($invite, $userId);

        AuditLog::log('auth.register', 'user', $userId, ['username' => $username]);

        if ($needsVerify) {
            $this->sendVerifyMail($userId);
            redirect('verify?sent=1');
            return;
        }

        if ($needsReview) {
            redirect('login?error=pending');
            return;
        }

        Auth::login($username, $password);
        redirect('');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('');
    }

    public function verifyNotice(): void
    {
        $sent = !empty($_GET['sent']);
        $pending = !empty($_GET['pending']);
        $done = !empty($_GET['done']);
        $error = $_GET['error'] ?? '';
        require WIKI_ROOT . '/templates/auth/verify.php';
    }

    public function verify(string $token): void
    {
        $row = EmailToken::findValid($token, 'verify');
        if (!$row) { redirect('verify?error=invalid'); return; }

        User::update((int)$row['user_id'], ['email_verified' => 1, 'status' => 'active']);
        EmailToken::markUsed((int)$row['id']);
        AuditLog::log('auth.verify', 'user', (int)$row['user_id']);

        $user = User::find((int)$row['user_id']);
        if ($user) {
            $_SESSION['uid'] = (int)$user['id'];
        }
        redirect('verify?done=1');
    }

    public function resendVerify(): void
    {
        $u = Auth::user();
        if (!$u) { redirect('login'); return; }
        $this->sendVerifyMail((int)$u['id']);
        redirect('verify?sent=1');
    }

    public function forgotForm(): void
    {
        $sent = !empty($_GET['sent']);
        $error = $_GET['error'] ?? '';
        require WIKI_ROOT . '/templates/auth/forgot.php';
    }

    public function forgot(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { redirect('forgot?error=csrf'); return; }

        $email = trim((string)($_POST['email'] ?? ''));
        $user = User::findByEmail($email);
        if ($user) {
            $token = EmailToken::create((int)$user['id'], 'reset', 3600);
            $this->sendResetMail((int)$user['id'], $token);
        }
        redirect('forgot?sent=1');
    }

    public function resetForm(string $token): void
    {
        $row = EmailToken::findValid($token, 'reset');
        if (!$row) { redirect('login?error=invalid_token'); return; }
        $error = $_GET['error'] ?? '';
        require WIKI_ROOT . '/templates/auth/reset.php';
    }

    public function reset(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { redirect('login?error=csrf'); return; }

        $token = (string)($_POST['token'] ?? '');
        $pass1 = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');

        if ($pass1 !== $pass2 || strlen($pass1) < 6) {
            redirect('reset/' . urlencode($token) . '?error=weak');
            return;
        }

        $row = EmailToken::findValid($token, 'reset');
        if (!$row) { redirect('login?error=invalid_token'); return; }

        User::update((int)$row['user_id'], [
            'password_hash' => password_hash($pass1, PASSWORD_DEFAULT),
        ]);
        EmailToken::markUsed((int)$row['id']);
        AuditLog::log('auth.reset', 'user', (int)$row['user_id']);
        redirect('login?reset=1');
    }

    public function githubStart(): void
    {
        if (!GitHubOAuth::enabled()) { http_response_code(404); exit(); }
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        header('Location: ' . GitHubOAuth::authorizeUrl($state));
        exit;
    }

    public function githubCallback(): void
    {
        if (!GitHubOAuth::enabled()) { http_response_code(404); exit(); }
        $state = (string)($_GET['state'] ?? '');
        $code  = (string)($_GET['code'] ?? '');

        if ($state === '' || $state !== ($_SESSION['oauth_state'] ?? '')) {
            redirect('login?error=oauth_state');
            return;
        }
        unset($_SESSION['oauth_state']);

        $accessToken = GitHubOAuth::exchangeCode($code);
        if (!$accessToken) { redirect('login?error=oauth_token'); return; }

        $ghUser = GitHubOAuth::fetchUser($accessToken);
        if (!$ghUser) { redirect('login?error=oauth_user'); return; }

        $oauthId = (string)$ghUser['id'];
        $user = User::findByOAuth('github', $oauthId);

        if (!$user) {
            $email = (string)($ghUser['email'] ?? ($oauthId . '@github.local'));
            $existing = User::findByEmail($email);
            if ($existing) {
                User::update((int)$existing['id'], [
                    'oauth_provider' => 'github',
                    'oauth_id'       => $oauthId,
                    'avatar_url'     => (string)($ghUser['avatar_url'] ?? ''),
                ]);
                $user = User::find((int)$existing['id']);
            } else {
                if (!Settings::getBool('auth.allow_register') && !Settings::getBool('auth.invite_only')) {
                    redirect('login?error=register_disabled');
                    return;
                }
                $username = User::generateUsername($email, 'gh_' . substr($oauthId, -4));
                $id = User::create([
                    'username'       => $username,
                    'email'          => $email,
                    'password_hash'  => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'display_name'   => (string)($ghUser['name'] ?? $username),
                    'avatar_url'     => (string)($ghUser['avatar_url'] ?? ''),
                    'role'           => (string)Settings::get('auth.default_role', 'user'),
                    'email_verified' => 1,
                    'oauth_provider' => 'github',
                    'oauth_id'       => $oauthId,
                ]);
                $user = User::find($id);
            }
        }

        if (!$user || $user['status'] !== 'active') {
            redirect('login?error=account_inactive');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        AuditLog::log('auth.login', 'user', (int)$user['id'], ['via' => 'github']);
        redirect('');
    }

    // Profile

    public function profileForm(): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        $user = Auth::user();
        require WIKI_ROOT . '/templates/profile.php';
    }

    public function profileUpdate(): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('auth.error.csrf')];
            redirect('profile');
            return;
        }

        $u = Auth::user();
        $email = trim((string)($_POST['email'] ?? ''));
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $avatar = trim((string)($_POST['avatar_url'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('auth.error.invalid_email')];
            redirect('profile');
            return;
        }
        $existing = User::findByEmail($email);
        if ($existing && (int)$existing['id'] !== (int)$u['id']) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('auth.error.email_taken')];
            redirect('profile');
            return;
        }

        $updates = [
            'email'        => $email,
            'display_name' => $displayName,
            'avatar_url'   => $avatar,
        ];

        $needsVerify = Settings::getBool('auth.email_verify');
        if ($needsVerify && $email !== $u['email']) {
            $updates['email_verified'] = 0;
        }

        User::update((int)$u['id'], $updates);
        AuditLog::log('profile.update', 'user', (int)$u['id']);
        $_SESSION['flash_profile'] = ['type' => 'success', 'text' => I18nService::t('profile.saved')];
        redirect('profile');
    }

    public function profilePassword(): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('auth.error.csrf')];
            redirect('profile?tab=password');
            return;
        }

        $u = Auth::user();
        $current = (string)($_POST['current'] ?? '');
        $pass1   = (string)($_POST['password'] ?? '');
        $pass2   = (string)($_POST['password2'] ?? '');

        if (!password_verify($current, (string)$u['password_hash'])) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('profile.wrong_password')];
            redirect('profile?tab=password');
            return;
        }
        if ($pass1 !== $pass2 || strlen($pass1) < 6) {
            $_SESSION['flash_profile'] = ['type' => 'error', 'text' => I18nService::t('auth.error.weak_password')];
            redirect('profile?tab=password');
            return;
        }

        User::update((int)$u['id'], ['password_hash' => password_hash($pass1, PASSWORD_DEFAULT)]);
        AuditLog::log('profile.password', 'user', (int)$u['id']);
        $_SESSION['flash_profile'] = ['type' => 'success', 'text' => I18nService::t('profile.password_saved')];
        redirect('profile?tab=password');
    }

    private function sendVerifyMail(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $token = EmailToken::create($userId, 'verify', 86400);
        $link  = url('verify/' . $token);

        $subject = MailService::render(
            (string)Settings::get('mail.tpl.verify_subject', '[{site_name}] Verify your email')
        );
        $body = MailService::render(
            (string)Settings::get('mail.tpl.verify_body',
                "Hi {username},\n\nPlease verify your email by visiting:\n{link}\n\nThis link expires in 24 hours.\n\n{site_name}"),
            ['username' => (string)$user['username'], 'link' => $link]
        );

        MailService::send((string)$user['email'], $subject, $body);
    }

    private function sendResetMail(int $userId, string $token): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $link = url('reset/' . $token);
        $subject = MailService::render(
            (string)Settings::get('mail.tpl.reset_subject', '[{site_name}] Password reset')
        );
        $body = MailService::render(
            (string)Settings::get('mail.tpl.reset_body',
                "Hi {username},\n\nReset your password by visiting:\n{link}\n\nThis link expires in 1 hour.\n\n{site_name}"),
            ['username' => (string)$user['username'], 'link' => $link]
        );

        MailService::send((string)$user['email'], $subject, $body);
    }
}
