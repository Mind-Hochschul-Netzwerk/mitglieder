<?php
namespace App\Domain\Controller;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Tpl;
use App\Auth;
use App\Mitglied;
use App\Service\Ldap;
use App\Service\EmailService;

/**
 * Aufnahme neuer Mitglieder
 */
class AufnahmeController
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
    private $action = '';

    private $username = '';
    private $password = '';
    private $readyToSave = true;

    public function __construct()
    {
    }

    public function run(): void
    {
        ensure($_REQUEST['token'], ENSURE_STRING) or die('token missing');
        $this->token = $_REQUEST['token'];
        Tpl::set('token', $this->token);

        Tpl::set('htmlTitle', 'Benutzerkonto aktivieren');
        Tpl::set('title', 'Benutzerkonto aktivieren');
        Tpl::set('navId', 'start');

        $this->requestData();
        $this->checkEmailUsed();

        if (isset($_REQUEST['username'])) {
            $this->checkEnteredUsername();
            $this->checkEnteredPassword();
            if ($this->readyToSave) {
                $this->save();
                Tpl::pause();
                header('Location: /bearbeiten.php?tab=profilbild');
                exit;
            }
        }

        $this->showForm();
    }

    private function requestData(): void
    {
        $curl = curl_init('http://aufnahme:8080/get-antrag.php?action=data&token=' . $this->token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        $this->data = json_decode($response, associative: true);

        if ($this->data === null) {
            Tpl::render('AufnahmeController/invalid');
            exit;
        }
    }

    private function isEmailUsed(): bool
    {
        return (Mitglied::getIdByEmail($this->data['user_email']) !== null);
    }

    private function checkEmailUsed(): void
    {
        if ($this->isEmailUsed()) {
            Tpl::set('email', $this->data['user_email']);
            Tpl::render('AufnahmeController/emailUsed');
            exit;
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

        for ($n = 1; !Mitglied::isUsernameAvailable($username); ++$n) {
            $username = $username0 . $n;
        }

        return $username;
    }

    private function checkEnteredUsername(): void
    {
        ensure($_REQUEST['username'], ENSURE_STRING);

        $this->username = trim($_REQUEST['username']);

        if (!($this->username)) {
            $this->readyToSave = false;
            Tpl::set('usernameMissing', true);
            return;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9\-_.]*$/', $this->username)) {
            $this->readyToSave = false;
            Tpl::set('usernameInvalid', true);
            return;
        }

        if (!Mitglied::isUsernameAvailable($this->username)) {
            $this->readyToSave = false;
            Tpl::set('usernameUsed', true);
            return;
        }
    }

    private function checkEnteredPassword(): void
    {
        ensure($_REQUEST['password'], ENSURE_STRING);
        ensure($_REQUEST['password2'], ENSURE_STRING);

        $this->password = $_REQUEST['password'];

        if (!($this->password)) {
            $this->readyToSave = false;
            Tpl::set('passwordMissing', true);
            return;
        }

        if ($this->password !== $_REQUEST['password2']) {
            $this->readyToSave = false;
            Tpl::set('passwordMismatch', true);
            return;
        }
    }

    private function showForm(): void
    {
        Tpl::set('username', $this->username ? $this->username : $this->suggestUsername());
        Tpl::set('data', $this->data);
        Tpl::render('AufnahmeController/form');
        exit;
    }

    private function save(): void
    {
        $m = Mitglied::neu($this->username, $this->password, $this->data['user_email']);

        foreach (self::MAP as $key_neu => $key_alt) {
            if (!isset($this->data[$key_alt])) {
                throw new \RuntimeException($key_alt . ' is missing');
            }
            $m->set($key_neu, $this->data[$key_alt]);
        }

        if (isset($this->data['mhn_geburtstag'])) {
            $m->set('geburtstag', $this->data['mhn_geburtstag']);
        }

        $this->processAccessFlags($m);

        $m->save();

        $ldap = Ldap::getInstance();
        $ldap->addUserToGroup($this->username, 'alleMitglieder');
        $ldap->addUserToGroup($this->username, 'listen');

        $curl = curl_init('http://aufnahme:8080/get-antrag.php?action=finish&token=' . $this->token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);

        Auth::logIn($m->get('id')); // Status neu laden

        $this->sendMailToActivationTeam($m);
    }

    private function processAccessFlags(Mitglied $m)
    {
        ensure($_REQUEST['sichtbarkeit_adresse'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_email'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_telefon'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_geburtstag'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_mensa_nr'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_studium'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['sichtbarkeit_beruf'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['uebernahme_titel'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['uebernahme_homepage'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['uebernahme_zweitwohnsitz'], ENSURE_INT_GTEQ, 0);
        ensure($_REQUEST['uebernahme_interessen'], ENSURE_INT_GTEQ, 0);

        $m->set('sichtbarkeit_strasse', $_REQUEST['sichtbarkeit_adresse'] === 1);
        $m->set('sichtbarkeit_adresszusatz', $_REQUEST['sichtbarkeit_adresse'] === 1);
        $m->set('sichtbarkeit_plz_ort', $_REQUEST['sichtbarkeit_adresse'] >= 1);
        $m->set('sichtbarkeit_land', $_REQUEST['sichtbarkeit_adresse'] >= 1);
        $m->set('sichtbarkeit_email', (bool) $_REQUEST['sichtbarkeit_email']);
        $m->set('sichtbarkeit_geburtstag', (bool) $_REQUEST['sichtbarkeit_geburtstag']);
        $m->set('sichtbarkeit_mensa_nr', (bool) $_REQUEST['sichtbarkeit_mensa_nr']);
        $m->set('sichtbarkeit_telefon', (bool) $_REQUEST['sichtbarkeit_telefon']);
        $m->set('sichtbarkeit_beschaeftigung', (bool) $_REQUEST['sichtbarkeit_beruf']);
        $m->set('sichtbarkeit_beruf', (bool) $_REQUEST['sichtbarkeit_beruf']);
        $m->set('sichtbarkeit_studienort', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_studienfach', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_unityp', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_schwerpunkt', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_nebenfach', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_abschluss', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_zweitstudium', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_hochschulaktivitaeten', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_stipendien', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_auslandsaufenthalte', (bool) $_REQUEST['sichtbarkeit_studium']);
        $m->set('sichtbarkeit_praktika', (bool) $_REQUEST['sichtbarkeit_studium']);

        if (!$_REQUEST['uebernahme_titel']) {
            $m->set('titel', '');
        }
        if ($_REQUEST['uebernahme_zweitwohnsitz'] !== 1) {
            $m->set('strasse2', '');
            $m->set('adresszusatz2', '');
        }
        if ($_REQUEST['uebernahme_zweitwohnsitz'] === 0) {
            $m->set('plz2', '');
            $m->set('ort2', '');
            $m->set('land2', '');
        }
        if (!$_REQUEST['uebernahme_homepage']) {
            $m->set('homepage', '');
        }
        if (!$_REQUEST['uebernahme_interessen']) {
            $m->set('sprachen', '');
            $m->set('hobbys', '');
            $m->set('interessen', '');
            $m->set('hochschulaktivitaeten', '');
        }
    }

    /**
     * @throws RuntimeException on error
     */
    private function sendMailToActivationTeam(Mitglied $newMember): void
    {
        Tpl::set('id', $newMember->get('id'));
        Tpl::set('fullName', $newMember->get('fullName'));
        Tpl::set('email', $newMember->get('email'));
        $text = Tpl::render('mails/account-activated', false);

        $ids = Ldap::getInstance()->getIdsByGroup('aktivierung');
        foreach ($ids as $id) {
            $user = Mitglied::lade($id, false);
            if ($user === null) {
                continue;
            }
            $user->sendEmail('Neues Mitglied', $text);
        }
    }
}
