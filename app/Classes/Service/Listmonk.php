<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\MailAddress;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Anbindung an eine ListMonk-Instanz mit zwei Listen:
 *
 * - Mitglieder-Liste: Alle Mitglieder. LDAP hat immer recht; aus Listmonk wird nie etwas ins LDAP zurückgeschrieben.
 * - Newsletter: LDAP-Gruppe „newsletter“. Zusätzlich werden Abmeldungen in ListMonk gemeldet, damit der
 *   Aufrufer sie aus der Gruppe entfernen kann (syncNewsletter()).
 *
 * Abonnent*innen werden über das Attribut `mitgliedsnummer` zugeordnet, nicht über die E-Mail-Adresse,
 * damit eine geänderte Adresse den bestehenden Abonnenten (samt Abmelde-Status) aktualisiert.
 *
 * Dieser Service kennt das LDAP nicht. Mitglieder werden als Arrays übergeben:
 * ['id' => Mitgliedsnummer, 'firstname', 'lastname', 'email' => die zu verwendende Adresse].
 */
class Listmonk
{
    public function __construct(
        private string $baseUrl,
        private string $apiUser,
        private string $apiToken,
        private int $memberListId,
        private int $newsletterListId = 0,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * Ist die Anbindung vollständig konfiguriert? Ohne Konfiguration bleibt das Feature inaktiv.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiUser !== '' && $this->apiToken !== '' && $this->memberListId > 0;
    }

    public function isNewsletterConfigured(): bool
    {
        return $this->isConfigured() && $this->newsletterListId > 0;
    }

    /**
     * Gleicht die Mitglieder-Liste ab. Entfernt Abonnent*innen, die nicht (mehr) in $members stehen;
     * eine Abmeldung in ListMonk bleibt dort bestehen und hat keine Auswirkung auf das LDAP.
     *
     * @param array[] $members
     * @return array{total:int,created:int,updated:int,removed:int,skipped:int,unmatched:int,errors:string[]}
     */
    public function syncMembers(array $members): array
    {
        $this->assertConfigured();
        $result = $this->syncList($this->memberListId, $members, false);
        unset($result['unsubscribed']);
        return $result;
    }

    /**
     * Gleicht die Newsletter-Liste ab. Wer in ListMonk von der Liste abgemeldet ist, wird nicht neu
     * angemeldet, sondern in 'unsubscribed' (Mitgliedsnummern) gemeldet; der Aufrufer entfernt sie aus der Gruppe.
     *
     * @param array[] $members die Mitglieder der Gruppe „newsletter“
     * @return array{total:int,created:int,updated:int,removed:int,skipped:int,unmatched:int,errors:string[],unsubscribed:int[]}
     */
    public function syncNewsletter(array $members): array
    {
        $this->assertNewsletterConfigured();
        return $this->syncList($this->newsletterListId, $members, true);
    }

    /**
     * Meldet ein Mitglied für den Newsletter an. Setzt eine frühere Abmeldung zurück (Aufnahme in die Gruppe).
     */
    public function subscribeNewsletter(array $member): void
    {
        $this->assertNewsletterConfigured();
        $entry = $this->buildEntry($member);
        if ($entry === null) {
            return;
        }

        $existing = $this->findByNumber((int) $member['id']) ?? $this->findByEmail($entry['email']);
        if ($existing === null) {
            $this->createSubscriber($entry, [$this->newsletterListId]);
            return;
        }
        if ($this->needsUpdate($existing, $entry)) {
            $this->updateSubscriber($existing, $entry, $this->listIdsOf($existing));
        }
        $this->setListStatus([(int) $existing['id']], $this->newsletterListId, 'add', 'confirmed');
    }

    /**
     * Entfernt ein Mitglied aus der Newsletter-Liste (Entfernen aus der Gruppe). Nicht vorhandene sind kein Fehler.
     */
    public function unsubscribeNewsletter(array $member): void
    {
        $this->assertNewsletterConfigured();
        $existing = $this->findByNumber((int) $member['id']);
        if ($existing !== null) {
            $this->removeFromList($this->newsletterListId, [(int) $existing['id']]);
        }
    }

