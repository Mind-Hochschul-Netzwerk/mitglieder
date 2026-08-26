<?php
namespace App\Model;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Repository\UserRepository;
use App\Service\Ldap;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Repräsentiert ein User
 */
class User
{
    const string PROFILE_PICUTRE_DIRECTORY = '/var/www/profilbilder/';

    public array $data = [];

    public ?Entry $ldapEntry = null;
    private $deleted = false;

    // Felder und Defaults
    const felder = [
        'id' => null, 'username' => '', 'vorname' => '', 'nachname' => '', 'sichtbarkeit_email' => false, 'titel' => '', 'geburtstag' => null, 'aufnahmedatum' => null, 'sichtbarkeit_geburtstag' => false, 'profilbild' => '', 'mensa_nr' => '', 'sichtbarkeit_mensa_nr' => false, 'strasse' => '', 'sichtbarkeit_strasse' => false, 'adresszusatz' => '', 'sichtbarkeit_adresszusatz' => false, 'plz' => '', 'ort' => '', 'sichtbarkeit_plz_ort' => false, 'land' => '', 'sichtbarkeit_land' => false, 'strasse2' => '', 'adresszusatz2' => '', 'plz2' => '', 'ort2' => '', 'land2' => '', 'telefon' => '', 'sichtbarkeit_telefon' => false, 'homepage' => '', 'sprachen' => '', 'hobbys' => '', 'interessen' => '',
        'beschaeftigung' => 'Sonstiges', 'sichtbarkeit_beschaeftigung' => false, 'studienort' => '', 'sichtbarkeit_studienort' => false, 'studienfach' => '', 'sichtbarkeit_studienfach' => false, 'unityp' => '', 'sichtbarkeit_unityp' => false, 'schwerpunkt' => '', 'sichtbarkeit_schwerpunkt' => false, 'nebenfach' => '', 'sichtbarkeit_nebenfach' => false, 'abschluss' => '', 'sichtbarkeit_abschluss' => false, 'zweitstudium' => '', 'sichtbarkeit_zweitstudium' => false, 'hochschulaktivitaeten' => '', 'sichtbarkeit_hochschulaktivitaeten' => false, 'stipendien' => '', 'sichtbarkeit_stipendien' => false, 'auslandsaufenthalte' => '', 'sichtbarkeit_auslandsaufenthalte' => false, 'praktika' => '', 'sichtbarkeit_praktika' => false, 'beruf' => '', 'sichtbarkeit_beruf' => false,
        'auskunft_studiengang' => false, 'auskunft_stipendien' => false, 'auskunft_auslandsaufenthalte' => false, 'auskunft_praktika' => false, 'auskunft_beruf' => false, 'mentoring' => false, 'aufgabe_ma' => false, 'aufgabe_orte' => false, 'aufgabe_vortrag' => false, 'aufgabe_koord' => false, 'aufgabe_graphisch' => false, 'aufgabe_computer' => false, 'aufgabe_texte_schreiben' => false, 'aufgabe_texte_lesen' => false, 'aufgabe_vermittlung' => false, 'aufgabe_ansprechpartner' => false, 'aufgabe_hilfe' => false, 'aufgabe_sonstiges' => false, 'aufgabe_sonstiges_beschreibung' => '',
        'db_modified' => null, 'last_login' => null,
        'db_modified_user_id' => null,
        'resignation' => null, 'membership_confirmation' => null,
    ];

    public $hashedPassword = '';
    private $newPassword = '';
    private string $email = '';

    public string $profilePicturePath {
        get => User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild');
    }

    public string $thumbnailPath {
        get => User::PROFILE_PICUTRE_DIRECTORY . '/thumbnail-' . $this->get('profilbild');
    }

    public string $profilePictureUrl {
        get => $this->get('profilbild') ? '/user/' . $this->get('username') . '/profile-picture?' . filemtime($this->profilePicturePath) : '';
    }

    public string $thumbnailUrl {
        get => $this->get('profilbild') ? '/user/' . $this->get('username') . '/profile-picture?size=thumbnail&' . filemtime($this->thumbnailPath) : '';
    }

    public string $fullName {
        get => $this->get('fullName');
    }

    public string $initials {
        get => preg_replace('/[^A-Z]/', '', strtoupper(preg_replace('/\B\w|\s+/u', '', $this->fullName)));
    }

    public string $landAusgeschrieben {
        get => self::getIso3166Laendernamen()[$this->get('land')] ?? $this->get('land');
    }

