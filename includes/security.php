<?php
// includes/security.php — anti-XSS + security headers + JSON helper
declare(strict_types=1);

function clean_input(?string $s, int $maxLen = 255): string {
    $s = trim((string)$s);
    $s = strip_tags($s);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    return mb_substr($s, 0, $maxLen);
}

function clean_url(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (strlen($url) > 512) return null;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) return null;
    $url = str_replace(["\r", "\n", "\0"], '', $url);
    return $url;
}

function clean_email(?string $email): ?string {
    $email = trim((string)$email);
    $email = str_replace(["\r", "\n"], '', $email);
    if ($email === '' || strlen($email) > 255) return null;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
}

function clean_ip(?string $ip): ?string {
    $ip = trim((string)$ip);
    if ($ip === '') return null;
    return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
}

function send_html_security_headers(): void {
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "img-src 'self' data: https:; "
        . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "script-src 'self' 'unsafe-inline'; "
        . "font-src 'self' data: https://cdnjs.cloudflare.com; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self';"
    );
}

function send_json_security_headers(): void {
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Content-Type: application/json; charset=utf-8');
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}