<?php
namespace App\Controller;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Controller\Exception\InvalidUserDataException;
use App\Model\User;
use App\Repository\UserRepository;
use App\Service\Attribute\Route;
use App\Service\AuthService;
use App\Service\Ldap;
use App\Service\Tpl;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aufnahme neuer Mitglieder
 */
class AufnahmeController extends Controller
{
    const MAP = [
        'titel' => 'mhn_titel',
        'vorname' => 'mhn_vorname',
        'nachname' => 'mhn_nachname',
        'mensa_nr' => 'mhn_mensa_nr',
        'strasse' => 'mhn_ws_strasse',
        'adresszusatz' => 'mhn_ws_zusatz',
        'plz' => 'mhn_ws_plz',
        'ort' => 'mhn_ws_ort',
        'land' => 'mhn_ws_land',
        'strasse2' => 'mhn_zws_strasse',
        'adresszusatz2' => 'mhn_zws_zusatz',
        'plz2' => 'mhn_zws_plz',
        'ort2' => 'mhn_zws_ort',
        'land2' => 'mhn_zws_land',
        'telefon' => 'mhn_telefon',
        'homepage' => 'mhn_homepage',
        'sprachen' => 'mhn_sprachen',
        'hobbys' => 'mhn_hobbies',
        'interessen' => 'mhn_interessen',
        'studienfach' => 'mhn_studienfach',
        'hochschulaktivitaeten' => 'mhn_hochschulaktivitaet', // mittlerweile ehrenamtliches Engagement
        'stipendien' => 'mhn_stipendien',
        'auslandsaufenthalte' => 'mhn_ausland',
        'praktika' => 'mhn_praktika',
        'beruf' => 'mhn_beruf',
        'kenntnisnahme_datenverarbeitung_aufnahme' => 'kenntnisnahme_datenverarbeitung',
        'kenntnisnahme_datenverarbeitung_aufnahme_text' => 'kenntnisnahme_datenverarbeitung_text',
        'einwilligung_datenverarbeitung_aufnahme' => 'einwilligung_datenverarbeitung',
        'einwilligung_datenverarbeitung_aufnahme_text' => 'einwilligung_datenverarbeitung_text',
        'auskunft_studiengang' => 'mhn_auskunft_studiengang',
        'auskunft_stipendien' => 'mhn_auskunft_stipendien',
        'auskunft_auslandsaufenthalte' => 'mhn_auskunft_ausland',
        'auskunft_praktika' => 'mhn_auskunft_praktika',
        'auskunft_beruf' => 'mhn_auskunft_beruf',
        'mentoring' => 'mhn_mentoring',
        'aufgabe_orte' => 'mhn_aufgabe_orte',
        'aufgabe_vortrag' => 'mhn_aufgabe_vortrag',
        'aufgabe_koord' => 'mhn_aufgabe_koord',
        'aufgabe_computer' => 'mhn_aufgabe_computer',
        'aufgabe_texte_schreiben' => 'mhn_aufgabe_texte_schreiben',
        'aufgabe_ansprechpartner' => 'mhn_aufgabe_ansprechpartner',
        'aufgabe_hilfe' => 'mhn_aufgabe_hilfe',
    ];

   /* Felder, die nicht gesetzt werden (Default siehe Mitglied::)
     'aufgabe_sonstiges_beschreibung'=>'',
     'sichtbarkeit_*',
     'aufgabe_ma' => '',
     'aufgabe_graphisch' => '',
     'aufgabe_texte_lesen' => '',
     'aufgabe_vermittlung' => '',
     'aufgabe_sonstiges' => '',
    */

    private $token = '';
    private $data = [];

    private $username = '';
    private $password = '';
    private $readyToSave = true;

    #[Route('GET /aufnahme?token={token}')]
    public function show(string $token): Response
    {
        $this->prepare($token);
        return $this->showForm();
    }