    public string $land2Ausgeschrieben {
        get => self::getIso3166Laendernamen()[$this->get('land2')] ?? $this->get('land2');
    }

    public function __construct(
        private Ldap $ldap,
        private UserRepository $userRepository,
        string $username = '',
        string $password = '',
        string $email = '',
    )
    {
        if (!$username && !$password && !$email) {
            // data will be filled in by UserRepository
            return;
        }

        $this->data = self::felder;

        $this->setUsername($username);
        $this->setPassword($password);
        $this->setEmail($email);

        // TODO: Aufnahmedatum wird gespeichert mit Uhrzeit 0:00 Uhr UTC.
        //       Falls der Antrag kurz nach Mitternacht in Deutschland ausgefüllt wird, liegt das Aufnahmedatum daher um 1 Tag daneben
        $this->setData('aufnahmedatum', 'now');
        $this->setData('db_modified', 'now');
    }

    /**
     * Liest eine Eigenschaft
     *
     * @param string $feld
     * @throws \LogicException wenn die ID erfragt wird, obwohl sie noch nicht existiert.
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function get($feld)
    {
        switch ($feld) {
        case 'id':
            return (int) $this->data['id'];
        case 'fullName':
            return implode(' ', array_filter([$this->data['vorname'], $this->data['nachname']])) ?? ('#' . $this->data['id']);
        case 'email':
            return $this->email;
        case 'hashedPassword':
            return $this->hashedPassword;
        case 'profilUrl':
            return '/user/' . $this->data['username'];
        case 'bearbeitenUrl':
            return '/user/' . $this->data['username'] . '/edit';
        case 'dateOfJoining';
            if ($this->get('membership_confirmation')) {
                return $this->get('membership_confirmation');
            }
            if ($this->get('aufnahmedatum') && $this->get('aufnahmedatum') > new \DateTime('2018-10-05')) {
                return $this->get('aufnahmedatum');
            }
            return null;
        default:
            if (in_array($feld, array_keys($this->data), true)) { // nicht über isset(), da dann Einträge mit Wert null nicht gefunden werden
                return $this->data[$feld];
            } else {
                throw new \OutOfRangeException('user property unknown: ' . $feld, 1493682787);
            }
        }
    }

    /**
     * typsicheres Setzen der Daten.
     *
     * @param bool $strictTypes Datentypen überprüfen. Bei false wird konvertiert.
     * @throws \TypeError, wenn $checkType === true ist und der Datentype nicht stimmt
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function setData(string $key, mixed $value, $strictTypes = true): void
    {
        if (!in_array($key, array_keys(self::felder), true)) {
            throw new \OutOfRangeException("user property unknown: $key", 1493682897);
        }

        $defaultType = gettype(self::felder[$key]);
        switch ($key) {
            case 'id':
            case 'db_modified_user_id':
                $defaultType = 'integer';
        }

        $type = gettype($value);

        if ($type === 'NULL') {
            if ($defaultType !== 'NULL' && !in_array($key, ['db_modified_user_id'])) {
                throw new \TypeError('Value for ' . $key . ' may not be null.', 1494774389);
            } else {
                $this->data[$key] = null;
            }
            return;
        }

        if ($defaultType !== 'NULL' && $strictTypes && $defaultType !== $type) {
            throw new \TypeError("Value for $key is expected to be $defaultType, $type given.", 1494774567);
        }

        switch ($key) {
            case 'geburtstag':
            case 'aufnahmedatum':
            case 'db_modified':
            case 'last_login':
            case 'resignation':
            case 'membership_confirmation':
                $this->data[$key] = $this->makeDateTime($value);
                return;
            default:
                if ($defaultType === 'integer') {
                    $this->data[$key] = (int)$value;
                } elseif ($defaultType === 'string') {
                    $this->data[$key] = (string)$value;
                } elseif ($defaultType === 'boolean') {
                    $this->data[$key] = (bool)$value;
                } elseif ($defaultType === 'double') {
                    $this->data[$key] = (float)$value;
                } else {
                    throw new \TypeError("Invalid data type for $key: $type.", 1494775686);
                }
                return;
        }
    }

    /**
     * Ändert eine Eigenschaft, sofern sie nicht schreibgeschützt ist
     *
     * @param string $feld
     * @param mixed $wert
     * @throws \LogicException wenn versucht wird, eine schreibgeschützte Eigenschaft zu ändern
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function set(string $feld, $wert)
    {
        switch ($feld) {
        case 'id':
        case 'username':
            throw new \LogicException("Eigenschaft $feld ist schreibgeschützt", 1493682836);
        case 'email':
            throw new \LogicException("Verwende setEmail(), um den Wert zu ändern.", 1494002758);
            break;
        case 'password':
            throw new \LogicException("Verwende setPassword(), um den Wert zu ändern.", 1755302008);
            break;
        default:
            $this->setData($feld,  $wert);
            break;
        }
        return true;
    }

    /**
     * @throws \UnexpectedValueException if username is already used by another user
     */
    private function setUsername(string $username)
    {
        if ($this->get('username') === 'username') {
            return;
        }
        if (!$this->userRepository->isUsernameAvailable($username)) {
            throw new \UnexpectedValueException('username already used', 1614368197);
        }
        $this->setData('username', $username);
    }

