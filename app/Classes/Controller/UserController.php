<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use App\Service\ImageResizer;
use App\Service\Ldap;
use App\Service\Tpl;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\AccessDeniedException;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function __construct(
        protected Request $request,
        private CurrentUser $currentUser
    )
    {
    }

    #[Route('GET /user', allow: ['loggedIn' => true])]
    public function showSelf(): Response {
        return $this->redirect('/user/self');
    }

    #[Route('GET /user/{\d+:id=>user}', allow: ['loggedIn' => true])]
    #[Route('GET /user/{username=>user}', allow: ['loggedIn' => true])]
    public function show(User $user, UserAgreementRepository $userAgreementRepository): Response {
        $db_modified = $user->get('db_modified');
        $isAdmin = $this->currentUser->hasRole('mvread');

        $templateVars = [
            'htmlTitle' => $user->get('fullName'),
            'fullName' => $user->get('fullName'),
            'title' => $user->get('fullName'),
        ];
        if ($db_modified) {
            $templateVars['title'] .= ' <small>Stand: '. $user->get('db_modified')->format('d.m.Y') . '</small>';
        }
        if ($this->currentUser->get('id') === $user->get('id') || $this->currentUser->hasRole('mvedit')) {
            $templateVars['title'] .= ' <small><a href="' . $user->get('bearbeitenUrl') . '"><span class="glyphicon glyphicon-pencil"></span> Daten bearbeiten</a></small>';
        }

        // generell: alle Daten kopieren
        foreach (array_keys(User::felder) as $feld) {
            $templateVars[$feld] = $user->get($feld);
        }

        // Dann die sichtgeschützten Felder gesondert behandeln, damit das Template möglichst frei von Logik bleiben kann
        if (!$isAdmin) {
            foreach (['email', 'geburtstag', 'mensa_nr', 'strasse', 'adresszusatz', 'land', 'telefon',
                'beschaeftigung', 'studienort', 'studienfach', 'unityp', 'schwerpunkt', 'nebenfach', 'abschluss',
                'zweitstudium', 'nebenfach', 'abschluss', 'zweitstudium', 'hochschulaktivitaeten', 'stipendien',
                'auslandsaufenthalte', 'praktika', 'beruf'] as $feld) {
                if (!$user->get('sichtbarkeit_' . $feld)) {
                    $templateVars[$feld] = null;
                }
            }
            if (!$user->get('sichtbarkeit_plz_ort')) {
                $templateVars['plz'] = null;
                $templateVars['ort'] = null;
            }
        }

        // Überprüfen, ob die Homepage das korrekte Format hat. ggf. http:// ergänzen
        $homepage = $user->get('homepage');
        if (!preg_match('=^https?://=i', $homepage)) {
            $homepage = 'http://' . $homepage;
        }
        if (!preg_match('=^https?://(?P<user>[^@]*@)?(?P<host>[\w\.0-9-]+)(?P<port>:[0-9]+)?(?<query>/.*)?$=i', $homepage)) {
            $homepage = '';
        }
        $templateVars['homepage'] = $homepage;

        return $this->render('UserController/profil', [
            ...$templateVars,
            'email' => $user->get('email'),
            'datenschutzverpflichtung' => $userAgreementRepository->findLatestByUserAndName($user, 'datenschutzverpflichtung'),
        ]);
    }

    #[Route('GET /user/{username=>user}/edit', allow: ['role' => 'mvedit', 'id' => '$user->get("id")'])]
    public function edit(User $user): Response {
        $templateVars = [];

        $tab = $this->request->query->getString('tab');
        if (in_array($tab, ['basisdaten', 'uebermich', 'ausbildungberuf', 'profilbild', 'settings', 'account'])) {
            $templateVars['active_pane'] = $tab;
        }

        if ($this->currentUser->get('id') !==  $user->get('id') && !$this->currentUser->hasRole('mvedit')) {
            throw new AccessDeniedException();
        }

        foreach (array_keys(User::felder) as $feld) {
            $templateVars[$feld] = $user->get($feld);
        }
        $templateVars += [
            'fullName' => $user->get('fullName'),
            'dateOfJoining' => $user->get('dateOfJoining'),
            'groups' => implode(', ', $user->getGroups()),
            'db_modified_user' => UserRepository::getInstance()->findOneById((int)$user->get('db_modified_user_id')),
            'isAdmin' => $this->currentUser->hasRole('mvedit'),
            'isSuperAdmin' => $this->currentUser->hasRole('rechte'),
            'isSelf' => $this->currentUser->get('id') ===  $user->get('id'),
        ];

        return $this->render('UserController/bearbeiten', [
            ...$templateVars,
            'email' => $user->get('email'),
            'bildLoeschen' => false,
            'delete' => false,
            'resign' => (bool)$user->get('resignation'),
            'password' => '',
            'new_password' => '',
            'new_password2' => '',
        ]);
    }

    private function updatePassword(User $user): void {
        $input = $this->validatePayload([
            'new_password' => 'string untrimmed',
            'new_password2' => 'string untrimmed',
            'password' => 'string untrimmed',
        ]);
        if ($input['new_password'] && !$input['new_password2'] && !$input['password'] && $this->currentUser->checkPassword($input['new_password'])) {
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
            if ($this->currentUser->hasRole('mvedit') && $this->currentUser->get('id') !==  $user->get('id')) {
                $user->setPassword($input['new_password']);
            } elseif ($this->currentUser->checkPassword($input['password'])) {
                $user->setPassword($input['new_password']);
            } else {
                $this->setTemplateVariable('old_password_error', true);
            }
        }
    }

    private function updateEmail(User $user): void {
        $email = $this->validatePayload(['email' => 'string'])['email'];

        if ($user->get('email') === $email) {
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_+&*-]+(?:\.[a-zA-Z0-9_+&*-]+)*@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,50}$/', $email)) { // siehe https://www.owasp.org/index.php/OWASP_Validation_Regex_Repository
            $this->setTemplateVariable('email_error', true);
            return;
        }

        if ($this->currentUser->hasRole('mvedit')) {
            $this->storeEmail($user, $email);
        } else {
            $this->setTemplateVariable('email_auth_info', true);
            $token = Token::encode([time(), $user->get('id'), $email], $user->get('email'), getenv('TOKEN_KEY'));
            $text = Tpl::getInstance()->render('mails/email-auth', ['token' => $token], $subject);
            EmailService::getInstance()->send($email, $subject, $text);
        }
    }

    private function updateAdmin(User $user): void {
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_strings_admin, 'string'));
        foreach ($input as $key=>$value) {
            $user->set($key, $value);
        }

        $input = $this->validatePayload([
            'geburtstag' => 'date',
        ]);
        foreach ($input as $key=>$value) {
            if ($value === '0000-00-00') {
                $value = null;
            }
            $user->set($key, $value);
        }
    }

    private function updateProfilePicture(User $user): void {
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
        $fileName = $user->get('id') . '-' . md5_file($file->getPathname()) . '.' . $type;

        // Datei und Thumbnail erstellen
        list($size_x, $size_y) = ImageResizer::resize($file->getPathname(), 'profilbilder/' . $fileName, $type, $type, self::profilbildMaxWidth, self::profilbildMaxHeight);
        ImageResizer::resize($file->getPathname(), 'profilbilder/thumbnail-' . $fileName, $type, $type, self::thumbnailMaxWidth, self::thumbnailMaxHeight);

        // altes Profilbild löschen
        if ($user->get('profilbild') && is_file('profilbilder/' . $user->get('profilbild'))) {
            unlink('profilbilder/' . $user->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $user->get('profilbild'));
        }

        $user->set('profilbild', $fileName);
        $user->set('profilbild_x', $size_x);
        $user->set('profilbild_y', $size_y);
    }

    private function removeProfilePicture(User $user): void {
        if ($user->get('profilbild') && is_file('profilbilder/' . $user->get('profilbild'))) {
            unlink('profilbilder/' . $user->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $user->get('profilbild'));
        }
        $user->set('profilbild', '');
    }

    private function updateGroups(User $user): void {
        $input = $this->validatePayload(['groups' => 'string']);
        $groups = array_filter(array_unique(preg_split('/[\s,]+/', $input['groups'])));

        if ($this->currentUser->get('id') ===  $user->get('id') && (!in_array('rechte', $groups, true))) {
            throw new AccessDeniedException('Du kannst dir das Recht zur Rechtverwaltung nicht selbst entziehen.');
        }

        try {
            $user->setGroups($groups);
        } catch (\Exception $e) {
            $this->setTemplateVariable('errorMessage', 'Beim Setzen der Gruppen ist ein Fehler aufgetreten.');
        }
    }

    private function handleResign(User $user) {
        $password = $this->request->getPayload()->getString('resignPassword');
        if ($password) {
            if (!$this->currentUser->checkPassword($password)) {
                $this->setTemplateVariable('errorMessage', 'Das eingegebene Passwort ist nicht korrekt.');
            } else {
                $user->set('resignation', 'now');
                $text = Tpl::getInstance()->render('mails/resignation', [
                    'fullName' => $user->get('fullName'),
                    'id' => $user->get('id'),
                ], $subject);
                EmailService::getInstance()->send('vorstand@mind-hochschul-netzwerk.de', $subject, $text);
                EmailService::getInstance()->send('mitgliederbetreuung@mind-hochschul-netzwerk.de', $subject, $text);
                $text = Tpl::getInstance()->render('mails/resignationConfirmation', [
                    'fullName' => $user->get('fullName'),
                    'id' => $user->get('id'),
                ], $subject);
                $user->sendEmail($subject, $text);
            }
        } elseif ($this->currentUser->hasRole('mvedit')) {
            $resignOld = $user->get('resignation') !== null;
            $resignNew = $this->request->getPayload()->getBoolean('resign');
            if ($resignOld && !$resignNew) {
                $user->set('resignation', null);
            } elseif (!$resignOld && $resignNew) {
                $user->set('resignation', 'now');
                $text = Tpl::getInstance()->render('mails/resignation', [
                    'adminFullName' => $this->currentUser->get('fullName'),
                    'fullName' => $user->get('fullName'),
                    'id' => $user->get('id'),
                ], $subject);
                EmailService::getInstance()->send('vorstand@mind-hochschul-netzwerk.de', $subject, $text);
                EmailService::getInstance()->send('mitgliederbetreuung@mind-hochschul-netzwerk.de', $subject, $text);
                $text = Tpl::getInstance()->render('mails/resignationConfirmation', [
                    'fullName' => $user->get('fullName'),
                    'id' => $user->get('id'),
                ], $subject);
                $user->sendEmail($subject, $text);
            }
        }
    }

    private function delete(User $user): Response {
        if ($this->currentUser->get('id') ===  $user->get('id')) {
            throw new AccessDeniedException('Du kannst dich nicht selbst löschen!');
        }

        UserRepository::getInstance()->delete($user);

        $mailText = Tpl::getInstance()->render('mails/MvEdit-Info-Mitglied-Geloescht', [
            'adminName' => $this->currentUser->get('fullName'),
            'adminId' => $this->currentUser->get('id'),
            'adminUsername' => $this->currentUser->get('username'),
            'deletedName' => $user->get('fullName'),
            'deletedId' => $user->get('id'),
            'deletedUsername' => $user->get('username'),
            'deletedEmail' => $user->get('email'),
        ], $subject);

        // Alle Mitglieder der Mitgliederbetreuung (mvedit) informieren
        $ids = Ldap::getInstance()->getIdsByGroup('mvedit');
        foreach ($ids as $id) {
            $user = UserRepository::getInstance()->findOneById($id);
            if ($user === null) {
                continue;
            }
            try {
                $user->sendEmail($subject, $mailText);
            } catch (\RuntimeException $e) {
                throw $e;
            }
        }

        return $this->showMessage("Bestätigung", "Die Daten wurden aus der Mitgliederdatenbank gelöscht.");
    }

    #[Route('POST /user/{username=>user}/edit', allow: ['role' => 'mvedit', 'id' => '$user->get("id")'])]
    public function update(User $user): Response {
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_strings_ungeprueft, 'string'));
        foreach ($input as $key=>$value) {
            $user->set($key, $value);
        }
        $input = $this->validatePayload(array_fill_keys(self::bearbeiten_bool_ungeprueft, 'bool'));
        foreach ($input as $key=>$value) {
            $user->set($key, $value);
        }

        $beschaeftigung = $this->validatePayload(['beschaeftigung' => 'string'])['beschaeftigung'];
        if (!in_array($beschaeftigung, ['Schueler', 'Hochschulstudent', 'Doktorand', 'Berufstaetig', 'Sonstiges'], true)) {
            throw new InvalidUserDataException("Wert für beschaeftigung ungültig.");
        }
        $user->set('beschaeftigung', $beschaeftigung);

        $this->updatePassword($user);
        $this->updateEmail($user);
        $this->updateProfilePicture($user);

        if ($this->request->getPayload()->getBoolean('bildLoeschen')) {
            $this->removeProfilePicture($user);
        }

        // nur für die Mitgliederverwaltung
        if ($this->currentUser->hasRole('mvedit')) {
            $this->updateAdmin($user);

            if ($this->request->getPayload()->getBoolean('delete')) {
                return $this->delete($user);
            }
        }

        // Gruppen aktualisieren
        if ($this->currentUser->hasRole('rechte')) {
            $this->updateGroups($user);
        }

        // Austritt erklären
        $this->handleResign($user);

        // Speichern
        $user->set('db_modified', 'now');
        $user->set('db_modified_user_id', CurrentUser::getInstance()->get('id'));
        $this->setTemplateVariable('data_saved_info', true);
        UserRepository::getInstance()->save($user);

        // und neu laden (insb. beim Löschen wichtig, sonst müssten alle Keys einzeln zurückgesetzt werden)
        // TODO: redirect. store messages in session
        return $this->edit(UserRepository::getInstance()->findOneById($user->get('id')));
    }

    private function storeEmail(User $user, string $email): void {
        $oldMail = $user->get('email');

        try {
            $user->setEmail($email);
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Diese E-Mail-Adresse ist bereits bei einem anderen Mitglied eingetragen.');
        }

        UserRepository::getInstance()->save($user);

        $text = Tpl::getInstance()->render('mails/email-changed', [
            'fullName' => $user->get('fullName'),
            'email' => $email,
        ], $subject);
        EmailService::getInstance()->send($oldMail, $subject, $text);

        $this->setTemplateVariable('email_changed', true);
    }

    #[Route('GET /email_auth?token={token}', allow: true)]
    public function emailAuth(string $token): Response {
        try {
            Token::decode($token, function ($data) use (&$user, &$email) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $email = $data[2];
                $user = UserRepository::getInstance()->findOneById($data[1]);
                return $user->get('email');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }

        $this->currentUser->logIn($user);

        $this->storeEmail($user, $email);

        return $this->edit($user);
    }
}
