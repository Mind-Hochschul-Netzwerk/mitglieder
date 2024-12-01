<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\PasswordService;
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller {
    #[Route('GET /(search|)', allow: ['loggedIn' => true])]
    public function form(): Response {
        return $this->render('SearchController/search', ['query' => '']);
    }

    // Felder mit |s bzw |s* nur mit sichtbarkeit
    const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];
    // Felder, bei denen nur nach Übereinstimmung statt nach Substring gesucht wird (müssen auch in felder aufgeführt sein)
    const felder_eq = ['id', 'mensa_nr', 'plz', 'plz2'];

    #[Route('GET /(search|)?q={query}', allow: ['loggedIn' => true])]
    public function search(string $query, Db $db): Response {
        // TODO filter einbauen über beschaeftigung, auskunft_* und für mvread für aufgabe_*
        $this->setTemplateVariable('query', $query);

        // Strings die in Anführungszeichen stehen, müssen wirklich genau so vorkommen -> vor dem aufsplitten an Leerzeichen ersetzen
        $literalMap = [];
        $query = preg_replace_callback('/"([^"]+)"/', function ($matches) use (&$literalMap) {
            $key = PasswordService::randomString(64);
            $literalMap[$key] = $matches[1];
            return ' ' . $key . ' ';
        }, $query);

        $query = preg_replace('/\s+/', ' ', $query);
        $begriffe = explode(' ', trim($query));

        // zurück ersetzen
        array_walk($begriffe, function (&$begriff) use ($literalMap) {
            if (isset($literalMap[$begriff])) {
                $begriff = $literalMap[$begriff];
            }
        });

        $AND = ['(1=1)'];
        $values = [];
        foreach ($begriffe as $b) {
            $OR = [];
            foreach (self::felder as $feld) {
                $f = '';
                if (strpos($feld, '|')) {
                    list($feld, $sichtbarkeitsfeld) = explode('|', $feld);
                    if ($sichtbarkeitsfeld === 's') {
                        $sichtbarkeitsfeld = 'sichtbarkeit_' . $feld;
                    }
                    $f .= "$sichtbarkeitsfeld = 1 AND ";
                }
                if (in_array($feld, self::felder_eq, true)) {
                    $f .= "$feld = :$feld";
                    $values[$feld] = $b;
                } else {
                    $f .= "$feld LIKE :$feld";
                    $values[$feld] = "%$b%";
                }
                $OR[] = "($f)";
            }

            $AND[] = '(' . implode(' OR ', $OR) . ')';
        }

        // TODO Pagination?? oder einfach suche einschränken lassen und die erst 50 zeigen...
        $ids = $db->query('SELECT id FROM mitglieder WHERE ' . implode(' AND ', $AND) . ' ORDER BY nachname, vorname LIMIT 50', $values)->getColumn();
        return $this->showResults($ids);
    }

    #[Route('GET /search/resigned', allow: ['role' => 'mvread'])]
    public function showResigned(Db $db): Response {
        $this->setTemplateVariable('query', ' '); // show the "search results" title even if the list is empty
        $ids = $db->query('SELECT id FROM mitglieder WHERE resignation IS NOT NULL')->getColumn();
        return $this->showResults($ids);
    }

    private function showResults(array $ids): Response {
        // Alle Mitglieder laden
        $ergebnisse = [];
        $ids = array_unique($ids);
        foreach ($ids as $id) {
            $user = UserRepository::getInstance()->findOneById((int)$id);

            $orte = [];
            if ($user->get('ort') && $user->get('sichtbarkeit_plz_ort')) {
                $orte[] = $user->get('ort');
            }
            if ($user->get('ort2')) {
                $orte[] = $user->get('ort2');
            }

            // auszugebende Daten speichern und an Tpl übergeben
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