    /**
     * Setzt die E-Mail-Adresse.
     *
     * @param string $email
     * @throws \RuntimeException falls schon ein anderes User diese Adresse verwendet.
     * @return void
     */
    public function setEmail(string $email): void
    {
        $id = $this->userRepository->getIdByEmail($email);

        if ($id !== null && $id !== $this->get('id')) {
            throw new \RuntimeException('Doppelte Verwendung der E-Mail-Adresse ' . $email, 1494003025);
        }

        $this->email = $email;
    }

    public function setPassword(string $newPassword): void
    {
        $this->newPassword = $newPassword;
    }

    public function getPassword(): string
    {
        return $this->newPassword;
    }

    public function hasPasswordChanged(): bool
    {
        return $this->newPassword !== null;
    }

    /**
     * Erstellt ein DateTimeInterface-Objekt (oder null)
     *
     * @param null|string|int|DateTimeInterface $dateTime string (für strtotime), int (Timestamp) oder DateTimeInterface
     * @throws \TypeError wenn $dateTime einen nicht unterstützten Datentyp hat
     */
    private function makeDateTime(null|string|int|DateTimeInterface $dateTime): ?DateTimeInterface
    {
        if ($dateTime === null || $dateTime === '1970-01-01 00:00:00' || $dateTime === '0000-00-00' || $dateTime === '0000-00-00 00:00:00') {
            return null;
        } elseif (is_int($dateTime)) {
            if ($dateTime === 0) {
                return null;
            }
            return new DateTimeImmutable('@' . $dateTime);
        } elseif (is_string($dateTime)) {
            if ($dateTime === '') {
                return null;
            }
            return new DateTimeImmutable($dateTime);
        } elseif ($dateTime instanceof \DateTimeInterface) {
            return $dateTime;
        } else {
            throw new \TypeError("Value is expected to be DateTime, null, string or integer. " . gettype($dateTime) . " given.", 1494775564);
        }
    }

    public function isMemberOfGroup(string $groupName): bool
    {
        return $this->ldap->isUserMemberOfGroup($this->get('username'), $groupName);
    }

    public function hasRole(string $roleName): bool
    {
        if ($roleName === 'user') {
            return true;
        } elseif ($roleName === 'mvread' && $this->hasRole('mvedit')) {
            return true;
        } elseif ($roleName === 'mvedit' && $this->hasRole('rechte')) {
            return true;
        } elseif ($roleName === 'groupadmin' && $this->hasRole('rechte')) {
            return true;
        } else {
            return $this->isMemberOfGroup($roleName);
        }
    }

    public function getGroups(): array
    {
        return $this->ldap->getGroupsByUsername($this->get('username'));
    }

    public function deleteProfilePicture(): void
    {
        if ($this->get('profilbild') && is_file(User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild'))) {
            unlink(User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild'));
            unlink(User::PROFILE_PICUTRE_DIRECTORY . '/thumbnail-' . $this->get('profilbild'));
        }
        $this->set('profilbild', '');
    }

    public function deleteResources(): void
    {
        $this->deleteProfilePicture();
        $this->ldapEntry = null;
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function checkPassword(string $password): bool
    {
        if (!$password) {
            return false;
        }

        return $this->ldap->checkPassword($this->get('username'), $password);
    }

    /**
     * check if a username has a valid format
     * the username has to start with a letter and may only contain letters, numbers and the symbols '.' '_' '-'
     * @param $username string to check
     */
    public static function isUsernameAllowed(string $username): bool
    {
        return preg_match('/^[a-z][a-z0-9\-_.]*$/i', $username) && !in_array(strtolower($username), [
            // reserved usernames ('_' and '_self' are already excluded by the pattern above)
            'admin', 'self', 'system', 'user', 'username',
        ]);
    }

    /**
     * @return string[]
     */
    public static function getIso3166Laendernamen(): array
    {
        return [
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
    }
}
