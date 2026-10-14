<?php
declare(strict_types=1);
namespace App\Controller;

use App\Model\Group;
use App\Model\GroupSettings;
use App\Repository\GroupRepository;
use App\Service\GroupPermissions;
use App\Service\ListigApi;
use App\Service\ListigPasswordRejectedException;
use Hengeb\Router\Attribute\Inject;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Einstellungen einer Gruppe: vereinfachter Dialog für Gruppenverwalter*innen (auf der Gruppenseite) und
 * Einstellungsseite für groupadmin/rechte. Beide speichern über denselben Endpunkt; welche Felder
 * übernommen werden, richtet sich nach den Rechten (siehe GroupPermissions).
 *
 * Bei den listig-Einstellungen bedeutet null „nicht gesetzt“ (listig-Standard gilt) und '' „bewusst leer“.
 */
class GroupSettingsController extends Controller
{
    /** Textfelder, bei denen „bewusst leer“ eine eigene Bedeutung hat (z. B. kein Footer) */
    private const TEXT_FIELDS = ['listLabel', 'fromName', 'footer'];

    /** was Gruppenverwalter*innen immer ändern dürfen */
    private const OWNER_ENUM_FIELDS = ['joinPolicy', 'memberVisibility', 'visibility'];

    /** was Gruppenverwalter*innen nur bei einer Mailingliste ändern dürfen */
    private const OWNER_LIST_ENUM_FIELDS = ['postAccessMembers', 'replyTo', 'archive'];
    private const OWNER_LIST_TEXT_FIELDS = ['listLabel'];

    #[Inject]
    public GroupPermissions $groupPermissions;

    #[Route('GET /groups/{name=>group}/settings'), RequireLogin]
    public function show(Group $group): Response
    {
        $this->groupPermissions->requireAdminister($group);

        return $this->render('GroupSettingsController/settings', [
            'group' => $group,
            'isRechteAdmin' => $this->groupPermissions->isRechteAdmin(),
            'sections' => GroupSettings::adminSections($group),
        ]);
    }

    #[Route('POST /groups/{name=>group}/settings'), RequireLogin]
    public function save(Group $group, GroupRepository $groupRepository): Response
    {
        $this->groupPermissions->requireManage($group);

        $adminForm = $this->isAdminForm($group);
        $this->applySettings($group, $adminForm);
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups/' . rawurlencode($group->name) . ($adminForm ? '/settings' : ''));
    }