    #[Route('POST /aufnahme?token={token}')]
    public function submit(string $token): Response {
        $this->prepare($token);
        $this->checkEnteredUsername();
        $this->checkEnteredPassword();
        if ($this->readyToSave) {
            $this->save();
            return $this->redirect('/user/_/edit/?tab=profilbild');
        }
        return $this->showForm();
    }

    private  function prepare(string $token): void
    {
        $this->token = $token;
        $this->setTemplateVariable('token', $this->token);

        $this->requestData();
        $this->checkEmailUsed();
    }

    private function requestData(): void
    {
        $curl = curl_init('http://aufnahme:8080/get-antrag.php?action=data&token=' . $this->token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        $this->data = json_decode($response, associative: true);

        if ($this->data === null) {
            throw new InvalidUserDataException('Der Link ist ungültig. Wurde der Zugang schon aktiviert?');
        }
    }

    private function isEmailUsed(): bool
    {
        return (UserRepository::getInstance()->getIdByEmail($this->data['user_email']) !== null);
    }

    private function checkEmailUsed(): void
    {
        $this->setTemplateVariable('email', $this->data['user_email']);
        if ($this->isEmailUsed()) {
            $this->readyToSave = false;
            $this->setTemplateVariable('emailUsed', true);
        }
    }

    private function suggestUsername(): string
    {
        // neuen Benutzernamen als Vorschlag generieren
        $username0 = strtolower(trim($this->data['mhn_vorname']) . '.' . trim($this->data['mhn_nachname']));
        $username0 = strtr($username0, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            'é' => 'e',
            'ç' => 'c',
            'ǧ' => 'g',
            "'" => '-',
        ]);
        $username0 = preg_replace('/[^a-zA-Z0-9\-_\.]/', '.', $username0);
        $username0 = substr($username0, 0, 255);
        $username = $username0;

        for ($n = 1; !UserRepository::getInstance()->isUsernameAvailable($username); ++$n) {
            $username = $username0 . $n;
        }

        return $username;
    }

    private function checkEnteredUsername(): void
    {
        $this->username = $this->validatePayload(['username' => 'required string'])['username'];

        if (!($this->username)) {
            $this->readyToSave = false;
            $this->setTemplateVariable('usernameMissing', true);
            return;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9\-_.]*$/', $this->username)) {
            $this->readyToSave = false;
            $this->setTemplateVariable('usernameInvalid', true);
            return;
        }

