<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\Ldap;
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

enum FilterOp: string {
    case Contains = "contains";
    case NotContains = "notContains";
    case StartsWith = "startsWith";
    case Equal = "eq";
    case GreaterOrEqual =  "ge";
    case LessOrEqual = "le";
    case isTrue = "true";
    case isFalse = "false";
}

class SearchController extends Controller {
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Route('GET /(search|)'), RequireLogin]
    public function form(): Response {
        return $this->render('SearchController/search', ['query' => '']);
    }

    // Felder mit |s bzw |s* nur mit sichtbarkeit
    const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];
    // Felder, bei denen nur nach Übereinstimmung statt nach Substring gesucht wird (müssen auch in felder aufgeführt sein)
    const felder_eq = ['id', 'mensa_nr', 'plz', 'plz2'];

    #[Route('GET /(search|)?fullName={fullName}'), RequireLogin]
    public function search(Db $db, Ldap $ldap): Response {
        $filters = [];
        for ($i = 0; $i < 5;$i++) {
            $key = $this->request->query->getString("key$i");
            if ($key === 'none') {
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

            $filters[] = [$key, $op, $value];

            $this->setTemplateVariable("key$i", $key);
            $this->setTemplateVariable("op$i", $op->value);
            $this->setTemplateVariable("value$i", $value);
        }

        foreach (['fullName', 'location', 'any'] as $field) {
            $value = $this->request->query->getString($field);
            if ($value) {
                $this->setTemplateVariable($field, $value);
                $filters[] = [$field, FilterOp::Contains, $value];
            }
        }

        if (!$filters) {
            return $this->form();
        }

        $conditions = ['true'];
        $values = [];
        foreach ($filters as $k=>[$field, $op, $value]) {
            $this->generateFilterSql($k, $field, $op, $value, $conditions, $values);
        }
        $where = '(' . implode(') AND (', $conditions) . ')';
        // remove unused keys from $values
        foreach ($values as $name=>$value) {
            if (!preg_match("/:$name\W/", $where)) {
                unset($values[$name]);
            }
        }
        bdump($where);
        bdump($values);

        $dbIds = $db->query("SELECT id FROM mitglieder WHERE $where ORDER BY nachname, vorname", $values)->getColumn();

        // TODO
        // $ldapIds = $ldap->;

        $ids = $dbIds;  // $ids = array_intersect($dbIds, $ldapIds);
        $ids = array_slice($ids, 0, 50);

        return $this->showResults($ids);
    }

    private function generateFilterSql(int $k, string $field, FilterOp $op, string $value, array &$conditions, array &$values): void
    {
        if ($field === 'aufnahmedatum') {
            if (preg_match('/^(\d\d?)\.(\d\d?)\.(\d\d\d\d)$/', $value, $matches)) {
                $value = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
            } elseif (preg_match('/^(\d\d\d\d)$/', $value)) {
                $value = "$value-01-01";
            }
        }

        $valueName = 'value' . $k;
        $values[$valueName] = $value;
        $condition = '';

        switch ($field) {
            case 'fullName':
                $conditionParts = [];
                foreach (explode(' ', $value) as $i=>$part) {
                    if ($part === '') {
                        continue;
                    }
                    $valuePartName = $valueName . '_' . $i;
                    $values[$valuePartName] = $part;
                    $conditionParts[] = '(' . $this->generateSingleFilterExpression('titel', FilterOp::StartsWith, $valuePartName)
                        . ' OR ' . $this->generateSingleFilterExpression('vorname', FilterOp::StartsWith, $valuePartName)
                        . ' OR ' . $this->generateSingleFilterExpression('nachname', FilterOp::StartsWith, $valuePartName) . ')';
                }
                $condition = implode(' AND ', $conditionParts);
                break;
            case 'location':
                if (preg_match('/^[0-9]+$/', $value)) {
                    $condition = $this->generateSingleFilterExpression('plz', FilterOp::StartsWith, $valueName);
                } elseif (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $value, $matches)) {
                    $values[$valueName . '_min'] = $matches[1];
                    $values[$valueName . '_max'] = $matches[2];
                    $condition = '(' . $this->generateSingleFilterExpression('plz', FilterOp::GreaterOrEqual, $valueName . '_min')
                        . 'AND' . $this->generateSingleFilterExpression('plz', FilterOp::LessOrEqual, $valueName . '_max') . ')'
                        . ' OR (' . $this->generateSingleFilterExpression('plz2', FilterOp::GreaterOrEqual, $valueName . '_min')
                        . 'AND' . $this->generateSingleFilterExpression('plz2', FilterOp::LessOrEqual, $valueName . '_max') . ')';
                } else {
                    $condition = $this->generateSingleFilterExpression('ort', $op, $valueName)
                        . 'OR' . $this->generateSingleFilterExpression('ort2', $op, $valueName)
                        . 'OR' . $this->generateSingleFilterExpression('land', $op, $valueName)
                        . 'OR' . $this->generateSingleFilterExpression('land2', $op, $valueName);
                }
                break;
            case 'any':
                $fields = ['telefon', 'mensa_nr', 'titel', 'sprachen', 'hobbys', 'interessen', 'stipendien', 'auslandsaufenthalte', 'praktika', 'beruf', 'id', 'studienfach', 'nebenfach'];
                $conditionParts = array_map(fn($f) => $this->generateSingleFilterExpression($f, $op, $valueName), $fields);
                $condition = implode(' OR ', $conditionParts);
                break;
            case 'studienfach':
                $condition = $this->generateSingleFilterExpression($field, $op, $valueName)
                    . 'OR' . $this->generateSingleFilterExpression('nebenfach', $op, $valueName);
                break;
            case 'beschaeftigung':
                throw new \OutOfBoundsException('not implemented: ' . $field);
                break;
            case 'telefon':
            case 'mensa_nr':
            case 'titel':
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
                $condition = $this->generateSingleFilterExpression($field, $op, $valueName);
                break;
            case 'aufgaben':
                throw new \OutOfBoundsException('not implemented: ' . $field);
                break;
        }

        if ($condition) {
            $conditions[] = $condition;
        }
    }

    function generateSingleFilterExpression(string $field, FilterOp $op, $valueName) {
        $sql = "($field " . match ($op) {
            FilterOp::Contains => "LIKE CONCAT('%', :$valueName, '%')",
            FilterOp::NotContains => "NOT LIKE CONCAT('%', :$valueName, '%')",
            FilterOp::StartsWith => "LIKE CONCAT(:$valueName, '%')",
            FilterOp::Equal => "= :$valueName",
            FilterOp::GreaterOrEqual => ">= :$valueName",
            FilterOp::LessOrEqual => "<= :$valueName",
            FilterOp::isTrue => "IS NOT NULL AND $field != ''",
            FilterOp::isFalse => "IS NULL OR $field = ''",
        } . ')';
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
