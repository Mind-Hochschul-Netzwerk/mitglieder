<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Mitglied;
use App\Service\Attribute\Route;
use App\Service\Db;
use App\Service\PasswordService;

class SearchController extends Controller {
    public function __construct() {
        // TODO: als Attribut der Klasse
        $this->requireLogin();
    }

    #[Route('GET /')]
    #[Route('GET /search')]
    public function form(): Response {
        return $this->render('SearchController/search');
    }

    // Felder mit |s bzw |s* nur mit sichtbarkeit
    const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];
    // Felder, bei denen nur nach Übereinstimmung statt nach Substring gesucht wird (müssen auch in felder aufgeführt sein)
    const felder_eq = ['id', 'mensa_nr', 'plz', 'plz2'];

    #[Route('GET /?q={query}')]
    #[Route('GET /search?q={query}')]
    public function search(string $query): Response {
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
        $ids = Db::getInstance()->query('SELECT id FROM mitglieder WHERE ' . implode(' AND ', $AND) . ' ORDER BY nachname, vorname LIMIT 50', $values)->getColumn();
        return $this->showResults($ids);
    }

    #[Route('GET /search/resigned')]
    public function showResigned(): Response {
        $this->requireRole('mvread');
        $this->setTemplateVariable('query', ' '); // show the "search results" title even if the list is empty
        $ids = Db::getInstance()->query('SELECT id FROM mitglieder WHERE resignation IS NOT NULL')->getColumn();
        return $this->showResults($ids);
    }

    private function showResults(array $ids): Response {
        // Alle Mitglieder laden
        $ergebnisse = [];
        $ids = array_unique($ids);
        foreach ($ids as $id) {
            $m = Mitglied::lade((int)$id);

            $orte = [];
            if ($m->get('ort') && $m->get('sichtbarkeit_plz_ort')) {
                $orte[] = $m->get('ort');
            }
            if ($m->get('ort2')) {
                $orte[] = $m->get('ort2');
            }

            // auszugebende Daten speichern und an Tpl übergeben
            $e = [
                'id' => $m->get('id'),
                'last_login' => $m->get('last_login'),
                'fullName' => $m->get('fullName'),
                'username' => $m->get('username'),
                'orte' => implode(', ', $orte),
                'profilbild' => $m->get('profilbild') ? ('thumbnail-' . $m->get('profilbild')) : null,
            ];

            $ergebnisse[] = $e;
        }

        return $this->render('SearchController/search', [
            'ergebnisse' => $ergebnisse,
        ]);
    }
}
