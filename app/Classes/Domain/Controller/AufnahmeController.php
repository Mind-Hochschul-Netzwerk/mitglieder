<?php
namespace MHN\Mitglieder\Domain\Controller;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\Service\EmailService;

/**
 * Aufnahme neuer Mitglieder
 */
class AufnahmeController
{
    const MAP_STRINGS = [
        'titel' => 'mhn_titel',
        'vorname' => 'mhn_vorname',
        'nachname' => 'mhn_nachname',
        'geschlecht' => 'mhn_geschlecht',
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
        'mobil' => 'mhn_mobil',
        'homepage' => 'mhn_homepage',
        'sprachen' => 'mhn_sprachen',
        'hobbys' => 'mhn_hobbies',
        'interessen' => 'mhn_interessen',
        'studienfach' => 'mhn_studienfach',
        'hochschulaktivitaeten' => 'mhn_hochschulaktivitaet',
        'stipendien' => 'mhn_stipendien',
        'auslandsaufenthalte' => 'mhn_ausland',
        'praktika' => 'mhn_praktika',
        'beruf' => 'mhn_beruf',
        'kenntnisnahme_datenverarbeitung_aufnahme' => 'kenntnisnahme_datenverarbeitung',
        'kenntnisnahme_datenverarbeitung_aufnahme_text' => 'kenntnisnahme_datenverarbeitung_text',
        'einwilligung_datenverarbeitung_aufnahme' => 'einwilligung_datenverarbeitung',
        'einwilligung_datenverarbeitung_aufnahme_text' => 'einwilligung_datenverarbeitung_text',
    ];

    const MAP_BOOL = [
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
                header('Location: /bearbeiten.php');
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

        $this->data = json_decode($response);
        if ($this->data === null) {
            Tpl::render('AufnahmeController/invalid');
            exit;
        }
    }

    private function isEmailUsed(): bool
    {
        return (Mitglied::getIdByEmail($this->data->user_email) !== null);
    }

    private function checkEmailUsed(): void
    {
        if ($this->isEmailUsed()) {
            Tpl::set('email', $data->user_email);
            Tpl::render('AufnahmeController/emailUsed');
            exit;
        }
    }

    private function suggestUsername(): string
    {
        // neuen Benutzernamen als Vorschlag generieren
        $username0 = strtolower(trim($this->data->mhn_vorname) . '.' . trim($this->data->mhn_nachname));
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
        Tpl::set('vorname', $this->data->mhn_vorname);
        Tpl::render('AufnahmeController/form');
        exit;
    }

    private function save(): void
    {
        $m = Mitglied::neu($this->username, $this->password, $this->data->user_email);

        foreach (self::MAP_STRINGS as $key_neu => $key_alt) {
            if (isset($this->data->$key_alt)) {
                $m->set($key_neu, $this->data->$key_alt);
            }
        }

        foreach (self::MAP_BOOL as $key_neu => $key_alt) {
            if (!isset($this->data->$key_alt)) {
                $m->set($key_neu, false);
            } else {
                $m->set($key_neu, $this->data->$key_alt === 'j');
            }
        }

        if (!empty($this->data->mhn_ws_hausnr)) {
            $m->set('strasse', $m->get('strasse') . ' ' . $this->data->mhn_ws_hausnr);
        }

        if (!empty($this->data->mhn_zws_hausnr)) {
            $m->set('strasse2', $m->get('strasse2') . ' ' . $this->data->mhn_zws_hausnr);
        }

        if (isset($this->data->mhn_geburtstag)) {
            $m->set('geburtstag', $this->data->mhn_geburtstag);
        }

        $m->set('aktiviert', true);

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
