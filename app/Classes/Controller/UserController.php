<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Controller\Exception\AccessDeniedException;
use App\Controller\Exception\InvalidUserDataException;
use App\Controller\Exception\NotFoundException;
use App\Mitglied;
use App\Service\AuthService;
use App\Service\EmailService;
use App\Service\ImageResizer;
use App\Service\Ldap;
use App\Service\Tpl;
use Hengeb\Token\Token;

class UserController extends Controller {
    // Maximale Größe von Profilbildern
    const profilbildMaxWidth = 800;
    const profilbildMaxHeight = 800;
    const thumbnailMaxWidth = 300;
    const thumbnailMaxHeight = 300;

    // Liste der vom Mitglied änderbaren Strings, deren Werte nicht geprüft werden
    const bearbeiten_strings_ungeprueft = ['titel', 'mensa_nr', 'strasse', 'adresszusatz', 'plz', 'ort', 'land', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'telefon', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort', 'studienfach', 'unityp', 'schwerpunkt', 'nebenfach', 'abschluss', 'zweitstudium', 'hochschulaktivitaeten', 'stipendien', 'auslandsaufenthalte', 'praktika', 'beruf', 'aufgabe_sonstiges_beschreibung'];

    // Liste der vom Mitglied änderbaren Booleans
    const bearbeiten_bool_ungeprueft = ['sichtbarkeit_email', 'sichtbarkeit_geburtstag', 'sichtbarkeit_mensa_nr', 'sichtbarkeit_strasse', 'sichtbarkeit_adresszusatz', 'sichtbarkeit_plz_ort', 'sichtbarkeit_land', 'sichtbarkeit_telefon', 'sichtbarkeit_beschaeftigung', 'sichtbarkeit_studienort', 'sichtbarkeit_studienfach', 'sichtbarkeit_unityp', 'sichtbarkeit_schwerpunkt', 'sichtbarkeit_nebenfach', 'sichtbarkeit_abschluss', 'sichtbarkeit_zweitstudium', 'sichtbarkeit_hochschulaktivitaeten', 'sichtbarkeit_stipendien', 'sichtbarkeit_auslandsaufenthalte', 'sichtbarkeit_praktika', 'sichtbarkeit_beruf', 'auskunft_studiengang', 'auskunft_stipendien', 'auskunft_auslandsaufenthalte', 'auskunft_praktika', 'auskunft_beruf', 'mentoring', 'aufgabe_ma', 'aufgabe_orte', 'aufgabe_vortrag', 'aufgabe_koord', 'aufgabe_graphisch', 'aufgabe_computer', 'aufgabe_texte_schreiben', 'aufgabe_texte_lesen', 'aufgabe_vermittlung', 'aufgabe_ansprechpartner', 'aufgabe_hilfe', 'aufgabe_sonstiges'];

    // Liste der von der Mitgliederverwaltung änderbaren Strings
    const bearbeiten_strings_admin = ['vorname', 'nachname'];

    public function getResponse(): Response {
        if ($this->path[1] === 'email_auth') {
            return $this->emailAuth($this->request->query->getString('token'));
        }

        $this->requireLogin();

        $id = null;
        if ($this->path[2] === '' || $this->path[2] === '_') {
            $id = AuthService::getUID();
        } elseif (ctype_digit($this->path[2])) {
            $id = intval($this->path[2]);
        } else {
            $id = Mitglied::getIdByUsername($this->path[2]);
        }

        $m = $id ? Mitglied::lade($id) : null;

        if ($m === null) {
            throw new NotFoundException('Ein Mitglied mit dieser Nummer existiert nicht.');
        }

        if ($this->path[3] === 'edit') {
            return $this->edit($m);
        } else if ($this->path[3] === 'update' && $this->request->isMethod('POST')) {
            return $this->update($m);
        } else {
            return $this->show($m);
        }
    }

    public function show(Mitglied $m): Response {
        $db_modified = $m->get('db_modified');
        $mvread = AuthService::hatRecht('mvread');

        $templateVars = [
            'htmlTitle' => $m->get('fullName'),
            'mvread' => $mvread,
            'fullName' => $m->get('fullName'),
            'title' => $m->get('fullName'),
        ];
        if ($db_modified) {
            $templateVars['title'] .= ' <small>Stand: '. $m->get('db_modified')->format('d.m.Y') . '</small>';
        }
        if (AuthService::ist($m->get('id')) || AuthService::hatRecht('mvedit')) {
            $templateVars['title'] .= ' <small><a href="' . $m->get('bearbeitenUrl') . '"><span class="glyphicon glyphicon-pencil"></span> Daten bearbeiten</a></small>';
        }

        // generell: alle Daten kopieren
        foreach (array_keys(Mitglied::felder) as $feld) {
            $templateVars[$feld] = $m->get($feld);
        }

        // Dann die sichtgeschützten Felder gesondert behandeln, damit das Template möglichst frei von Logik bleiben kann
        if (!$mvread) {
            foreach (['email', 'geburtstag', 'mensa_nr', 'strasse', 'adresszusatz', 'land', 'telefon',
                'beschaeftigung', 'studienort', 'studienfach', 'unityp', 'schwerpunkt', 'nebenfach', 'abschluss',
                'zweitstudium', 'nebenfach', 'abschluss', 'zweitstudium', 'hochschulaktivitaeten', 'stipendien',
                'auslandsaufenthalte', 'praktika', 'beruf'] as $feld) {
                if (!$m->get('sichtbarkeit_' . $feld)) {
                    $templateVars[$feld] = '';
                }
            }
            if (!$m->get('sichtbarkeit_plz_ort')) {
                $templateVars['plz'] = '';
                $templateVars['ort'] = '';
            }
        }

        // Überprüfen, ob die Homepage das korrekte Format hat. ggf. http:// ergänzen
        $homepage = $m->get('homepage');
        if (!preg_match('=^https?://=i', $homepage)) {
            $homepage = 'http://' . $homepage;
        }
        if (!preg_match('=^https?://(?P<user>[^@]*@)?(?P<host>[\w\.0-9-]+)(?P<port>:[0-9]+)?(?<query>/.*)?$=i', $homepage)) {
            $homepage = '';
        }
        $templateVars['homepage'] = $homepage;

        return $this->render('UserController/profil', $templateVars);
    }

    public function edit(Mitglied $m): Response {
        $templateVars = [];

        $tab = $this->request->query->getString('tab');
        if (in_array($tab, ['basisdaten', 'uebermich', 'ausbildungberuf', 'profilbild', 'settings', 'account'])) {
            $templateVars['active_pane'] = $tab;
        }

        if (!AuthService::ist($m->get('id')) && !AuthService::hatRecht('mvedit')) {
            throw new AccessDeniedException();
        }

        foreach (array_keys(Mitglied::felder) as $feld) {
            $templateVars[$feld] = $m->get($feld);
        }
        $templateVars += [
            'fullName' => $m->get('fullName'),
            'dateOfJoining' => $m->get('dateOfJoining'),
            'groups' => $m->getGroups(),
            'db_modified_user' => Mitglied::lade((int)$m->get('db_modified_user_id')),
            'isAdmin' => AuthService::hatRecht('mvedit'),
            'isSuperAdmin' => AuthService::hatRecht('rechte'),
            'isSelf' => AuthService::ist($m->get('id')),
        ];

        return $this->render('UserController/bearbeiten', $templateVars);
    }

    private function updatePassword(Mitglied $m): void {
        $input = $this->validatePayload([
            'new_password' => 'string untrimmed',
            'new_password2' => 'string untrimmed',
            'password' => 'string untrimmed',
        ]);
        if ($input['new_password'] && !$input['new_password2'] && !$input['password'] && AuthService::checkPassword($input['new_password'], $m->get('id'))) {
            // nichts tun. Der Passwort-Manager des Users hat das Passwort eingefügt und autocomplete=new-password ignoriert
            return;
        }
        if (!$input['new_password']) {
            return;
        }

        $this->setTemplateVariable('set_new_password', true);
        if ($input['new_password'] !== $input['new_password2']) {
            $this->setTemplateVariable('new_password2_error', true);
        } else {
            // Admins dürfen Passwörter ohne Angabe des eigenen Passworts ändern, außer das eigene
            if (AuthService::hatRecht('mvedit') && !AuthService::ist($m->get('id'))) {
                $m->set('password', $input['new_password']);
            } elseif (AuthService::checkPassword($_REQUEST['password'])) {
                $m->set('password', $input['new_password']);
            } else {
                $this->setTemplateVariable('old_password_error', true);
            }
        }
    }

    private function updateEmail(Mitglied $m): void {
        $email = $this->validatePayload(['email' => 'string'])['email'];

        if ($m->get('email') === $email) {
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_+&*-]+(?:\.[a-zA-Z0-9_+&*-]+)*@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,50}$/', $email)) { // siehe https://www.owasp.org/index.php/OWASP_Validation_Regex_Repository
            $this->setTemplateVariable('email_error', true);
            return;
        }