    /**
     * Gleicht ein einzelnes Mitglied sofort ab (z.B. nach einer Adressänderung): Der Abonnent wird über die
     * Mitgliedsnummer gefunden, sonst über die Adresse (ein Eintrag ohne Mitgliedsnummer wird übernommen),
     * sonst neu angelegt. Es werden nur fehlende Listen ergänzt und Adresse/Name/Attribute aktualisiert;
     * Abmeldungen und das Entfernen anderer Mitglieder bleiben dem regelmäßigen Abgleich überlassen.
     * Hat das Mitglied keine gültige Adresse, wird es wie beim Abgleich aus unseren Listen genommen.
     *
     * @param array $member siehe Klassenkommentar
     * @param bool $inMemberList Mitglied gehört in die Mitglieder-Liste (Gruppe „mitglieder“)
     * @param bool $inNewsletter Mitglied gehört in den Newsletter (Gruppe „newsletter“)
     * @throws \RuntimeException bei Fehlern oder einem Adresskonflikt in ListMonk
     */
    public function syncMember(array $member, bool $inMemberList, bool $inNewsletter): void
    {
        $this->assertConfigured();
        $entry = $this->buildEntry($member);
        $existing = $this->findByNumber((int) ($member['id'] ?? 0));

        if ($entry === null) {
            if ($existing !== null) {
                $present = $this->listIdsOf($existing);
                foreach ([$this->memberListId, $this->newsletterListId] as $listId) {
                    if ($listId > 0 && in_array($listId, $present, true)) {
                        $this->removeFromList($listId, [(int) $existing['id']]);
                    }
                }
            }
            return;
        }

        $wanted = [];
        if ($inMemberList) {
            $wanted[] = $this->memberListId;
        }
        if ($inNewsletter && $this->isNewsletterConfigured()) {
            $wanted[] = $this->newsletterListId;
        }
        if (!$wanted) {
            return;
        }

        $existing ??= $this->findByEmail($entry['email']);
        if ($existing === null) {
            $this->createSubscriber($entry, $wanted);
            return;
        }

        $listIds = $this->listIdsOf($existing);
        $added = array_diff($wanted, $listIds);
        if ($added || $this->needsUpdate($existing, $entry)) {
            $this->updateSubscriber($existing, $entry, array_values(array_unique([...$listIds, ...$added])));
        }
    }