        if (!UserRepository::getInstance()->isUsernameAvailable($this->username)) {
            $this->readyToSave = false;
            $this->setTemplateVariable('usernameUsed', true);
            return;
        }
    }

    private function checkEnteredPassword(): void
    {
        $input = $this->validatePayload([
            'password' => 'required string untrimmed',
            'password2' => 'required string untrimmed',
        ]);

        $this->password = $input['password'];

        if (!($this->password)) {
            $this->readyToSave = false;
            $this->setTemplateVariable('passwordMissing', true);
            return;
        }

        if ($this->password !== $input['password2']) {
            $this->readyToSave = false;
            $this->setTemplateVariable('passwordMismatch', true);
            return;
        }
    }

    private function showForm(): Response
    {
        return $this->render('AufnahmeController/form', [
            'username' => $this->username ? $this->username : $this->suggestUsername(),
            'data' => $this->data,
        ]);
    }

    private function save(): void
    {
        $user = new User($this->username, $this->password, $this->data['user_email']);

        foreach (self::MAP as $key_neu => $key_alt) {
            if (!isset($this->data[$key_alt])) {
                throw new \RuntimeException($key_alt . ' is missing');
            }
            $user->set($key_neu, $this->data[$key_alt]);
        }

        if (isset($this->data['mhn_geburtstag'])) {
            $user->set('geburtstag', $this->data['mhn_geburtstag']);
        }

        $this->processAccessFlags($user);

        UserRepository::getInstance()->save($user);

        $ldap = Ldap::getInstance();
        $ldap->addUserToGroup($this->username, 'alleMitglieder');
        $ldap->addUserToGroup($this->username, 'listen');

        $curl = curl_init('http://aufnahme:8080/get-antrag.php?action=finish&token=' . $this->token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);

        AuthService::logIn($user->get('id')); // Status neu laden

        $this->sendMailToActivationTeam($user);
    }

    private function processAccessFlags(User $user)
    {
        $input = $this->validatePayload([
            'sichtbarkeit_adresse' => 'uint',
            'sichtbarkeit_email' => 'uint',
            'sichtbarkeit_telefon' => 'uint',
            'sichtbarkeit_geburtstag' => 'uint',
            'sichtbarkeit_mensa_nr' => 'uint',
            'sichtbarkeit_studium' => 'uint',
            'sichtbarkeit_beruf' => 'uint',
            'uebernahme_titel' => 'uint',
            'uebernahme_homepage' => 'uint',
            'uebernahme_zweitwohnsitz' => 'uint',
            'uebernahme_interessen' => 'uint',
        ]);

        $user->set('sichtbarkeit_strasse', $input['sichtbarkeit_adresse'] === 1);
        $user->set('sichtbarkeit_adresszusatz', $input['sichtbarkeit_adresse'] === 1);
        $user->set('sichtbarkeit_plz_ort', $input['sichtbarkeit_adresse'] >= 1);
        $user->set('sichtbarkeit_land', $input['sichtbarkeit_adresse'] >= 1);
        $user->set('sichtbarkeit_email', (bool) $input['sichtbarkeit_email']);
        $user->set('sichtbarkeit_geburtstag', (bool) $input['sichtbarkeit_geburtstag']);
        $user->set('sichtbarkeit_mensa_nr', (bool) $input['sichtbarkeit_mensa_nr']);
        $user->set('sichtbarkeit_telefon', (bool) $input['sichtbarkeit_telefon']);
        $user->set('sichtbarkeit_beschaeftigung', (bool) $input['sichtbarkeit_beruf']);
        $user->set('sichtbarkeit_beruf', (bool) $input['sichtbarkeit_beruf']);
        $user->set('sichtbarkeit_studienort', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_studienfach', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_unityp', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_schwerpunkt', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_nebenfach', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_abschluss', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_zweitstudium', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_hochschulaktivitaeten', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_stipendien', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_auslandsaufenthalte', (bool) $input['sichtbarkeit_studium']);
        $user->set('sichtbarkeit_praktika', (bool) $input['sichtbarkeit_studium']);

        if (!$input['uebernahme_titel']) {
            $user->set('titel', '');
        }
        if ($input['uebernahme_zweitwohnsitz'] !== 1) {
            $user->set('strasse2', '');
            $user->set('adresszusatz2', '');
        }
        if ($input['uebernahme_zweitwohnsitz'] === 0) {
            $user->set('plz2', '');
            $user->set('ort2', '');
            $user->set('land2', '');
        }
        if (!$input['uebernahme_homepage']) {
            $user->set('homepage', '');
        }
        if (!$input['uebernahme_interessen']) {
            $user->set('sprachen', '');
            $user->set('hobbys', '');
            $user->set('interessen', '');
            $user->set('hochschulaktivitaeten', '');
        }
    }

    /**
     * @throws RuntimeException on error
     */
    private function sendMailToActivationTeam(User $newUser): void
    {
        $text = Tpl::getInstance()->render('mails/account-activated', [
            'id' => $newUser->get('id'),
            'fullName' => $newUser->get('fullName'),
            'email' => $newUser->get('email'),
        ]);

        $ids = Ldap::getInstance()->getIdsByGroup('aktivierung');
        foreach ($ids as $id) {
            $user = UserRepository::getInstance()->findOneById($id);
            if ($user === null) {
                continue;
            }
            $user->sendEmail('Neues Mitglied', $text);
        }
    }
}
