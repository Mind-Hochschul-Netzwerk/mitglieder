<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace MHN\Mitglieder;

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\Service\Db;

require_once '../lib/base.inc.php';

Auth::intern();
Auth::requirePermission('mvread');

Tpl::set('htmlTitle', 'Statistik');
Tpl::set('title', 'Statistik');
Tpl::set('navId', 'statistics');

Tpl::sendHead();

$db = Db::getInstance();

ensure($_GET['a'], ENSURE_STRING, 'main');

if ($_GET['a'] === 'deletionCandidates') {
    $ids = array_map('intval', $db->query('SELECT id FROM mitglieder WHERE aktiviert=1 AND aufnahmedatum < 20181005 AND membership_confirmation IS NULL')->getColumn());
    $users = array_map(function ($id) {
        $m = Mitglied::lade($id);
        return [
            'id' => $m->get('id'),
            'fullName' => $m->get('fullName'),
            'ort' => $m->get('ort'),
            'email' => $m->get('email'),
            'aufnahmedatum' => $m->get('aufnahmedatum') ? $m->get('aufnahmedatum')->format('d.m.Y') : 'unbekannt',
            'lastLogin' => $m->get('last_login') ? $m->get('last_login')->format('d.m.Y') : 'vor 2014',
            'moodle' => ($m->get('last_login') && $m->get('last_login') > new \DateTime('2021-05-22')) ? 'ja' : 'nein',
        ];
    }, $ids);

    Tpl::set('htmlTitle', 'Zum Löschen vorgesehene Personen');
    Tpl::set('title', 'Zum Löschen vorgesehene Personen');

    Tpl::set('deletionCandidates', $users);
    Tpl::render('StatisticsController/deletionCandidates');
    exit;
}

// $entriesWithInvalidEmails = Ldap::getInstance()->getAllEntriesWithInvalidEmails();
$invalidEmailsList = Ldap::getInstance()->getInvalidEmailsList();

if ($_GET['a'] === 'invalidEmails') {
    $users = [];
    foreach ($invalidEmailsList as $id) {
        $m = Mitglied::lade($id);
        if (!$m) {
            continue;
        }
        $users[] = [
            'id' => $m->get('id'),
            'fullName' => $m->get('fullName'),
            'ort' => $m->get('ort'),
            'email' => substr($m->get('email'), 0, -strlen('.invalid')),
            'aufnahmedatum' => $m->get('aufnahmedatum') ? $m->get('aufnahmedatum')->format('d.m.Y') : 'unbekannt',
            'lastLogin' => $m->get('last_login') ? $m->get('last_login')->format('d.m.Y') : 'vor 2014',
            'moodle' => ($m->get('last_login') && $m->get('last_login') > new \DateTime('2021-05-22')) ? 'ja' : 'nein',
        ];
    }

    Tpl::set('invalidEmailsList', $users);

    Tpl::set('htmlTitle', 'Personen mit nicht-erreichbaren E-Mail-Adressen');
    Tpl::set('title', 'Personen mit nicht-erreichbaren E-Mail-Adressen');

    Tpl::render('StatisticsController/invalidEmails');
    exit;
}

Tpl::set('countInvalidEmails', count($invalidEmailsList));

Tpl::set('countAllEntries', $db->query('SELECT COUNT(id) FROM mitglieder')->get());
Tpl::set('countActivated', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1')->get());
Tpl::set('countNotActivated', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=0')->get());
Tpl::set('countDeleted', $db->query('SELECT COUNT(id) FROM deleted_usernames')->get());

Tpl::set('countAfterOct2018', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1 AND aufnahmedatum >= 20181005')->get());
Tpl::set('countConfirmedMembership', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1 AND aufnahmedatum < 20181005 AND membership_confirmation IS NOT NULL')->get());
Tpl::set('countDeletionCandidates', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1 AND aufnahmedatum < 20181005 AND membership_confirmation IS NULL')->get());
Tpl::set('countMembers', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1 AND (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL)')->get());
Tpl::set('countResignations', $db->query('SELECT COUNT(id) FROM mitglieder WHERE aktiviert=1 AND (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL) AND resignation = 1')->get());

Tpl::render('StatisticsController/main');

Tpl::submit();
