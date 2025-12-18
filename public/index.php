<?php

declare(strict_types=1);

// If this file is used as the PHP built-in server router, let it serve static files directly.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $file = __DIR__ . $requested;
    if ($requested !== '/' && is_file($file)) {
        return false;
    }
}

/**
 * Basic env toggle.
 * Set `APP_ENV=development` locally for detailed error pages.
 */
$appEnv = getenv('APP_ENV') ?: 'production';
$debug = $appEnv !== 'production';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');

/**
 * @param mixed $value
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');

    // Keep CSP simple and local-dev friendly (no forced HTTPS upgrades).
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self'"
    );
}

function send_text(string $text, int $status = 200): void
{
    http_response_code($status);
    security_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
}

/**
 * @param array<string,mixed> $data
 */
function send_json(array $data, int $status = 200): void
{
    http_response_code($status);
    security_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_starts_with(current_path(), '/api/') || str_contains($accept, 'application/json');
}

function nav_link(string $href, string $label): string
{
    $isActive = current_path() === $href;
    $active = $isActive ? ' aria-current="page"' : '';
    return '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
}

function layout(string $title, string $contentHtml, int $status = 200): void
{
    http_response_code($status);
    security_headers();
    header('Content-Type: text/html; charset=utf-8');

    $fullTitle = $title === '' ? 'PHP on Render' : $title . ' · PHP on Render';

    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<link rel="icon" href="/favicon.svg" type="image/svg+xml" />';
    echo '<link rel="stylesheet" href="/assets/site.css" />';
    echo '<script defer src="/assets/site.js"></script>';
    echo '<title>' . e($fullTitle) . '</title>';
    echo '</head>';
    echo '<body>';
    echo '<a class="skip-link" href="#main">Skip to content</a>';
    echo '<header class="site-header">';
    echo '  <div class="container header-inner">';
    echo '    <div class="brand"><a href="/">PHP on Render</a></div>';
    echo '    <nav class="nav">';
    echo '      ' . nav_link('/', 'Home');
    echo '      ' . nav_link('/about', 'About');
    echo '      ' . nav_link('/request', 'Request');
    echo '      ' . nav_link('/contact', 'Contact');
    echo '    </nav>';
    echo '    <button class="theme-toggle" type="button" data-theme-toggle>Theme</button>';
    echo '  </div>';
    echo '</header>';

    echo '<main id="main" class="container site-main">';
    echo $contentHtml;
    echo '</main>';

    echo '<footer class="site-footer">';
    echo '  <div class="container footer-inner">';
    echo '    <div>Endpoints: <a href="/healthz">/healthz</a> · <a href="/api/time">/api/time</a></div>';
    echo '    <div class="muted">UTC: <code>' . e(utc_now()) . '</code></div>';
    echo '  </div>';
    echo '</footer>';
    echo '</body>';
    echo '</html>';
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if ($path === '') {
        return '/';
    }
    return $path;
}

function utc_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

/**
 * @return array<string,string>
 */
function request_headers(): array
{
    if (function_exists('getallheaders')) {
        /** @var array<string,string> $headers */
        $headers = getallheaders();
        ksort($headers);
        return $headers;
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (!is_string($value) || !str_starts_with($key, 'HTTP_')) {
            continue;
        }
        $name = str_replace('_', '-', strtolower(substr($key, 5)));
        $name = implode('-', array_map('ucfirst', explode('-', $name)));
        $headers[$name] = $value;
    }
    ksort($headers);
    return $headers;
}

function redirect(string $to, int $status = 302): void
{
    http_response_code($status);
    security_headers();
    header('Location: ' . $to);
    exit;
}

function ensure_trailing_slash_policy(string $path): void
{
    if ($path !== '/' && str_ends_with($path, '/')) {
        redirect(rtrim($path, '/'), 301);
    }
}

