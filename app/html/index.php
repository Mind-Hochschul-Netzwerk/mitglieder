<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Mitgliedersuche
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

include '../lib/base.inc.php';

use \MHN\Mitglieder\Service\Db;

Auth::intern();

Tpl::set('htmlTitle', 'MHN-Mitgliederverzeichnis');
Tpl::set('navId', 'suche');
Tpl::set('title', 'MHN-Mitgliederverzeichnis');
Tpl::sendHead();

// Felder mit |s bzw |s* nur mit sichtbarkeit
const felder = ['username', 'id', 'vorname', 'nachname', 'mensa_nr|s', 'strasse|s', 'adresszusatz|s', 'plz|sichtbarkeit_plz_ort', 'ort|sichtbarkeit_plz_ort', 'land|s', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort|s', 'studienfach|s', 'unityp|s', 'schwerpunkt|s', 'nebenfach|s', 'abschluss|s', 'zweitstudium|s', 'hochschulaktivitaeten|s', 'stipendien|s', 'auslandsaufenthalte|s', 'praktika|s', 'beruf|s'];
// Felder, bei denen nur nach Übereinstimmung statt nach Substring gesucht wird (müssen auch in felder aufgeführt sein)
const felder_eq = ['id', 'mensa_nr', 'plz', 'plz2'];

// TODO filter einbauen über beschaeftigung, auskunft_* und für mvread für aufgabe_*

ensure($_GET['q'], ENSURE_STRING);

$ids = [];
$db = Db::getInstance();

if ($_GET['q']) {
    Tpl::set('query', $_GET['q']);

    // Strings die in Anführungszeichen stehen, müssen wirklich genau so vorkommen -> vor dem aufsplitten an Leerzeichen ersetzen
    $literalMap = [];
    $_GET['q'] = preg_replace_callback('/"([^"]+)"/', function ($matches) use (&$literalMap) {
        $key = Password::randomString(64);
        $literalMap[$key] = $matches[1];
        return ' ' . $key . ' ';
    }, $_GET['q']);

    $_GET['q'] = preg_replace('/\s+/', ' ', $_GET['q']);
    $begriffe = explode(' ', trim($_GET['q']));

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
        foreach (felder as $feld) {
            $f = '';
            if (strpos($feld, '|')) {
                list($feld, $sichtbarkeitsfeld) = explode('|', $feld);
                if ($sichtbarkeitsfeld === 's') {
                    $sichtbarkeitsfeld = 'sichtbarkeit_' . $feld;
                }
                $f .= "$sichtbarkeitsfeld = 1 AND ";
            }
            if (in_array($feld, felder_eq, true)) {
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
    $ids = $db->query('SELECT id FROM mitglieder WHERE aktiviert = true AND ' . implode(' AND ', $AND) . ' ORDER BY nachname, vorname LIMIT 50', $values)->getColumn();

    // Die Mitgliederverwaltung darf auch nach nicht aktivierten Mitgliedern suchen
    if (Auth::hatRecht('mvedit')) {
        $id = $db->query('SELECT id FROM mitglieder WHERE id=:id OR username=:username', [
            'id' => (int)$_GET['q'],
            'username' => $_GET['q']
        ])->get();
        if ($id) {
            array_unshift($ids, $id);
        }
    }
} elseif (!empty($_GET['resigned']) && Auth::hatRecht('mvread')) {
    $ids = $db->query('SELECT id FROM mitglieder WHERE resignation IS NOT NULL')->getColumn();
}

// Alle Mitglieder laden
$ergebnisse = [];
$ids = array_unique($ids);
foreach ($ids as $id) {
    $m = Mitglied::lade((int)$id, true);

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
Tpl::set('ergebnisse', $ergebnisse);

Tpl::render('Suche/suche');

Tpl::submit();
