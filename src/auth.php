<?php
declare(strict_types=1);

const ROLE_LABELS = [
    'MARKETING' => 'Marketing',
    'ATASAN' => 'Atasan Marketing',
    'ADMIN_BO' => 'Admin Backoffice',
    'DEALER' => 'Dealer',
];

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $row = q('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']])->fetch();
            $user = $row ?: null;
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }
    return $user;
}

function require_role(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render('error', ['title' => 'Akses ditolak', 'message' => 'Halaman ini tidak tersedia untuk peran Anda.']);
        exit;
    }
}

function attempt_login(string $username, string $password): bool
{
    $row = q('SELECT * FROM users WHERE username = ? AND is_active = 1', [$username])->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    return true;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        render('error', ['title' => 'Sesi kedaluwarsa', 'message' => 'Muat ulang halaman, lalu coba lagi.']);
        exit;
    }
}

/** Who may open an application at all. */
function can_view_application(array $user, array $app): bool
{
    return match ($user['role']) {
        'MARKETING' => (int) $app['marketing_id'] === (int) $user['id'],
        'ATASAN' => is_supervisor_of($user, (int) $app['marketing_id']),
        'ADMIN_BO' => true,
        default => false,
    };
}

function is_supervisor_of(array $user, int $marketingId): bool
{
    $sup = q('SELECT supervisor_id FROM users WHERE id = ?', [$marketingId])->fetchColumn();
    return $sup !== false && (int) $sup === (int) $user['id'];
}
