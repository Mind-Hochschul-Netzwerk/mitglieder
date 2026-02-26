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
    ) {}

    #[Route('GET /(search|)'), RequireLogin]
    public function form(): Response {
        return $this->render('SearchController/search');
    }

    private array $filterValues = [];

    // Felder mit |s bzw |s* nur mit sichtbarkeit
    const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];

    #[Route('GET /(search|)?fullName={fullName}&location={location}&any={any}'), RequireLogin]
    public function search(string $fullName, string $location, string $any, Db $db, Ldap $ldap): Response {
        $filters = [];
        for ($i = 0; $i < 5;$i++) {
            $key = $this->request->query->getString("key$i");
            if ($key === 'none' || !$key) {
                continue;
            }
            if (in_array($key, ['rolle', 'resigned', 'emailInvalid'], true) && !$this->currentUser->hasRole('mvread')) {
                throw new \Hengeb\Router\Exception\AccessDeniedException();
            }
            // TODO
            if (in_array($key, ['email', 'rolle', 'emailInvalid', 'datenschutzverpflichtung'], true)) {
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
        }

        if (!$filters) {
            return $this->form();
        }

        $conditions = ['true'];
        bdump($filters);
        foreach ($filters as [$fields, $op, $value]) {
            $conditions[] = $this->generateFilterSql($fields, $op, $value);
        }
        bdump($conditions);
        $where = implode(' AND ', $conditions);
        // remove unused keys from $values
        foreach ($this->filterValues as $name=>$value) {
            if (!preg_match("/:$name\W/", $where)) {
                unset($this->filterValues[$name]);
            }
        }
        bdump($where);
        bdump($this->filterValues);

        $dbIds = $db->query("SELECT id FROM mitglieder WHERE $where ORDER BY nachname, vorname", $this->filterValues)->getColumn();

        // TODO
        // $ldapIds = $ldap->;

        $ids = $dbIds;  // $ids = array_intersect($dbIds, $ldapIds);
        $ids = array_slice($ids, 0, 50);

        return $this->showResults($ids);
    }

    private function addFilterValue(string $name, string $value): void
    {
        $this->filterValues[$name] = $value;
        $this->filterValues[$name . '_like'] = "%$value%";
        $this->filterValues[$name . '_start'] = "$value%";
        $this->filterValues[$name . '_end'] = "$value%";
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

        if ($field === 'aufnahmedatum') {
            $valueName = $valueName . '_date';
        }

        switch ($field) {
            case 'studienfach':
                return '(' . $this->generateSingleFilterExpression($field, $op, $valueName)
                    . ' OR ' . $this->generateSingleFilterExpression('nebenfach', $op, $valueName) . ')';
            case 'beschaeftigung':
                throw new \OutOfBoundsException('not implemented: ' . $field);
                break;
            case 'telefon':
            case 'mensa_nr':
            case 'titel':
            case 'plz':
            case 'plz2':
            case 'ort':
            case 'ort2':
            case 'land':
            case 'land2':
            case 'vorname':
            case 'nachname':
            case 'sprachen':
            case 'hobbys':
            case 'interessen':
            case 'stipendien':
            case 'auslandsaufenthalte':
            case 'praktika':
            case 'beruf':
            case 'id':
            case 'aufnahmedatum':
            case 'resignation':
                return $this->generateSingleFilterExpression($field, $op, $valueName);
            case 'aufgaben':
                throw new \OutOfBoundsException('not implemented: ' . $field);
                break;
            default:
                return '';
        }
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
        };
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