set_exception_handler(static function (Throwable $e) use ($debug): void {
    if (headers_sent()) {
        return;
    }

    error_log((string) $e);

    if (wants_json()) {
        $payload = ['error' => 'internal_error'];
        if ($debug) {
            $payload['message'] = $e->getMessage();
            $payload['type'] = $e::class;
        }
        send_json($payload, 500);
        exit;
    }

    $content = '';
    $content .= '<h1>Something went wrong</h1>';
    $content .= '<p class="muted">An unexpected error occurred.</p>';
    if ($debug) {
        $content .= '<section class="card"><h2>Debug</h2>';
        $content .= '<p><code>' . e($e::class) . '</code>: <code>' . e($e->getMessage()) . '</code></p>';
        $content .= '</section>';
    } else {
        $content .= '<p><a class="btn" href="/">Back to home</a></p>';
    }
    layout('Error', $content, 500);
    exit;
});

$fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
register_shutdown_function(static function () use ($debug, $fatalTypes): void {
    $last = error_get_last();
    if ($last === null || (($last['type'] ?? 0) & $fatalTypes) === 0) {
        return;
    }
    if (headers_sent()) {
        return;
    }

    $message = is_string($last['message'] ?? null) ? $last['message'] : 'Fatal error';

    if (wants_json()) {
        $payload = ['error' => 'fatal_error'];
        if ($debug) {
            $payload['message'] = $message;
        }
        send_json($payload, 500);
        return;
    }

    $content = '<h1>Something went wrong</h1><p class="muted">A fatal error occurred.</p>';
    if ($debug) {
        $content .= '<section class="card"><h2>Debug</h2><p><code>' . e($message) . '</code></p></section>';
    }
    layout('Error', $content, 500);
});

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = current_path();
ensure_trailing_slash_policy($path);

if ($path === '/healthz') {
    send_text("ok\n");
    exit;
}

if ($path === '/api/time') {
    if ($method !== 'GET') {
        send_json(['error' => 'method_not_allowed'], 405);
        exit;
    }

    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    send_json([
        'utc' => utc_now(),
        'unix' => time(),
        'php' => PHP_VERSION,
        'request_id' => is_string($requestId) ? $requestId : null,
    ]);
    exit;
}