    #[Route('POST /groups/{name=>group}/settings/list-password'), RequireLogin]
    public function setListPassword(Group $group, GroupRepository $groupRepository, ListigApi $listigApi): Response
    {
        // das Mailserver-Passwort dürfen nur groupadmin/rechte ändern, nicht die Gruppenverwalter*innen
        $this->groupPermissions->requireAdminister($group);

        $input = $this->validatePayload([
            'password' => 'string required untrimmed',
        ]);

        // die Einstellungen kommen aus demselben Formular wie in save() und werden hier mit gespeichert -
        // sonst würde bei einer noch nicht gespeicherten (z.B. neu angelegten) Liste das Passwort gesetzt,
        // obwohl der Rest der Konfiguration (insb. mailAddress, das Listig als IMAP-Benutzername verwendet)
        // noch fehlt, wodurch die Verifikation fehlschlägt.
        if ($this->isAdminForm($group)) {
            $this->applySettings($group, true);
        }

        if ($input['password'] === '') {
            $group->listPasswordCiphertext = '';
            $groupRepository->save($group);
            return $this->success();
        }

        // api-token nur temporär setzen, damit Listig sich per Bearer-Token authentifizieren kann;
        // Listig verschlüsselt das Passwort und schreibt mail-password selbst direkt in denselben
        // LDAP-Eintrag (encrypt-password gibt das Chiffrat nicht zurück) - daher unten neu einlesen,
        // bevor der Token wieder entfernt wird, sonst würde ein veralteter Save es überschreiben.
        $group->apiToken = bin2hex(random_bytes(32));
        $groupRepository->save($group);

        try {
            $listigApi->encryptPassword($group->name, $group->apiToken, $input['password']);
        } catch (ListigPasswordRejectedException $e) {
            $this->clearListApiToken($group, $groupRepository);
            throw new InvalidUserDataException('Das Passwort wurde vom Mailserver abgelehnt: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->clearListApiToken($group, $groupRepository);
            throw new InvalidUserDataException('Passwort konnte nicht gesetzt werden: ' . $e->getMessage());
        }

        $this->clearListApiToken($group, $groupRepository);
        return $this->success();
    }

    private function success(): Response
    {
        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    private function clearListApiToken(Group $group, GroupRepository $groupRepository): void
    {
        $group = $groupRepository->findOneByName($group->name) ?? $group;
        $group->apiToken = '';
        $groupRepository->save($group);
    }

    /** das Formular der Einstellungsseite (nicht der Dialog) darf nur von groupadmin/rechte kommen */
    private function isAdminForm(Group $group): bool
    {
        return $this->request->getPayload()->getString('form') === 'admin'
            && $this->groupPermissions->mayAdminister($group);
    }

    private function applySettings(Group $group, bool $adminForm): void
    {
        $payload = $this->request->getPayload();

        // Felder, die beide Formulare haben
        foreach (['displayName', 'description'] as $field) {
            if ($payload->has($field)) {
                $group->$field = $this->nonEmptyOrNull($payload->getString($field));
            }
        }
        if ($payload->has('category')) {
            $group->category = trim($payload->getString('category'));
        }

        if ($adminForm) {
            $this->applyAdminSettings($group);
        } else {
            $this->applyOwnerSettings($group);
        }
    }

    /** Dialog: ein Feld, das fehlt, bleibt unverändert; leer = nicht gesetzt (Standard) */
    private function applyOwnerSettings(Group $group): void
    {
        $payload = $this->request->getPayload();
        $isMailingList = $group->hasListPassword();

        $enumFields = $isMailingList
            ? [...self::OWNER_ENUM_FIELDS, ...self::OWNER_LIST_ENUM_FIELDS]
            : self::OWNER_ENUM_FIELDS;
        foreach ($enumFields as $field) {
            if ($payload->has($field)) {
                $group->$field = $this->parseEnum($field, trim($payload->getString($field)));
            }
        }

        if ($isMailingList) {
            foreach (self::OWNER_LIST_TEXT_FIELDS as $field) {
                // ein bewusst leerer Wert (von groupadmin gesetzt) bleibt erhalten, solange das Feld leer bleibt
                if ($payload->has($field) && !($group->$field === '' && trim($payload->getString($field)) === '')) {
                    $group->$field = $this->nonEmptyOrNull($payload->getString($field));
                }
            }
            if ($payload->has('archiveMaxAge')) {
                $group->archiveMaxAge = $this->parseArchiveMaxAge(trim($payload->getString('archiveMaxAge')), $group);
            }
        }
    }

    /** Einstellungsseite: jede listig-Einstellung hat eine Checkbox `set_<feld>`; ohne Haken = nicht gesetzt */
    private function applyAdminSettings(Group $group): void
    {
        $payload = $this->request->getPayload();

        foreach (array_keys(GroupSettings::ENUM_FIELDS) as $field) {
            $group->$field = $payload->has('set_' . $field)
                ? $this->parseEnum($field, trim($payload->getString($field)))
                : null;
        }
        foreach (self::TEXT_FIELDS as $field) {
            $group->$field = $payload->has('set_' . $field) ? trim($payload->getString($field)) : null;
        }
        $group->archiveMaxAge = $payload->has('set_archiveMaxAge')
            ? $this->parseArchiveMaxAge(trim($payload->getString('archiveMaxAge')), $group)
            : null;

        if ($payload->has('set_personalize')) {
            $keys = array_keys(array_filter(
                GroupSettings::PERSONALIZE_OPTIONS,
                fn($label, $key) => $payload->has('personalize_' . $key),
                ARRAY_FILTER_USE_BOTH
            ));
            $group->personalize = implode(',', $keys);
        } else {
            $group->personalize = null;
        }

        $mailAddress = trim($payload->getString('mailAddress'));
        if ($mailAddress !== '' && filter_var($mailAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidUserDataException('Die E-Mail-Adresse ist ungültig.');
        }
        $group->mailAddress = $mailAddress !== '' ? $mailAddress : null;

        if ($this->groupPermissions->isRechteAdmin()) {
            $group->privileged = $payload->has('privileged');
        }
    }

    private function nonEmptyOrNull(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function parseEnum(string $field, string $value): ?\BackedEnum
    {
        if ($value === '') {
            return null;
        }
        $enum = GroupSettings::ENUM_FIELDS[$field]::tryFrom($value);
        if (!$enum) {
            throw new InvalidUserDataException('Ungültiger Wert für ' . $field . '.');
        }
        return $enum;
    }

    /** nur die angebotenen Fristen (oder der bereits gespeicherte Wert); leer = Standard (nicht gesetzt), „unbegrenzt“ = leerer Eintrag */
    private function parseArchiveMaxAge(string $value, Group $group): ?string
    {
        if ($value === '') {
            return null;
        }
        if ($value === GroupSettings::ARCHIVE_UNLIMITED) {
            return '';
        }
        if (!isset(GroupSettings::ARCHIVE_MAX_AGE_OPTIONS[$value]) && $value !== $group->archiveMaxAge) {
            throw new InvalidUserDataException('Ungültige Aufbewahrungsdauer.');
        }
        return $value;
    }
}
