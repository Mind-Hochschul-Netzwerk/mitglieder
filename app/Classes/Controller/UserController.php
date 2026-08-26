<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\AccessDeniedException;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller {
    // Liste der vom Mitglied änderbaren Strings, deren Werte nicht geprüft werden
    /* @var string[] */
    private array $bearbeiten_strings_ungeprueft = [
        'titel',
        'mensa_nr',
        'strasse', 'adresszusatz', 'plz', 'ort',
        'strasse2', 'adresszusatz2', 'plz2', 'ort2',
        'telefon', 'homepage',
        'sprachen', 'hobbys', 'interessen',
        'studienort', 'studienfach', 'unityp', 'schwerpunkt', 'nebenfach', 'abschluss', 'zweitstudium',
        'ehrenamt', 'stipendien', 'auslandsaufenthalte', 'praktika',
        'beruf', 'fruehere_taetigkeiten',
        'aufgabe_freitext',
    ];

    // Liste der vom Mitglied änderbaren Booleans. Wird ergänzt um alle
    // sichtbarkeit_*- und aufgabe_*-Felder (außer aufgabe_freitext) aus User
    /* @var string[] */
    private array $bearbeiten_bool_ungeprueft = ['mentoring'];

    // Liste der von der Mitgliederverwaltung änderbaren Strings
    /* @var string[] */
    private array $bearbeiten_strings_admin = ['vorname', 'nachname'];

    const COUNTRY_NAMES = [
        'AF' => 'Afghanistan',
        'EG' => 'Ägypten',
        'AX' => 'Ålandinseln',
        'AL' => 'Albanien',
        'DZ' => 'Algerien',
        'AS' => 'Amerikanisch-Samoa',
        'VI' => 'Amerikanische Jungferninseln',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarktis',
        'AG' => 'Antigua und Barbuda',
        'GQ' => 'Äquatorialguinea',
        'AR' => 'Argentinien',
        'AM' => 'Armenien',
        'AW' => 'Aruba',
        'AZ' => 'Aserbaidschan',
        'ET' => 'Äthiopien',
        'AU' => 'Australien',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesch',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgien',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivien',
        'BQ' => 'Bonaire, Sint Eustatius und Saba',
        'BA' => 'Bosnien und Herzegowina',
        'BW' => 'Botsuana',
        'BV' => 'Bouvetinsel',
        'BR' => 'Brasilien',
        'VG' => 'Britische Jungferninseln',
        'IO' => 'Britisches Territorium im Indischen Ozean',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgarien',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CV' => 'Cabo Verde',
        'CL' => 'Chile',
        'CN' => 'China',
        'CK' => 'Cookinseln',
        'CR' => 'Costa Rica',
        'CI' => "Côte d'Ivoire",
        'CW' => 'Curaçao',
        'DK' => 'Dänemark',
        'CD' => 'Demokratische Republik Kongo',
        'DE' => 'Deutschland',
        'DM' => 'Dominica',
        'DO' => 'Dominikanische Republik',
        'DJ' => 'Dschibuti',
        'EC' => 'Ecuador',
        'SV' => 'El Salvador',
        'ER' => 'Eritrea',
        'EE' => 'Estland',
        'SZ' => 'Eswatini',
        'FK' => 'Falklandinseln',
        'FO' => 'Färöer',
        'FJ' => 'Fidschi',
        'FI' => 'Finnland',
        'FR' => 'Frankreich',
        'GF' => 'Französisch-Guayana',
        'PF' => 'Französisch-Polynesien',
        'TF' => 'Französische Süd- und Antarktisgebiete',
        'GA' => 'Gabun',
        'GM' => 'Gambia',
        'GE' => 'Georgien',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GD' => 'Grenada',
        'GR' => 'Griechenland',
        'GL' => 'Grönland',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard- und McDonaldinseln',
        'HN' => 'Honduras',
        'HK' => 'Hongkong',
        'IN' => 'Indien',
        'ID' => 'Indonesien',
        'IQ' => 'Irak',
        'IR' => 'Iran',
        'IE' => 'Irland',
        'IS' => 'Island',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italien',
        'JM' => 'Jamaika',
        'JP' => 'Japan',
        'YE' => 'Jemen',
        'JE' => 'Jersey',
        'JO' => 'Jordanien',
        'KY' => 'Kaimaninseln',
        'KH' => 'Kambodscha',
        'CM' => 'Kamerun',
        'CA' => 'Kanada',
        'KZ' => 'Kasachstan',
        'QA' => 'Katar',
        'KE' => 'Kenia',
        'KG' => 'Kirgisistan',
        'KI' => 'Kiribati',
        'CC' => 'Kokosinseln (Keelinginseln)',
        'CO' => 'Kolumbien',
        'KM' => 'Komoren',
        'CG' => 'Kongo',
        'HR' => 'Kroatien',
        'CU' => 'Kuba',
        'KW' => 'Kuwait',
        'LA' => 'Laos',
        'LS' => 'Lesotho',
        'LV' => 'Lettland',
        'LB' => 'Libanon',
        'LR' => 'Liberia',
        'LY' => 'Libyen',
        'LI' => 'Liechtenstein',
        'LT' => 'Litauen',
        'LU' => 'Luxemburg',
        'MO' => 'Macau',
        'MG' => 'Madagaskar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Malediven',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MA' => 'Marokko',
        'MH' => 'Marshallinseln',
        'MQ' => 'Martinique',
        'MR' => 'Mauretanien',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexiko',
        'FM' => 'Mikronesien',
        'MD' => 'Moldau',
        'MC' => 'Monaco',
        'MN' => 'Mongolei',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MZ' => 'Mosambik',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NC' => 'Neukaledonien',
        'NZ' => 'Neuseeland',
        'NI' => 'Nicaragua',
        'NL' => 'Niederlande',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'KP' => 'Nordkorea',
        'MK' => 'Nordmazedonien',
        'NF' => 'Norfolkinsel',
        'NO' => 'Norwegen',
        'MP' => 'Nördliche Marianen',
        'OM' => 'Oman',
        'AT' => 'Österreich',
        'PK' => 'Pakistan',
        'PS' => 'Palästina',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua-Neuguinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippinen',
        'PN' => 'Pitcairninseln',
        'PL' => 'Polen',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'RE' => 'Réunion',
        'RW' => 'Ruanda',
        'RO' => 'Rumänien',
        'RU' => 'Russische Föderation',
        'BL' => 'Saint-Barthélemy',
        'KN' => 'Saint Kitts und Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint-Martin (franz. Teil)',
        'PM' => 'Saint Pierre und Miquelon',
        'VC' => 'Saint Vincent und die Grenadinen',
        'SB' => 'Salomonen',
        'ZM' => 'Sambia',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'São Tomé und Príncipe',
        'SA' => 'Saudi-Arabien',
        'SE' => 'Schweden',
        'CH' => 'Schweiz',
        'SN' => 'Senegal',
        'RS' => 'Serbien',
        'SC' => 'Seychellen',
        'SL' => 'Sierra Leone',
        'ZW' => 'Simbabwe',
        'SG' => 'Singapur',
        'SX' => 'Sint Maarten (niederl. Teil)',
        'SK' => 'Slowakei',
        'SI' => 'Slowenien',
        'SO' => 'Somalia',
        'ES' => 'Spanien',
        'LK' => 'Sri Lanka',
        'ZA' => 'Südafrika',
        'SD' => 'Sudan',
        'GS' => 'Südgeorgien und die Südlichen Sandwichinseln',
        'KR' => 'Südkorea',
        'SS' => 'Südsudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard und Jan Mayen',
        'SY' => 'Syrien',
        'TJ' => 'Tadschikistan',
        'TW' => 'Taiwan',
        'TZ' => 'Tansania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad und Tobago',
        'TD' => 'Tschad',
        'CZ' => 'Tschechien',
        'TN' => 'Tunesien',
        'TR' => 'Türkei',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks- und Caicosinseln',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'HU' => 'Ungarn',
        'UY' => 'Uruguay',
        'US' => 'USA',
        'UM' => 'US-Amerikanische Kleinere Inselbesitzungen',
        'UZ' => 'Usbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatikanstadt',
        'VE' => 'Venezuela',
        'AE' => 'Vereinigte Arabische Emirate',
        'GB' => 'Vereinigtes Königreich',
        'VN' => 'Vietnam',
        'WF' => 'Wallis und Futuna',
        'EH' => 'Westsahara',
        'CF' => 'Zentralafrikanische Republik',
        'CY' => 'Zypern',
    ];

    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
    ) {
        $this->setTemplateVariable('countryNames', self::COUNTRY_NAMES);
        $this->setTemplateVariable('aufgabenLabels', User::AUFGABEN);
        $this->bearbeiten_bool_ungeprueft = [
            ...$this->bearbeiten_bool_ungeprueft,
            ...array_filter(User::getAllKeys(), fn($key) => str_starts_with($key, 'sichtbarkeit_') || str_starts_with($key, 'aufgabe_') && $key !== 'aufgabe_freitext')
        ];
    }

    #[Route('GET /user'), RequireLogin]
    public function showSelf(): Response {
        return $this->redirect('/user/self');
    }

    #[
        Route('GET /user/{\d+:id=>user}'),
        Route('GET /user/{username=>user}'),
        RequireLogin,
    ]
    public function show(User $user, UserAgreementRepository $userAgreementRepository): Response {
        $isAdmin = $this->currentUser->hasRole('mvread');

        $templateVars = [
            'fullName' => $user->get('fullName'),
            'uncoverAll' => $isAdmin,
            'bearbeitenUrl' => $user->get('bearbeitenUrl'),
            'mayEdit' => $this->currentUser->get('id') === $user->get('id') || $this->currentUser->hasRole('mvedit'),
            'isSelf' => $this->currentUser->get('id') ===  $user->get('id'),
        ];

        // generell: alle Daten kopieren
        foreach (User::getAllKeys() as $feld) {
            $templateVars[$feld] = $user->get($feld);
        }
        // E-Mail ist in User::getAllKeys() nicht enthalten, da sie in LDAP liegt
        $templateVars['email'] = $user->get('email');

        // Dann die sichtgeschützten Felder gesondert behandeln, damit das Template möglichst frei von Logik bleiben kann
        if (!$isAdmin) {
            $felder = array_filter(User::getAllKeys(), fn($key) => str_starts_with($key, 'sichtbarkeit_') && $key !== 'sichtbarkeit_plz_ort');
            $felder = array_map(fn($key) => substr($key, strlen('sichtbarkeit_')), $felder);
            foreach ($felder as $feld) {
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
            $homepage = 'https://' . $homepage;
        }
        if (!preg_match('=^https?://(?P<user>[^@]*@)?(?P<host>[\w\.0-9-]+)(?P<port>:[0-9]+)?(?<query>/.*)?$=i', $homepage)) {
            $homepage = '';
        }
        $templateVars['homepage'] = $homepage;

        return $this->render('UserController/profil', [
            ...$templateVars,
            'user' => $user,
            'datenschutzverpflichtung' => $userAgreementRepository->findLatestByUserAndName($user, 'datenschutzverpflichtung'),
        ]);
    }

    #[
        Route('GET /user/{username=>user}/edit'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")')
    ]
    public function edit(User $user): Response {
        $templateVars = [];
        foreach (User::getAllKeys() as $feld) {
            $templateVars[$feld] = $user->get($feld);
        }

        return $this->render('UserController/bearbeiten', [
            ...$templateVars,
            'fullName' => $user->get('fullName'),
            'dateOfJoining' => $user->get('dateOfJoining'),
            'groups' => implode(', ', $user->getGroups()),
            'db_modified_user' => $this->userRepository->findOneById((int)$user->get('db_modified_user_id')),
            'isAdmin' => $this->currentUser->hasRole('mvedit'),
            'isSuperAdmin' => $this->currentUser->hasRole('rechte'),
            'isSelf' => $this->currentUser->get('id') ===  $user->get('id'),
            'user' => $user,
            'email' => $user->get('email'),
            'delete' => false,
            'resign' => (bool)$user->get('resignation'),
            'password' => '',
            'new_password' => '',
            'new_password2' => '',
        ]);
    }

    #[
        Route('POST /user/{username=>user}/password'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")'),
    ]
    public function updatePassword(User $user): Response {
        $input = $this->validatePayload([
            'new_password' => 'string untrimmed',
            'new_password2' => 'string untrimmed',
            'password' => 'string untrimmed',
        ]);
        if (!$input['new_password']) {
            return $this->edit($user);
        }

        if ($input['new_password'] !== $input['new_password2']) {
            $this->setTemplateVariable('new_password2_error', true);
        } else {
            // Admins dürfen Passwörter ohne Angabe des eigenen Passworts ändern, außer das eigene
            if (($this->currentUser->hasRole('mvedit') && $this->currentUser->get('id') !==  $user->get('id'))
                || $this->currentUser->checkPassword($input['password'])
            ) {
                $this->setTemplateVariable('set_new_password', true);
                $user->setPassword($input['new_password']);
                $this->userRepository->save($user);
            } else {
                $this->setTemplateVariable('old_password_error', true);
            }
        }

        // TODO: redirect
        return $this->edit($user);
    }

    private function updateEmail(User $user): void {
        $email = $this->validatePayload(['email' => 'string'])['email'];

        if ($user->get('email') === $email) {
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setTemplateVariable('email_error', true);
            return;
        }

        if ($this->currentUser->hasRole('mvedit')) {
            $this->storeEmail($user, $email);
        } else {
            $this->setTemplateVariable('email_auth_info', true);
            $token = Token::encode([time(), $user->get('id'), $email], $user->get('email'), getenv('TOKEN_KEY'));
            $text = $this->renderToString('mails/email-auth', [
                'token' => $token,
                'return' => $return = new \stdclass(),
            ]);
            $this->emailService->send($email, $return->subject, $text);
        }
    }

    private function updateAdmin(User $user): void {
        $input = $this->validatePayload(array_fill_keys($this->bearbeiten_strings_admin, 'string'));
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

    /**
     * Admin-initiated resignation toggling via the edit form. Self-service resignation goes
     * through the dedicated step-up flow (resignForm()/resign()).
     */
    private function handleResign(User $user) {
        if (!$this->currentUser->hasRole('mvedit')) {
            return;
        }
        $resignOld = $user->get('resignation') !== null;
        $resignNew = $this->request->getPayload()->getBoolean('resign');
        if ($resignOld && !$resignNew) {
            $user->set('resignation', null);
        } elseif (!$resignOld && $resignNew) {
            $this->applyResignation($user, $this->currentUser->get('fullName'));
        }
    }

    /**
     * Sets the resignation date and notifies the board, the membership team and the member.
     */
    private function applyResignation(User $user, ?string $adminFullName = null): void {
        $user->set('resignation', 'now');

        $vars = [
            'fullName' => $user->get('fullName'),
            'id' => $user->get('id'),
            'return' => $return = new \stdclass(),
        ];
        if ($adminFullName !== null) {
            $vars['adminFullName'] = $adminFullName;
        }
        $text = $this->renderToString('mails/resignation', $vars);
        $this->emailService->send('vorstand@mind-hochschul-netzwerk.de', $return->subject, $text);
        $this->emailService->send('mitgliederbetreuung@mind-hochschul-netzwerk.de', $return->subject, $text);

        $text = $this->renderToString('mails/resignationConfirmation', [
            'fullName' => $user->get('fullName'),
            'id' => $user->get('id'),
            'return' => $return = new \stdclass(),
        ]);
        $this->emailService->sendToUser($user, $return->subject, $text);
    }

    /**
     * self-service resignation requires a fresh IdP re-authentication (step-up)
     */
    private function needsResignStepUp(User $user): bool {
        return $this->currentUser->get('id') === $user->get('id') && !$this->currentUser->hasRecentStepUp();
    }

    private function redirectToResignStepUp(User $user): Response {
        return $this->redirect('/login?stepup=1&redirect=' . rawurlencode('/user/' . $user->get('username') . '/resign'));
    }

    #[
        Route('GET /user/{username=>user}/resign'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")'),
    ]
    public function resignForm(User $user): Response {
        if ($this->needsResignStepUp($user)) {
            return $this->redirectToResignStepUp($user);
        }
        return $this->render('UserController/resign', [
            'user' => $user,
            'fullName' => $user->get('fullName'),
            'username' => $user->get('username'),
            'resignation' => $user->get('resignation'),
        ]);
    }

    #[
        Route('POST /user/{username=>user}/resign'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")'),
    ]
    public function resign(User $user): Response {
        if ($this->needsResignStepUp($user)) {
            return $this->redirectToResignStepUp($user);
        }
        if (!$user->get('resignation')) {
            $this->applyResignation($user);
            $user->set('db_modified', 'now');
            $user->set('db_modified_user_id', $this->currentUser->get('id'));
            $this->userRepository->save($user);
        }
        return $this->redirect('/user/' . $user->get('username') . '/edit');
    }

    private function delete(User $user): Response {
        if ($this->currentUser->get('id') ===  $user->get('id')) {
            throw new AccessDeniedException('Du kannst dich nicht selbst löschen!');
        }

        $this->userRepository->delete($user);

        $mailText = $this->renderToString('mails/MvEdit-Info-Mitglied-Geloescht', [
            'adminName' => $this->currentUser->get('fullName'),
            'adminId' => $this->currentUser->get('id'),
            'adminUsername' => $this->currentUser->get('username'),
            'deletedName' => $user->get('fullName'),
            'deletedId' => $user->get('id'),
            'deletedUsername' => $user->get('username'),
            'deletedEmail' => $user->get('email'),
            'return' => $return = new \stdclass(),
        ]);

        // Alle Mitglieder der Mitgliederbetreuung (mvedit) informieren
        $this->emailService->sendToGroup('mvedit', $return->subject, $mailText);

        return $this->render("UserController/delete-success");
    }

    #[
        Route('POST /user/{username=>user}/edit'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")'),
    ]
    public function update(User $user): Response {
        $input = $this->validatePayload(array_fill_keys($this->bearbeiten_strings_ungeprueft, 'string'));
        foreach ($input as $key=>$value) {
            $user->set($key, $value);
        }
        $input = $this->validatePayload(array_fill_keys($this->bearbeiten_bool_ungeprueft, 'bool'));
        foreach ($input as $key=>$value) {
            $user->set($key, $value);
        }

        // Land prüfen (ISO 3166-1 alpha-2 code)
        $input = $this->validatePayload(['land' => 'string', 'land2' => 'string']);
        foreach ($input as $key=>$value) {
            if (isset(self::COUNTRY_NAMES[$value]) || $key === 'land2' && $value === '') {
                $user->set($key, $value);
            }
        }

        $this->updateEmail($user);

        // nur für die Mitgliederverwaltung
        if ($this->currentUser->hasRole('mvedit')) {
            $this->updateAdmin($user);

            if ($this->request->getPayload()->getBoolean('delete')) {
                return $this->delete($user);
            }
        }

        // Austritt erklären
        $this->handleResign($user);

        // Speichern
        $user->set('db_modified', 'now');
        $user->set('db_modified_user_id', $this->currentUser->get('id'));
        $this->setTemplateVariable('data_saved_info', true);
        $this->userRepository->save($user);

        // und neu laden (insb. beim Löschen wichtig, sonst müssten alle Keys einzeln zurückgesetzt werden)
        // TODO: redirect. store messages in session
        return $this->edit($this->userRepository->findOneById($user->get('id')));
    }

    private function storeEmail(User $user, string $email): void {
        $oldMail = $user->get('email');

        try {
            $user->setEmail($email);
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Diese E-Mail-Adresse ist bereits bei einem anderen Mitglied eingetragen.');
        }

        $this->userRepository->save($user);

        $text = $this->renderToString('mails/email-changed', [
            'fullName' => $user->get('fullName'),
            'email' => $email,
            'return' => $return = new \stdclass(),
        ]);
        $this->emailService->send($oldMail, $return->subject, $text);

        $this->setTemplateVariable('email_changed', true);
    }

    #[Route('GET /email_auth?token={token}'), PublicAccess]
    public function emailAuth(string $token): Response {
        try {
            Token::decode($token, function ($data) use (&$user, &$email) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $email = $data[2];
                $user = $this->userRepository->findOneById($data[1]);
                return $user->get('email');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }

        assert($user instanceof User);
        assert(is_string($email));

        $this->currentUser->logIn($user);

        $this->storeEmail($user, $email);

        return $this->edit($user);
    }
}
