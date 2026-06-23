<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Synchronisiert Namen und E-Mail-Adressen der Mitglieder mit einer ListMonk-Instanz.
 *
 * Quelle der Wahrheit sind die Mitgliederdaten (per Ldap::getAll() ermittelt und vom Controller
 * hereingereicht); ListMonk wird abgeglichen. Dieser Service kennt das LDAP selbst nicht.
 */
class Listmonk
{
    public function __construct(
        private string $baseUrl,
        private string $apiUser,
        private string $apiToken,
        private int $listId,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * Ist die Anbindung vollständig konfiguriert? Ohne Konfiguration bleibt das Feature inaktiv.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiUser !== '' && $this->apiToken !== '' && $this->listId > 0;
    }

    /**
     * Gleicht die übergebenen Mitglieder mit der Ziel-Liste in ListMonk ab.
     *
     * @param array $members Liste von ['id', 'username', 'firstname', 'lastname', 'email'] (Ldap::getAll())
     * @return array{total:int,created:int,updated:int,removed:int,skipped:int,errors:string[]}
     * @throws \RuntimeException wenn ListMonk nicht konfiguriert ist
     */
    public function sync(array $members): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('ListMonk ist nicht konfiguriert (LISTMONK_URL, LISTMONK_USER, LISTMONK_TOKEN, LISTMONK_LIST_ID).', 1750000001);
        }

        $created = $updated = $removed = $skipped = 0;
        $errors = [];

        // 1. Soll-Zustand aus den Mitgliedern bilden (.invalid-Adressen überspringen)
        $desired = [];
        foreach ($members as $member) {
            $email = trim((string) ($member['email'] ?? ''));
            if ($email === '' || preg_match('/\.invalid$/i', $email)) {
                $skipped++;
                continue;
            }
            $desired[mb_strtolower($email)] = [
                'email' => $email,
                'name' => trim(($member['firstname'] ?? '') . ' ' . ($member['lastname'] ?? '')),
                'attribs' => [
                    'mitgliedsnummer' => (int) ($member['id'] ?? 0),
                    'vorname' => (string) ($member['firstname'] ?? ''),
                    'nachname' => (string) ($member['lastname'] ?? ''),
                ],
            ];
        }

        // 2. Ist-Zustand: aktuelle Abonnenten der Ziel-Liste
        $current = $this->getListSubscribers();

        // 3. Anlegen / aktualisieren
        foreach ($desired as $key => $entry) {
            try {
                if (isset($current[$key])) {
                    if ($this->needsUpdate($current[$key], $entry)) {
                        $this->updateSubscriber($current[$key], $entry, $this->listIdsOf($current[$key]));
                        $updated++;
                    }
                } else {
                    $this->createOrAdd($entry);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = $entry['email'] . ': ' . $e->getMessage();
            }
        }

        // 4. Voller Abgleich: Abonnenten der Liste entfernen, die kein aktuelles Mitglied (mehr) sind
        $removeIds = [];
        foreach ($current as $key => $subscriber) {
            if (!isset($desired[$key])) {
                $removeIds[] = (int) $subscriber['id'];
            }
        }
        if ($removeIds) {
            try {
                $this->removeFromList($removeIds);
                $removed = count($removeIds);
            } catch (\Throwable $e) {
                $errors[] = 'Entfernen aus der Liste fehlgeschlagen: ' . $e->getMessage();
            }
        }

        return [
            'total' => count($desired),
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Alle Abonnenten der Ziel-Liste, paginiert geladen.
     *
     * @return array<string,array> strtolower(email) => Abonnenten-Objekt von ListMonk
     */
    private function getListSubscribers(): array
    {
        $subscribers = [];
        $page = 1;
        $perPage = 1000;
        do {
            $query = http_build_query([
                'list_id' => $this->listId,
                'page' => $page,
                'per_page' => $perPage,
            ]);
            [$status, $body] = $this->request('GET', '/api/subscribers?' . $query);
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Konnte Abonnenten nicht laden (HTTP ' . $status . ').', 1750000002);
            }
            $results = $body['data']['results'] ?? [];
            foreach ($results as $subscriber) {
                $email = trim((string) ($subscriber['email'] ?? ''));
                if ($email !== '') {
                    $subscribers[mb_strtolower($email)] = $subscriber;
                }
            }
            $total = (int) ($body['data']['total'] ?? 0);
            $page++;
        } while ($results && count($subscribers) < $total);

        return $subscribers;
    }

    /**
     * Legt einen Abonnenten an. Existiert die E-Mail bereits global (nur nicht in dieser Liste),
     * wird der Abonnent gesucht und der Ziel-Liste hinzugefügt (Status bleibt erhalten).
     */
    private function createOrAdd(array $entry): void
    {
        [$status, $body] = $this->request('POST', '/api/subscribers', [
            'email' => $entry['email'],
            'name' => $entry['name'],
            'status' => 'enabled',
            'lists' => [$this->listId],
            'preconfirm_subscriptions' => true,
            'attribs' => $entry['attribs'],
        ]);

        if ($status >= 200 && $status < 300) {
            return;
        }

        // E-Mail existiert bereits: Abonnent suchen und der Liste hinzufügen
        $existing = $this->findByEmail($entry['email']);
        if ($existing === null) {
            $message = $body['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Anlegen fehlgeschlagen: ' . $message, 1750000003);
        }

        $listIds = $this->listIdsOf($existing);
        if (!in_array($this->listId, $listIds, true)) {
            $listIds[] = $this->listId;
        }
        $this->updateSubscriber($existing, $entry, $listIds);
    }

    /**
     * Aktualisiert Name/Attribute eines Abonnenten. Listenmitgliedschaften und Status werden bewusst
     * mitgegeben, da PUT in ListMonk alle Felder überschreibt.
     *
     * @param int[] $listIds beizubehaltende/zu setzende Listen-IDs
     */
    private function updateSubscriber(array $subscriber, array $entry, array $listIds): void
    {
        $id = (int) $subscriber['id'];
        $attribs = array_merge(is_array($subscriber['attribs'] ?? null) ? $subscriber['attribs'] : [], $entry['attribs']);

        [$status, $body] = $this->request('PUT', '/api/subscribers/' . $id, [
            'email' => $entry['email'],
            'name' => $entry['name'],
            'status' => $subscriber['status'] ?? 'enabled',
            'lists' => array_values($listIds),
            'preconfirm_subscriptions' => true,
            'attribs' => $attribs,
        ]);

        if ($status < 200 || $status >= 300) {
            $message = $body['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Aktualisieren fehlgeschlagen: ' . $message, 1750000004);
        }
    }

    /**
     * Entfernt Abonnenten aus der Ziel-Liste (löscht sie nicht global, andere Listen bleiben erhalten).
     *
     * @param int[] $ids
     */
    private function removeFromList(array $ids): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$status, $body] = $this->request('PUT', '/api/subscribers/lists', [
                'ids' => array_values($chunk),
                'action' => 'remove',
                'target_list_ids' => [$this->listId],
            ]);
            if ($status < 200 || $status >= 300) {
                $message = $body['message'] ?? ('HTTP ' . $status);
                throw new \RuntimeException($message, 1750000005);
            }
        }
    }

    /**
     * Sucht einen Abonnenten anhand seiner E-Mail-Adresse.
     */
    private function findByEmail(string $email): ?array
    {
        $query = http_build_query([
            'query' => "subscribers.email = '" . str_replace("'", "''", $email) . "'",
            'per_page' => 1,
        ]);
        [$status, $body] = $this->request('GET', '/api/subscribers?' . $query);
        if ($status < 200 || $status >= 300) {
            return null;
        }
        return $body['data']['results'][0] ?? null;
    }

    /**
     * Muss der Abonnent aktualisiert werden (Name oder relevante Attribute weichen ab)?
     */
    private function needsUpdate(array $subscriber, array $entry): bool
    {
        if (($subscriber['name'] ?? '') !== $entry['name']) {
            return true;
        }
        $attribs = is_array($subscriber['attribs'] ?? null) ? $subscriber['attribs'] : [];
        foreach ($entry['attribs'] as $key => $value) {
            if (!array_key_exists($key, $attribs) || (string) $attribs[$key] !== (string) $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Listen-IDs eines Abonnenten-Objekts.
     *
     * @return int[]
     */
    private function listIdsOf(array $subscriber): array
    {
        $lists = $subscriber['lists'] ?? [];
        if (!is_array($lists)) {
            return [];
        }
        return array_values(array_map(fn($list) => (int) ($list['id'] ?? 0), $lists));
    }

    /**
     * Führt einen ListMonk-API-Aufruf aus.
     *
     * @return array{0:int,1:array} [HTTP-Statuscode, dekodierter Body]
     * @throws \RuntimeException bei Transportfehlern
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $headers = ['Accept: application/json'];
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiUser . ':' . $this->apiToken);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('ListMonk nicht erreichbar: ' . $error, 1750000006);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $response, true);
        return [$status, is_array($decoded) ? $decoded : []];
    }
}