    /**
     * Adressen mit einem Hard Bounce (dauerhaft unzustellbar). Soft Bounces und Beschwerden werden ignoriert.
     *
     * @return string[] Adressen in Kleinschreibung
     */
    public function getHardBounces(): array
    {
        $this->assertConfigured();
        [$status, $body] = $this->request('GET', '/api/bounces?' . http_build_query(['per_page' => 'all']));
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Konnte Bounces nicht laden (HTTP ' . $status . ').', 1750000007);
        }
        $emails = [];
        foreach ($body['data']['results'] ?? [] as $bounce) {
            $email = trim((string) ($bounce['email'] ?? ''));
            if (($bounce['type'] ?? '') === 'hard' && $email !== '') {
                $emails[mb_strtolower($email)] = true;
            }
        }
        return array_keys($emails);
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('ListMonk ist nicht konfiguriert (LISTMONK_URL, LISTMONK_USER, LISTMONK_TOKEN, LISTMONK_LIST_ID).', 1750000001);
        }
    }

    private function assertNewsletterConfigured(): void
    {
        if (!$this->isNewsletterConfigured()) {
            throw new \RuntimeException('Die Newsletter-Liste ist nicht konfiguriert (LISTMONK_NEWSLETTER_LIST_ID).', 1750000008);
        }
    }

    /**
     * @param array[] $members
     */
    private function syncList(int $listId, array $members, bool $detectUnsubscribed): array
    {
        $created = $updated = $removed = $skipped = 0;
        $errors = [];
        $unsubscribed = [];

        // 1. Soll-Zustand: Mitgliedsnummer => Eintrag (ungültige Adressen überspringen)
        $desired = [];
        foreach ($members as $member) {
            $entry = $this->buildEntry($member);
            if ($entry === null) {
                $skipped++;
                continue;
            }
            $desired[(int) $member['id']] = $entry;
        }

        // 2. Ist-Zustand: Abonnent*innen der Liste nach Mitgliedsnummer (ohne Nummer: nicht zuordenbar, bleiben unberührt)
        $current = [];
        $unmatched = 0;
        foreach ($this->getListSubscribers($listId) as $subscriber) {
            $number = (int) ($subscriber['attribs']['mitgliedsnummer'] ?? 0);
            if ($number > 0) {
                $current[$number] = $subscriber;
            } else {
                $unmatched++;
            }
        }

        // 3. Anlegen / aktualisieren / Abmeldungen erkennen
        foreach ($desired as $number => $entry) {
            try {
                if (isset($current[$number])) {
                    if ($detectUnsubscribed && $this->subscriptionStatusOf($current[$number], $listId) === 'unsubscribed') {
                        $unsubscribed[] = $number;
                        continue;
                    }
                    if ($this->needsUpdate($current[$number], $entry)) {
                        $this->updateSubscriber($current[$number], $entry, $this->listIdsOf($current[$number]));
                        $updated++;
                    }
                } else {
                    $this->createOrAdd($entry, $number, $listId);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = $entry['email'] . ': ' . $e->getMessage();
            }
        }

        // 4. Voller Abgleich: Abonnent*innen entfernen, die nicht (mehr) zum Soll-Zustand gehören
        $removeIds = [];
        foreach ($current as $number => $subscriber) {
            if (!isset($desired[$number])) {
                $removeIds[] = (int) $subscriber['id'];
            }
        }
        if ($removeIds) {
            try {
                $this->removeFromList($listId, $removeIds);
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
            'unmatched' => $unmatched,
            'errors' => $errors,
            'unsubscribed' => $unsubscribed,
        ];
    }

    /**
     * Soll-Eintrag zu einem Mitglied, oder null bei leerer bzw. als ungültig markierter Adresse.
     */
    private function buildEntry(array $member): ?array
    {
        $email = trim((string) ($member['email'] ?? ''));
        if ($email === '' || MailAddress::isInvalid($email)) {
            return null;
        }
        return [
            'email' => $email,
            'name' => trim(($member['firstname'] ?? '') . ' ' . ($member['lastname'] ?? '')),
            'attribs' => [
                'mitgliedsnummer' => (int) ($member['id'] ?? 0),
                'vorname' => (string) ($member['firstname'] ?? ''),
                'nachname' => (string) ($member['lastname'] ?? ''),
            ],
        ];
    }

    /**
     * Alle Abonnenten einer Liste (mit jeweiligem Abo-Status), paginiert geladen.
     *
     * @return array[] Abonnenten-Objekte von ListMonk
     */
    private function getListSubscribers(int $listId): array
    {
        $subscribers = [];
        $page = 1;
        $perPage = 1000;
        do {
            $query = http_build_query([
                'list_id' => $listId,
                'page' => $page,
                'per_page' => $perPage,
            ]);
            [$status, $body] = $this->request('GET', '/api/subscribers?' . $query);
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Konnte Abonnenten nicht laden (HTTP ' . $status . ').', 1750000002);
            }
            $results = $body['data']['results'] ?? [];
            foreach ($results as $subscriber) {
                $subscribers[(int) $subscriber['id']] = $subscriber;
            }
            $total = (int) ($body['data']['total'] ?? 0);
            $page++;
        } while ($results && count($subscribers) < $total);

        return array_values($subscribers);
    }

    /**
     * Legt einen Abonnenten an. Existiert er bereits global (nur nicht in dieser Liste), wird er gesucht
     * (zuerst über die Mitgliedsnummer, dann über die Adresse) und der Liste hinzugefügt.
     */
    private function createOrAdd(array $entry, int $number, int $listId): void
    {
        $existing = $this->findByNumber($number) ?? $this->findByEmail($entry['email']);
        if ($existing === null) {
            $this->createSubscriber($entry, [$listId]);
            return;
        }

        $listIds = $this->listIdsOf($existing);
        if (!in_array($listId, $listIds, true)) {
            $listIds[] = $listId;
        }
        $this->updateSubscriber($existing, $entry, $listIds);
    }

    /**
     * @param int[] $listIds
     */
    private function createSubscriber(array $entry, array $listIds): void
    {
        [$status, $body] = $this->request('POST', '/api/subscribers', [
            'email' => $entry['email'],
            'name' => $entry['name'],
            'status' => 'enabled',
            'lists' => array_values($listIds),
            'preconfirm_subscriptions' => true,
            'attribs' => $entry['attribs'],
        ]);
        if ($status < 200 || $status >= 300) {
            $message = $body['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Anlegen fehlgeschlagen: ' . $message, 1750000003);
        }
    }

    /**
     * Aktualisiert Name/Adresse/Attribute eines Abonnenten. Listenmitgliedschaften und Status werden bewusst
     * mitgegeben, da PUT in ListMonk alle Felder überschreibt.
     *
     * @param int[] $listIds beizubehaltende/zu setzende Listen-IDs
     */
    private function updateSubscriber(array $subscriber, array $entry, array $listIds): void
    {
        // Adressen sind in ListMonk eindeutig: eine verständliche Meldung statt der Datenbankfehlermeldung
        $other = $this->findOtherByEmail($entry['email'], (int) $subscriber['id']);
        if ($other !== null) {
            $number = (int) ($other['attribs']['mitgliedsnummer'] ?? 0);
            throw new \RuntimeException(
                'Die Adresse gehört in ListMonk bereits zum Abonnenten ' . (int) $other['id']
                . ($number > 0 ? ' (Mitgliedsnummer ' . $number . ')' : ' (ohne Mitgliedsnummer)') . '.',
                1750000010
            );
        }

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
            throw new \RuntimeException('Aktualisieren des Abonnenten ' . $id . ' fehlgeschlagen: ' . $message, 1750000004);
        }
    }

    /**
     * Entfernt Abonnenten aus einer Liste (löscht sie nicht global, andere Listen bleiben erhalten).
     *
     * @param int[] $ids
     */
    private function removeFromList(int $listId, array $ids): void
    {
        $this->setListStatus($ids, $listId, 'remove');
    }

    /**
     * Ändert Listenmitgliedschaften (ListMonk: PUT /api/subscribers/lists).
     *
     * @param int[] $ids
     * @param 'add'|'remove'|'unsubscribe' $action
     * @param string|null $subscriptionStatus bei 'add': 'confirmed' setzt auch eine bestehende Abmeldung zurück
     */
    private function setListStatus(array $ids, int $listId, string $action, ?string $subscriptionStatus = null): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            $body = [
                'ids' => array_values($chunk),
                'action' => $action,
                'target_list_ids' => [$listId],
            ];
            if ($subscriptionStatus !== null) {
                $body['status'] = $subscriptionStatus;
            }
            [$status, $response] = $this->request('PUT', '/api/subscribers/lists', $body);
            if ($status < 200 || $status >= 300) {
                $message = $response['message'] ?? ('HTTP ' . $status);
                throw new \RuntimeException($message, 1750000005);
            }
        }
    }

    /**
     * Sucht einen Abonnenten anhand seiner E-Mail-Adresse.
     */
    private function findByEmail(string $email): ?array
    {
        // Groß-/Kleinschreibung egal: ListMonk behandelt Adressen über LOWER(email) als eindeutig
        return $this->findByQuery("LOWER(subscribers.email) = LOWER('" . str_replace("'", "''", $email) . "')");
    }

    /**
     * Sucht einen Abonnenten anhand der Mitgliedsnummer.
     */
    private function findByNumber(int $number): ?array
    {
        return $this->findByQuery("subscribers.attribs->>'mitgliedsnummer' = '" . $number . "'");
    }

    /**
     * Sucht einen Abonnenten mit dieser Adresse, der nicht der übergebene selbst ist (bei abweichender
     * Schreibweise kann es mehrere Treffer geben, dann hat der mit exakt gleicher Schreibweise Vorrang).
     */
    private function findOtherByEmail(string $email, int $ownId): ?array
    {
        $others = array_values(array_filter(
            $this->queryAll("LOWER(subscribers.email) = LOWER('" . str_replace("'", "''", $email) . "')"),
            fn(array $subscriber) => (int) $subscriber['id'] !== $ownId
        ));
        foreach ($others as $other) {
            if ($other['email'] === $email) {
                return $other;
            }
        }
        return $others[0] ?? null;
    }

    private function findByQuery(string $sqlCondition): ?array
    {
        return $this->queryAll($sqlCondition)[0] ?? null;
    }

    /**
     * @return array[] passende Abonnenten (höchstens 20)
     */
    private function queryAll(string $sqlCondition): array
    {
        $query = http_build_query([
            'query' => $sqlCondition,
            'per_page' => 20,
        ]);
        [$status, $body] = $this->request('GET', '/api/subscribers?' . $query);
        if ($status < 200 || $status >= 300) {
            // nicht verschlucken: sonst bleibt unbemerkt, dass z.B. ein Adresskonflikt nicht erkannt wurde
            throw new \RuntimeException('Suche nach Abonnenten fehlgeschlagen (HTTP ' . $status . '): ' . ($body['message'] ?? 'keine Meldung') . ' [' . $sqlCondition . ']', 1750000012);
        }
        return $body['data']['results'] ?? [];
    }

    /**
     * Muss der Abonnent aktualisiert werden (Adresse, Name oder relevante Attribute weichen ab)?
     */
    private function needsUpdate(array $subscriber, array $entry): bool
    {
        if (strcasecmp((string) ($subscriber['email'] ?? ''), $entry['email']) !== 0) {
            return true;
        }
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
     * Abo-Status ('unconfirmed', 'confirmed', 'unsubscribed') des Abonnenten in einer Liste, oder null.
     */
    private function subscriptionStatusOf(array $subscriber, int $listId): ?string
    {
        foreach ($subscriber['lists'] ?? [] as $list) {
            if ((int) ($list['id'] ?? 0) === $listId) {
                return $list['subscription_status'] ?? null;
            }
        }
        return null;
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
