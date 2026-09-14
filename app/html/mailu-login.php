<?php
declare(strict_types=1);

/**
 * Mailu-Login-Skript, das Authelia-Authentifizierung übernimmt und
 * die gewünschte Mailbox per Cookie an den Mailu-Container weitergibt.
 * Nicht über normalen Router, da es zeitkritisch ist (Middleware bei
 * jedem Request an Mailu) und keine Session benötigt.
 *
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

function base64urlencode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64urldecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function verifyToken(string $token): ?array {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null; // invalid format
    [$body, $sig] = $parts;
    $expected = base64urlencode(hash_hmac('sha256', $body, getenv('TOKEN_KEY'), true));
    if (!hash_equals($expected, $sig)) return null; // manipulated
    $payload = json_decode(base64urldecode($body), true);
    if (!is_array($payload) || !isset($payload['exp'], $payload['sub'])) return null; // missing keys
    if ($payload['exp'] < time()) return null; // expired
    return $payload;
}

const TOKEN_COOKIE_NAME   = 'mailbox-token';
const TOKEN_QUERY_PARAM   = 'mbx';


$username = $_SERVER['HTTP_REMOTE_USER'] ?? '';
if ($username === '') {
    http_response_code(401);
    echo 'Keine Identität von Authelia erhalten.';
    exit;
}

$uri    = $_SERVER['HTTP_X_FORWARDED_URI'] ?? '/';
$currentUrl = 'https://mail.' . getenv('DOMAINNAME') . $uri;

parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);

function redirect_to_selector(string $currentUrl): void {
    http_response_code(302);
    $selectorUrl = 'https://mitglieder.' . getenv('DOMAINNAME') . '/mail-login';
    header('Location: ' . $selectorUrl . '?redirect=' . urlencode($currentUrl));
    exit;
}

// Token einsammeln: Querystring hat Vorrang vor Cookie
$rawToken  = null;
$fromQuery = false;
if (!empty($params[TOKEN_QUERY_PARAM])) {
    $rawToken  = $params[TOKEN_QUERY_PARAM];
    $fromQuery = true;
} elseif (!empty($_COOKIE[TOKEN_COOKIE_NAME])) {
    $rawToken = $_COOKIE[TOKEN_COOKIE_NAME];
}

// Kein Token vorhanden -> externer Mailbox-Selector
if ($rawToken === null) {
    redirect_to_selector($currentUrl);
}

// Signatur + Ablauf prüfen (verify_token gibt bei Ablauf ebenfalls null zurück)
$payload = verifyToken($rawToken);
if ($payload === null || !isset($payload['mailbox'])) {
    // ungültig ODER abgelaufen -> zurück zur Auswahl statt Fehlermeldung
    redirect_to_selector($currentUrl);
}

// Token muss für die aktuell eingeloggte Person ausgestellt worden sein
if ($payload['sub'] !== $username) {
    http_response_code(403);
    echo 'Dieser Token gehört zu einer anderen Identität.';
    exit;
}

// Token aus der URL zusätzlich als Cookie merken (für Folge-Requests)
if ($fromQuery) {
    setcookie(TOKEN_COOKIE_NAME, $rawToken, [
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

http_response_code(200);
header('Remote-Email: ' . $payload['mailbox']);
