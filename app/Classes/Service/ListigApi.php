<?php
declare(strict_types=1);
namespace App\Service;

/**
 * Ruft die List-Management-API von Listig auf (siehe Listig-CLAUDE.md "List Management API").
 * Verwendet aktuell nur den encrypt-password-Endpoint: Listig verschlüsselt das übergebene
 * Passwort mit seinem eigenen APP_SECRET und schreibt es selbst direkt als mail-password
 * in den LDAP-Eintrag der Liste - das Chiffrat wird nicht im Response zurückgegeben.
 */
class ListigApi
{
    public function __construct(
        private string $baseUrl,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function encryptPassword(string $listName, string $apiToken, string $password): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Listig ist nicht konfiguriert (LISTIG_URL).', 1753900001);
        }

        [$status, $body] = $this->request('POST', '/' . rawurlencode($listName) . '/encrypt-password', $apiToken, [
            'password' => $password,
        ]);

        // Listig prüft das Passwort per IMAP-Login, bevor es verschlüsselt und gespeichert wird
        // (siehe CLAUDE.md "Password verification") und antwortet bei einer Ablehnung mit 422 -
        // eigener Exception-Typ, damit der Aufrufer das von anderen Fehlern unterscheiden kann.
        if ($status === 422) {
            throw new ListigPasswordRejectedException($body['error'] ?? 'Das Passwort wurde vom Mailserver abgelehnt.', 1753900004);
        }

        if ($status < 200 || $status >= 300) {
            $message = $body['error'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Verschlüsseln des Passworts fehlgeschlagen: ' . $message, 1753900002);
        }
    }

    /**
     * @return array{0:int,1:array} [HTTP-Statuscode, dekodierter Body]
     */
    private function request(string $method, string $path, string $bearerToken, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $bearerToken];
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Listig nicht erreichbar: ' . $error, 1753900003);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = $response !== '' ? json_decode($response, true) : null;
        return [$status, is_array($decoded) ? $decoded : []];
    }
}
