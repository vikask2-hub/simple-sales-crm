<?php

declare(strict_types=1);

const CRM_ROOT = __DIR__.'/..';

$externalConfig = getenv('CRM_CONFIG_FILE') ?: '';
if (is_file($externalConfig)) {
    $config = require $externalConfig;
} else {
    $config = [
        'dsn' => getenv('CRM_DSN') ?: 'sqlite:'.CRM_ROOT.'/database/local.sqlite',
        'username' => getenv('CRM_DB_USERNAME') ?: null,
        'password' => getenv('CRM_DB_PASSWORD') ?: null,
        'base_url' => getenv('CRM_BASE_URL') ?: '/crm',
        'demo_mode' => filter_var(getenv('CRM_DEMO_MODE') ?: 'true', FILTER_VALIDATE_BOOL),
    ];
}

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/Crm.php';
require_once __DIR__.'/View.php';

date_default_timezone_set('Asia/Kolkata');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('tech4projects_crm');
    session_start();
}

$database = new Database($config);
$auth = new Auth($database->pdo());
$crm = new Crm($database->pdo(), $auth);
$baseUrl = rtrim((string) ($config['base_url'] ?? '/crm'), '/');

function url(string $path = ''): string
{
    global $baseUrl;

    return $baseUrl.'/'.ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || ! hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: '.url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = compact('type', 'message');
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function validation_error(string $key): ?string
{
    return $_SESSION['errors'][$key] ?? null;
}

function remember_form(array $input, array $errors, string $path): never
{
    unset($input['password'], $input['password_confirmation'], $input['csrf_token']);
    $_SESSION['old'] = $input;
    $_SESSION['errors'] = $errors;
    redirect($path);
}
