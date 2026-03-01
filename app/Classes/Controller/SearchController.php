<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\Ldap;
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

enum FilterOp: string {
    case Contains = 'contains';
    case ContainsWord = 'containsWord';
    case NotContains = 'notContains';
    case StartsWith = 'startsWith';
    case EndsWith = 'endsWith';
    case Equal = 'eq';
    case GreaterOrEqual =  'ge';
    case LessOrEqual = 'le';
    case Between = 'between';
    case isTrue = 'true';
    case isFalse = 'false';
}

class SearchController extends Controller {
    public function __construct(
        private UserRepository $userRepository,
        private Db $db,
        private Ldap $ldap,
    ) {}

    #[Route('GET /(search|)'), RequireLogin]
    public function form(): Response {
        return $this->render('SearchController/search');
    }

    private array $filterValues = [];

    // Felder mit |s bzw |s* nur mit sichtbarkeit
    const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];

    #[Route('GET /(search|)?fullName={fullName}&location={location}&any={any}'), RequireLogin]
    public function search(string $fullName, string $location, string $any): Response {
        // handle the "any" filter
        $anyIds = null;
        if ($any) {
            $this->setTemplateVariable('any', $any);

            foreach (preg_split('/[\.;, ]/', $any) as $k=>$part) {
                if ($part === '') {
                    continue;
                }
                $this->addFilterValue("any$k", $part);
                $filters[] = [
                    ['telefon', 'mensa_nr', 'titel', 'sprachen', 'hobbys', 'interessen', 'stipendien', 'auslandsaufenthalte', 'praktika', 'beruf', 'id', 'studienfach', 'nebenfach'],
                    FilterOp::Contains,
                    "any$k"
                ];
            }

            $dbIds = $this->getDbIds($filters, $this->filterValues);
            $ldapIds = $this->getLdapIds($filters, $this->filterValues);

            $anyIds = match (null) {
                $dbIds => $ldapIds,
                $ldapIds => $dbIds,
                default => array_unique([...$dbIds, ...$ldapIds]),
            };
        }

        // reset filters after the handling of the "any" filter
        $filters = [];
        $this->filterValues = [];

        if ($fullName) {
            $this->setTemplateVariable('fullName', $fullName);

            foreach (preg_split('/[\.;, ]/', $fullName) as $k=>$part) {
                $this->addFilterValue("fullName$k", $part);
                $filters[] = [['titel', 'vorname', 'nachname'], FilterOp::StartsWith, "fullName$k"];
            }
        }

        if ($location) {
            $this->setTemplateVariable('location', $location);
            $this->addFilterValue("location", $location);

            if (preg_match('/^[0-9]+$/', $location)) {
                $filters[] = [['plz', 'plz2'], FilterOp::StartsWith, 'location'];
            } elseif (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $location)) {
                $filters[] = [['plz', 'plz2'], FilterOp::Between, 'location'];
            } else {
                $filters[] = [['ort', 'ort2', 'land', 'land2'], FilterOp::Contains, 'location'];
            }
        }

        for ($i = 0; $i < 5;$i++) {
            $key = $this->request->query->getString("key$i");
            if ($key === 'none' || !$key) {
                break;
            }
            if (in_array($key, ['rolle', 'resigned', 'emailInvalid'], true) && !$this->currentUser->hasRole('mvread')) {
                throw new \Hengeb\Router\Exception\AccessDeniedException();
            }
            // TODO
            if (in_array($key, ['rolle'], true)) {
                throw new \Exception('not implemented');
            }

            $op = $this->request->query->getEnum("op$i", FilterOp::class);
            $value = $this->request->query->getString("value$i");

            $this->addFilterValue("value$i", $value);

            $filters[] = [[$key], $op, "value$i"];

            $this->setTemplateVariable("key$i", $key);
            $this->setTemplateVariable("op$i", $op->value);
            $this->setTemplateVariable("value$i", $value);
        }

        $dbIds = $this->getDbIds($filters, $this->filterValues);
        $ldapIds = $this->getLdapIds($filters, $this->filterValues);

        // now we have $anyIds (from the "any" filter), $dbIds and $ldapIds. Let's combine them.
        $ids = null;
        foreach ([$anyIds, $dbIds, $ldapIds] as $current) {
            if ($ids === null) {
                $ids = $current;
            } elseif ($current !== null) {
                $ids = array_intersect($ids, $current);
            }
        }

        if (!$ids) {
            return $this->form();
        }

        // show a maximum of 50 results
        $ids = array_slice($ids, 0, 50);

        return $this->showResults($ids);
    }

    /**
     * @return array|null (null if no filter is an LDAP filter)
     */
    function getLdapIds(array $filters, array $values): ?array
    {
        $query = '';

        foreach ($filters as [$fields, $op, $valueName]) {
            $field = $fields[0]; // TODO multi
            if ($field === 'email') {
                $query .= $this->generateLdapQuery('mail', $op, $values[$valueName]);
                // email protection is handled in generateFilterSql()
            } elseif ($field === 'emailInvalid') {
                if ($op === FilterOp::isTrue) {
                    $query .= '(mail=*.invalid)';
                } elseif ($op === FilterOp::isFalse) {
                    $query .= '(!(mail=*.invalid))';
                } else {
                    throw new \Exception('invalid filter operator for emailInvalid');
                }

            }
        }

        bdump($query);

        if (!$query) {
            return null;
        } else {
            return $this->ldap->getUserIdsByQuery($query);
        }
    }

    function generateLdapQuery(string $field, FilterOp $op, string $value): string
    {
        $value = ldap_escape($value);
        return match ($op) {
            FilterOp::Equal => "($field=$value)",
            FilterOp::Contains => "($field=*$value*)",
            FilterOp::StartsWith => "($field=$value*)",
            FilterOp::EndsWith => "($field=*$value)",
            FilterOp::NotContains => "(!($field=*$value*))",
            FilterOp::GreaterOrEqual => "($field>=$value)",
            FilterOp::LessOrEqual => "($field<=$value)",
            FilterOp::isTrue => "(!($field=))",
            FilterOp::isFalse => "($field=)",
            default => throw new \Exception('filter operator not implement for LDAP'),
        };
    }

    /**
     * @return array|null (null if no filter is an SQL filter)
     */
    function getDbIds(array $filters, array $values): ?array
    {
        $conditions = [];
        foreach ($filters as [$fields, $op, $value]) {
            $conditions[] = $this->generateFilterSql($fields, $op, $value);
        }
        $where = implode(' AND ', array_filter($conditions));

        if (!$where) {
            return null;
        }

        // remove unused keys from $values
        foreach ($values as $name=>$value) {
            if (!preg_match("/:$name\W/", $where)) {
                unset($values[$name]);
            }
        }
        bdump($where);
        bdump($values);

        return $this->db->query("SELECT id FROM mitglieder WHERE $where ORDER BY nachname, vorname", $values)->getColumn();
    }

    private function addFilterValue(string $name, string $value): void
    {
        $this->filterValues[$name] = $value;
        $this->filterValues[$name . '_like'] = "%$value%";
        $this->filterValues[$name . '_start'] = "$value%";
        $this->filterValues[$name . '_end'] = "%$value";
        $this->filterValues[$name . '_word'] = '[[:<:]]' . preg_quote($value, '/') . '[[:>:]]';
        if (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $value, $matches)) {
            [$min, $max] = array_map('trim', explode('-', $value));
            $this->filterValues[$name . '_min'] = $min;
            $this->filterValues[$name . '_max'] = $max;
        } else {
            $this->filterValues[$name . '_min'] = $value;
            $this->filterValues[$name . '_max'] = $value;
        }
        if (preg_match('/^(\d\d?)\.(\d\d?)\.((19|20)\d\d)$/', $value, $matches)) {
            $this->filterValues[$name . '_date'] = $this->filterValues[$name . '_date_min'] = $this->filterValues[$name . '_date_max']
                = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        } elseif (preg_match('/^((19|20)\d\d)$/', $value)) {
            $this->filterValues[$name . '_date'] = "$value-01-01";
            $this->filterValues[$name . '_date_min'] = "$value-01-01";
            $this->filterValues[$name . '_date_max'] = "$value-12-31";
        } elseif (preg_match('/^(\d\d?)\.(\d\d?)\.((19|20)\d\d)\s*\-\s*(\d\d?)\.(\d\d?)\.((19|20)\d\d)$/', $value, $matches)) {
            $this->filterValues[$name . '_date'] = 'invalid';
            $this->filterValues[$name . '_date_min'] = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
            $this->filterValues[$name . '_date_max'] = sprintf('%04d-%02d-%02d', $matches[7], $matches[6], $matches[5]);
        } elseif (preg_match('/^((19|20)\d\d)\s*\-\s*((19|20)\d\d)$/', $value, $matches)) {
            $this->filterValues[$name . '_date'] = 'invalid';
            $this->filterValues[$name . '_date_min'] = "{$matches[1]}-01-01";
            $this->filterValues[$name . '_date_max'] = "{$matches[3]}-12-31";
        } else {
            $this->filterValues[$name . '_date'] = $this->filterValues[$name . '_date_min'] = $this->filterValues[$name . '_date_max'] = $value;
        }
    }

    private function generateFilterSql(array $fields, FilterOp $op, string $valueName): string
    {
        if (count($fields) > 1) {
            return '(' . implode(' OR ', array_filter(array_map(fn($field) => $this->generateFilterSql([$field], $op, $valueName), $fields))) . ')';
        }
        $field = $fields[0];

        return match ($field) {
            // email is in LDAP but protection flag is in DB
            'email' => !$this->currentUser->hasRole('mvread') ? '(sichtbarkeit_email = true)' : '',
            'studienfach' => '(' . $this->generateSingleFilterExpression($field, $op, $valueName)
                    . ' OR ' . $this->generateSingleFilterExpression('nebenfach', $op, $valueName) . ')',
            'aufnahmedatum', 'resignation' => match ($op) {
                    FilterOp::Contains, FilterOp::ContainsWord, FilterOp::NotContains, FilterOp::StartsWith, FilterOp::EndsWith => $this->generateSingleFilterExpression("DATE_FORMAT($field, '%d.%m.%Y')", $op, $valueName),
                    FilterOp::Equal, FilterOp::GreaterOrEqual, FilterOp::LessOrEqual, FilterOp::Between, FilterOp::isTrue, FilterOp::isFalse => $this->generateSingleFilterExpression($field, $op, $valueName . '_date'),
                    default => throw new \OutOfBoundsException('op implemented: ' . $op),
                },
            'datenschutzverpflichtung' => $this->generateSingleFilterExpression('(
                    SELECT IF(user_agreements.action != "accept", NULL, agreements.version)
                    FROM user_agreements
                    INNER JOIN agreements ON agreements.id = user_agreements.agreement_id
                    WHERE agreements.name = "Datenschutzverpflichtung" AND user_agreements.user_id = mitglieder.id
                    ORDER BY user_agreements.id DESC
                    LIMIT 1
                )', $op, $valueName),
            'id', 'titel', 'vorname', 'nachname',
            'plz', 'plz2', 'ort', 'ort2', 'land', 'land2',
            'telefon', 'mensa_nr', 'sprachen', 'hobbys', 'interessen',
            'stipendien', 'auslandsaufenthalte', 'praktika', 'beruf',
                => $this->generateSingleFilterExpression($field, $op, $valueName),
            default => '',
        };
    }

    function generateSingleFilterExpression(string $field, FilterOp $op, $valueName) {
        $sql = "$field " . match ($op) {
            FilterOp::Contains => "LIKE :{$valueName}_like",
            FilterOp::ContainsWord => "REGEXP :{$valueName}_word",
            FilterOp::NotContains => "NOT LIKE :{$valueName}_like",
            FilterOp::StartsWith => "LIKE :{$valueName}_start",
            FilterOp::EndsWith => "LIKE :{$valueName}_end",
            FilterOp::Equal => "= :$valueName",
            FilterOp::GreaterOrEqual => ">= :{$valueName}_min",
            FilterOp::LessOrEqual => "<= :{$valueName}_max",
            FilterOp::Between => ">= :{$valueName}_min AND $field <= :{$valueName}_max",
            FilterOp::isTrue => "IS NOT NULL AND $field != ''",
            FilterOp::isFalse => "IS NULL OR $field = ''",
            default => throw new \Exception('filter operator not implement for SQL'),
        };
        // handle protected fields
        if (!$this->currentUser->hasRole('mvread')) {
            if (in_array("$field|s", self::felder, true)) {
                $sql .= " AND sichtbarkeit_$field = true";
            } elseif ($field === 'plz' || $field === 'ort') {
                $sql .= " AND sichtbarkeit_plz_ort = true";
            }
        }
        return "($sql)";
    }

    private function showResults(array $ids): Response {
        // Alle Mitglieder laden
        $ergebnisse = [];
        $ids = array_unique($ids);
        foreach ($ids as $id) {
            $user = $this->userRepository->findOneById((int)$id);

            $orte = [];
            if ($user->get('ort') && $user->get('sichtbarkeit_plz_ort')) {
                $orte[] = $user->get('ort');
            }
            if ($user->get('ort2')) {
                $orte[] = $user->get('ort2');
            }

            // auszugebende Daten speichern und an Template übergeben
            $e = [
                'id' => $user->get('id'),
                'last_login' => $user->get('last_login'),
                'fullName' => $user->get('fullName'),
                'username' => $user->get('username'),
                'orte' => implode(', ', $orte),
                'profilbild' => $user->get('profilbild') ? ('thumbnail-' . $user->get('profilbild')) : null,
            ];

            $ergebnisse[] = $e;
        }

        return $this->render('SearchController/search', [
            'ergebnisse' => $ergebnisse,
        ]);
    }
}
