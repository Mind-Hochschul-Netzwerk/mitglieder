<?php
namespace App\Controller;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\Enum\UserAgreementAction;
use App\Model\User;
use App\Model\UserAgreement;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use App\Service\Ldap;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Router\ServiceContainer;
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
        'ehrenamt' => 'mhn_hochschulaktivitaet',
        'stipendien' => 'mhn_stipendien',
        'auslandsaufenthalte' => 'mhn_ausland',
        'praktika' => 'mhn_praktika',
        'beruf' => 'mhn_beruf',
        'mentoring' => 'mhn_mentoring',
        'aufgabe_vortrag' => 'mhn_aufgabe_vortrag',
        'aufgabe_it' => 'mhn_aufgabe_computer',
        'aufgabe_oeffentlichkeitsarbeit' => 'mhn_aufgabe_texte_schreiben',
        'aufgabe_lokal' => 'mhn_aufgabe_ansprechpartner',
    ];

   /* Felder, die nicht gesetzt werden (Default siehe Mitglied::)
     sichtbarkeit_*
     fruehere_taetigkeiten
     aufgabe_koord
     aufgabe_ma
     aufgabe_veranstaltungen
     aufgabe_mentoringprogramm
     aufgabe_seminar
     aufgabe_finanzen
     aufgabe_mitgliederbetreuung
     aufgabe_seminarteam
     aufgabe_freitext
    */

    private string $token = '';
    private array $data = [];

    private string $username = '';
    private string $password = '';

    private bool $readyToSave = true;

    public function __construct(
        private EmailService $emailService,
        private Ldap $ldap,
        private UserRepository $userRepository,
        private AgreementRepository $agreementRepository,
        private UserAgreementRepository $userAgreementRepository,
    ) {}

    #[Route('GET /aufnahme?token={token}'), PublicAccess]
    public function show(string $token): Response
    {
        $this->prepare($token);
        return $this->showForm();
    }

    #[Route('POST /aufnahme?token={token}'), PublicAccess]
    public function submit(string $token): Response {
        $this->prepare($token);
        $this->checkEnteredUsername();
        $this->checkEnteredPassword();

        if ($this->readyToSave) {
            $this->save();
            return $this->redirect('/user/self/edit');
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
        $curl = curl_init('http://aufnahme:8080/onboarding/?token=' . $this->token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if ($response === false) {
            error_log('curl_exec: ' . curl_error($curl));
        }

        $this->data = json_decode($response, associative: true) ?? throw new InvalidUserDataException('Der Link ist ungültig. Wurde der Zugang schon aktiviert?');
    }

    private function isEmailUsed(): bool
    {
        return ($this->userRepository->getIdByEmail($this->data['user_email']) !== null);
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

        for ($n = 1; !$this->userRepository->isUsernameAvailable($username); ++$n) {
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

        if (!User::isUsernameAllowed($this->username)) {
            $this->readyToSave = false;
            $this->setTemplateVariable('usernameInvalid', true);
            return;
        }

        if (!$this->userRepository->isUsernameAvailable($this->username)) {
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

    #[Route('GET /aufnahme/test'), AllowIf(productionMode: false)]
    public function testForm(CurrentUser $currentUser): Response
    {
        return $this->render('AufnahmeController/form', ['mhn_vorname' => 'Max', 'username' => 'max.mustermann']);
    }

    private function showForm(): Response
    {
        $data = User::getDefaults();
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = !!($_POST[$key] ?? $value);
                continue;
            }
            $data[$key] = (string) $_POST[$key] ?: $value;
        }

        foreach (self::MAP as $key_neu => $key_alt) {
            if (!isset($this->data[$key_alt])) {
                throw new \RuntimeException($key_alt . ' is missing');
            }
            $data[$key_neu] = (string) $_POST[$key_neu] ?: $this->data[$key_alt];
        }
        $data['geburtstag'] = User::makeDateTime($this->data['mhn_geburtstag']);

        foreach (User::AUFGABEN as $key => $label) {
            $data[$key] = !!($_POST[$key] ?? false);
        }

        return $this->render('AufnahmeController/form', [
            'countryNames' => UserController::COUNTRY_NAMES,
            'aufgabenLabels' => User::AUFGABEN,
            ...$this->data,
            ...$data,
            'username' => (string) $_POST['username'] ?: $this->suggestUsername(),
            'password' => '',
            'password2' => '',
            'voreinstellung_adresse' => (int) ($_POST['voreinstellung_adresse'] ?? -1),
            'voreinstellung_email' => (int) ($_POST['voreinstellung_email'] ?? -1),
            'voreinstellung_geburtstag' => (int) ($_POST['voreinstellung_geburtstag'] ?? -1),
        ]);
    }

    private function save(): void
    {
        $user = new User(
            username: $this->username,
            password: $this->password,
            email: $this->data['user_email'],
            ldap: $this->ldap,
            userRepository: $this->userRepository,
        );

        $user->set('vorname', $this->data['mhn_vorname']);
        $user->set('nachname', $this->data['mhn_nachname']);

        $userController = new UserController(emailService: $this->emailService, userRepository: $this->userRepository);
        $userController->request = $this->request;
        $userController->copyFromForm($user);

        if (isset($this->data['mhn_geburtstag'])) {
            $user->set('geburtstag', $this->data['mhn_geburtstag']);
        }

        $this->userRepository->save($user);

        $this->saveUserAgreement($user, 'Kenntnisnahme', (int) $this->data['kenntnisnahme_datenverarbeitung_text'], new \DateTimeImmutable($this->data['kenntnisnahme_datenverarbeitung']));
        $this->saveUserAgreement($user, 'Einwilligung', (int) $this->data['einwilligung_datenverarbeitung_text'], new \DateTimeImmutable($this->data['einwilligung_datenverarbeitung']));

        $this->ldap->addUserToGroup($this->username, 'alleMitglieder');
        $this->ldap->addUserToGroup($this->username, 'listen');

        $curl = curl_init('http://aufnahme:8080/onboarding/?token=' . $this->token);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);

        $this->currentUser->logIn($user); // Status neu laden

        $this->sendMailToActivationTeam($user);
    }

    private function saveUserAgreement(User $user, string $name, int $version, \DateTimeInterface $timestamp): void
    {
        $agreement = $this->agreementRepository->findOneByNameAndVersion($name, $version) ?? throw \OutOfBoundsException("could not find agreement with name '$name' and version $version.");
        $userAgreement = new UserAgreement(
            user: $user,
            agreement: $agreement,
            action: UserAgreementAction::Accept,
            timestamp: $timestamp,
        );
        $this->userAgreementRepository->persist($userAgreement);
    }

    /**
     * @throws RuntimeException on error
     */
    private function sendMailToActivationTeam(User $newUser): void
    {
        $text = $this->renderToString('mails/account-activated', [
            'id' => $newUser->get('id'),
            'fullName' => $newUser->get('fullName'),
            'email' => $newUser->get('email'),
            'return' => $return = new \stdclass,
        ]);

        $this->emailService->sendToGroup('aktivierung', $return->subject, $text);
    }
}