if ($path === '/contact') {
    session_start();

    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    $flash = null;
    if (isset($_SESSION['flash']) && is_string($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
    }

    if ($method === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            $_SESSION['flash'] = 'Security check failed. Please try again.';
            redirect('/contact', 303);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($name === '' || $message === '') {
            $_SESSION['flash'] = 'Please fill in both fields.';
            redirect('/contact', 303);
        }

        $_SESSION['flash'] = 'Thanks, ' . $name . '! Your message was received (not sent anywhere).';
        redirect('/contact', 303);
    }

    $content = '';
    $content .= '<h1>Contact</h1>';
    $content .= '<p class="muted">This demo form validates input and uses CSRF + PRG, but does not send email.</p>';
    if ($flash !== null) {
        $content .= '<div class="notice" role="status">' . e($flash) . '</div>';
    }
    $content .= '<form class="card form" method="post" action="/contact">';
    $content .= '  <input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '" />';
    $content .= '  <label> Name <input name="name" autocomplete="name" required /></label>';
    $content .= '  <label> Message <textarea name="message" rows="5" required></textarea></label>';
    $content .= '  <div class="form-actions"><button class="btn" type="submit">Send</button></div>';
    $content .= '</form>';
    layout('Contact', $content);
    exit;
}

if ($path === '/about') {
    $content = '';
    $content .= '<h1>About</h1>';
    $content .= '<div class="grid">';
    $content .= '  <section class="card">';
    $content .= '    <h2>Runtime</h2>';
    $content .= '    <ul class="list">';
    $content .= '      <li><span class="k">PHP</span><span class="v"><code>' . e(PHP_VERSION) . '</code></span></li>';
    $content .= '      <li><span class="k">SAPI</span><span class="v"><code>' . e(PHP_SAPI) . '</code></span></li>';
    $content .= '      <li><span class="k">Server</span><span class="v"><code>' . e($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . '</code></span></li>';
    $content .= '    </ul>';
    $content .= '  </section>';
    $content .= '  <section class="card">';
    $content .= '    <h2>Next steps</h2>';
    $content .= '    <p>Add a database, Composer deps, or a framework—this project stays intentionally lightweight.</p>';
    $content .= '    <p>Try the JSON endpoint: <a href="/api/time"><code>/api/time</code></a></p>';
    $content .= '  </section>';
    $content .= '</div>';
    layout('About', $content);
    exit;
}

if ($path === '/request') {
    $content = '';
    $content .= '<h1>Request</h1>';
    $content .= '<p class="muted">Useful for debugging headers when deployed behind a proxy.</p>';

    $content .= '<section class="card">';
    $content .= '  <h2>Summary</h2>';
    $content .= '  <ul class="list">';
    $content .= '    <li><span class="k">Method</span><span class="v"><code>' . e($method) . '</code></span></li>';
    $content .= '    <li><span class="k">Path</span><span class="v"><code>' . e($path) . '</code></span></li>';
    $content .= '    <li><span class="k">Query</span><span class="v"><code>' . e($_SERVER['QUERY_STRING'] ?? '') . '</code></span></li>';
    $content .= '    <li><span class="k">Remote</span><span class="v"><code>' . e($_SERVER['REMOTE_ADDR'] ?? '') . '</code></span></li>';
    $content .= '    <li><span class="k">Forwarded</span><span class="v"><code>' . e($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '') . '</code></span></li>';
    $content .= '  </ul>';
    $content .= '</section>';

    $content .= '<section class="card">';
    $content .= '  <h2>Headers</h2>';
    $content .= '  <div class="table-wrap">';
    $content .= '  <table class="table"><thead><tr><th>Header</th><th>Value</th></tr></thead><tbody>';
    foreach (request_headers() as $name => $value) {
        $content .= '<tr><td><code>' . e($name) . '</code></td><td><code>' . e($value) . '</code></td></tr>';
    }
    $content .= '  </tbody></table>';
    $content .= '  </div>';
    $content .= '</section>';

    layout('Request', $content);
    exit;
}

if ($path === '/') {
    $content = '';
    $content .= '<section class="hero">';
    $content .= '  <h1>Deployed PHP app</h1>';
    $content .= '  <p class="lead">A tiny, fast PHP site with a few useful routes and a clean UI.</p>';
    $content .= '  <div class="hero-actions">';
    $content .= '    <a class="btn" href="/about">Learn more</a>';
    $content .= '    <a class="btn btn-secondary" href="/api/time">View JSON</a>';
    $content .= '  </div>';
    $content .= '</section>';

    $content .= '<div class="grid">';
    $content .= '  <section class="card">';
    $content .= '    <h2>Health</h2>';
    $content .= '    <p>Simple uptime check for load balancers and monitors.</p>';
    $content .= '    <p><a href="/healthz"><code>/healthz</code></a></p>';
    $content .= '  </section>';
    $content .= '  <section class="card">';
    $content .= '    <h2>Debug</h2>';
    $content .= '    <p>See method, IP, and request headers (escaped).</p>';
    $content .= '    <p><a href="/request"><code>/request</code></a></p>';
    $content .= '  </section>';
    $content .= '  <section class="card">';
    $content .= '    <h2>Contact</h2>';
    $content .= '    <p>Demo form with CSRF protection and PRG.</p>';
    $content .= '    <p><a href="/contact"><code>/contact</code></a></p>';
    $content .= '  </section>';
    $content .= '</div>';

    layout('', $content);
    exit;
}

if ($method !== 'GET') {
    send_text("method not allowed\n", 405);
    exit;
}

$content = '';
$content .= '<h1>Not found</h1>';
$content .= '<p class="muted">No route for <code>' . e($path) . '</code>.</p>';
$content .= '<p><a class="btn" href="/">Back to home</a></p>';
layout('Not found', $content, 404);
exit;