        if (AuthService::hatRecht('mvedit')) {
            $this->storeEmail($m, $email);
        } else {
            $this->setTemplateVariable('email_auth_info', true);
            $token = Token::encode([time(), $m->get('id'), $email], $m->get('email'), getenv('TOKEN_KEY'));
            $text = Tpl::getInstance()->render('mails/email-auth', ['token' => $token]);
            EmailService::getInstance()->send($email, 'E-Mail-Änderung', $text);
        }
    }

    private function updateAdmin(Mitglied $m): void {
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_strings_admin, 'string'));
        foreach ($input as $key=>$value) {
            $m->set($key, $value);
        }

        $input = $this->validatePayload([
            'geburtstag' => 'date',
        ]);
        foreach ($input as $key=>$value) {
            if ($value === '0000-00-00') {
                $value = null;
            }
            $m->set($key, $value);
        }
    }

    private function updateProfilePicture(Mitglied $m): void {
        $file = $this->request->files->get('profilbild');
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if (!$file->isValid()) {
            $this->setTemplateVariable('profilbild_uploadfehler', true);
        }

        $type = null;
        switch ($file->getMimeType()) {
            case 'image/jpeg':
                $type = 'jpeg';
                break;
            case 'image/png':
                $type = 'png';
                break;
            default:
                $this->setTemplateVariable('profilbild_format_unbekannt', true);
                return;
        }

        // Dateiname zufällig wählen
        $fileName = $m->get('id') . '-' . md5_file($file->getPathname()) . '.' . $type;

        // Datei und Thumbnail erstellen
        list($size_x, $size_y) = ImageResizer::resize($file->getPathname(), 'profilbilder/' . $fileName, $type, $type, self::profilbildMaxWidth, self::profilbildMaxHeight);
        ImageResizer::resize($file->getPathname(), 'profilbilder/thumbnail-' . $fileName, $type, $type, self::thumbnailMaxWidth, self::thumbnailMaxHeight);

        // altes Profilbild löschen
        if ($m->get('profilbild') && is_file('profilbilder/' . $m->get('profilbild'))) {
            unlink('profilbilder/' . $m->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $m->get('profilbild'));
        }

        $m->set('profilbild', $fileName);
        $m->set('profilbild_x', $size_x);
        $m->set('profilbild_y', $size_y);
    }

    private function removeProfilePicture(Mitglied $m): void {
        if ($m->get('profilbild') && is_file('profilbilder/' . $m->get('profilbild'))) {
            unlink('profilbilder/' . $m->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $m->get('profilbild'));
        }
        $m->set('profilbild', '');
    }

    private function updateGroups(Mitglied $m): void {
        $input = $this->validatePayload(['groups' => 'string']);
        $groups = array_filter(array_unique(preg_split('/[\s,]+/', $input['groups'])));

        if (AuthService::ist($m->get('id')) && (!in_array('rechte', $groups, true))) {
            throw new AccessDeniedException('Du kannst dir das Recht zur Rechtverwaltung nicht selbst entziehen.');
        }

        try {
            $m->setGroups($groups);
        } catch (\Exception $e) {
            $this->setTemplateVariable('errorMessage', 'Beim Setzen der Gruppen ist ein Fehler aufgetreten.');
        }
    }

    private function handleResign(Mitglied $m) {
        $password = $this->request->getPayload()->getString('resignPassword');
        if ($password) {
            if (!AuthService::checkPassword($password)) {
                $this->setTemplateVariable('errorMessage', 'Das eingegebene Passwort ist nicht korrekt.');
            } else {
                $m->set('resignation', 'now');
                $text = Tpl::getInstance()->render('mails/resignation', [
                    'fullName' => $m->get('fullName'),
                    'id' => $m->get('id'),
                ]);
                EmailService::getInstance()->send('vorstand@mind-hochschul-netzwerk.de', 'Austrittserklärung', $text);
                EmailService::getInstance()->send('mitgliederbetreuung@mind-hochschul-netzwerk.de', 'Austrittserklärung', $text);
                $text = Tpl::getInstance()->render('mails/resignationConfirmation', [
                    'fullName' => $m->get('fullName'),
                    'id' => $m->get('id'),
                ]);
                $m->sendEmail('Bestätigung deiner Austrittserklärung', $text);
            }
        } elseif (AuthService::hatRecht('mvedit')) {
            $resignOld = $m->get('resignation') !== null;
            $resignNew = $this->request->getPayload()->getBoolean('resign');
            if ($resignOld && !$resignNew) {
                $m->set('resignation', null);
            } elseif (!$resignOld && $resignNew) {
                $m->set('resignation', 'now');
                $admin = Mitglied::lade(AuthService::getUID());
                $text = Tpl::getInstance()->render('mails/resignation', [
                    'adminFullName' => $admin->get('fullName'),
                    'fullName' => $m->get('fullName'),
                    'id' => $m->get('id'),
                ]);
                EmailService::getInstance()->send('vorstand@mind-hochschul-netzwerk.de', 'Austrittserklärung eingetragen', $text);
                EmailService::getInstance()->send('mitgliederbetreuung@mind-hochschul-netzwerk.de', 'Austrittserklärung eingetragen', $text);
                $text = Tpl::getInstance()->render('mails/resignationConfirmation', [
                    'fullName' => $m->get('fullName'),
                    'id' => $m->get('id'),
                ]);
                $m->sendEmail('Bestätigung deiner Austrittserklärung', $text);
            }
        }
    }

    private function delete(Mitglied $m): Response {
        if (AuthService::ist($m->get('id'))) {
            throw new AccessDeniedException('Du kannst dich nicht selbst löschen!');
        }

        $m->delete();

        $admin = Mitglied::lade(AuthService::getUID());

        $mailText = Tpl::getInstance()->render('mails/MvEdit-Info-Mitglied-Geloescht', [
            'adminName' => $admin->get('fullName'),
            'adminId' => $admin->get('id'),
            'adminUsername' => $admin->get('username'),
            'deletedName' => $m->get('fullName'),
            'deletedId' => $m->get('id'),
            'deletedUsername' => $m->get('username'),
            'deletedEmail' => $m->get('email'),
        ]);

        // Alle Mitglieder der Mitgliederbetreuung (mvedit) informieren
        $ids = Ldap::getInstance()->getIdsByGroup('mvedit');
        foreach ($ids as $id) {
            $user = Mitglied::lade($id);
            if ($user === null) {
                continue;
            }
            try {
                $user->sendEmail('Information über gelöschtes Mitglied', $mailText);
            } catch (\RuntimeException $e) {
                throw $e;
            }
        }

        return $this->showMessage("Die Daten wurden aus der Mitgliederdatenbank gelöscht.");
    }

    private function update(Mitglied $m): Response {
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_strings_ungeprueft, 'string'));
        foreach ($input as $key=>$value) {
            $m->set($key, $value);
        }
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_bool_ungeprueft, 'bool'));
        foreach ($input as $key=>$value) {
            $m->set($key, $value);
        }

        $beschaeftigung = $this->validatePayload(['beschaeftigung' => 'string'])['beschaeftigung'];
        if (!in_array($beschaeftigung, ['Schueler', 'Hochschulstudent', 'Doktorand', 'Berufstaetig', 'Sonstiges'], true)) {
            throw new InvalidUserDataException("Wert für beschaeftigung ungültig.");
        }
        $m->set('beschaeftigung', $beschaeftigung);

        $this->updatePassword($m);
        $this->updateEmail($m);
        $this->updateProfilePicture($m);

        if ($this->request->getPayload()->getBoolean('bildLoeschen')) {
            $this->removeProfilePicture($m);
        }

        // nur für die Mitgliederverwaltung
        if (AuthService::hatRecht('mvedit')) {
            $this->updateAdmin($m);

            if ($this->request->getPayload()->getBoolean('delete')) {
                return $this->delete($m);
            }
        }

        // Gruppen aktualisieren
        if (AuthService::hatRecht('rechte')) {
            $this->updateGroups($m);
        }

        // Austritt erklären
        $this->handleResign($m);

        // Speichern
        $m->set('db_modified', 'now');
        $m->set('db_modified_user_id', AuthService::getUID());
        $this->setTemplateVariable('data_saved_info', true);
        $m->save();

        // und neu laden (insb. beim Löschen wichtig, sonst müssten alls Keys einzeln zurückgesetzt werden)
        return $this->edit(Mitglied::lade($m->get('id')));
    }

    private function storeEmail(Mitglied $m, string $email): void {
        $oldMail = $m->get('email');

        try {
            $m->setEmail($email);
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Diese E-Mail-Adresse ist bereits bei einem anderen Mitglied eingetragen.');
        }

        $m->save();

        $text = Tpl::getInstance()->render('mails/email-changed', [
            'fullName' => $m->get('fullName'),
            'email' => $email,
        ]);
        EmailService::getInstance()->send($oldMail, 'E-Mail-Änderung', $text);

        $this->setTemplateVariable('email_changed', true);
    }

    private function emailAuth(string $token): Response {
        try {
            Token::decode($token, function ($data) use (&$m, &$email) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $email = $data[2];
                $m = Mitglied::lade($data[1]);
                return $m->get('email');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }

        AuthService::login($m->get('id'));

        $this->storeEmail($m, $email);

        return $this->edit($m);
    }
}
