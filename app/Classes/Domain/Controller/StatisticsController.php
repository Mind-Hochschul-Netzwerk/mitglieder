<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Domain\Controller;

use App\Auth;
use App\Tpl;
use App\Service\Ldap;
use App\Service\Db;
use App\Mitglied;

class StatisticsController {
    private ?Db $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    public function run(): void
    {
        Auth::intern();
        Auth::requirePermission('mvread');

        Tpl::set('htmlTitle', 'Statistik');
        Tpl::set('title', 'Statistik');
        Tpl::set('navId', 'statistics');

        Tpl::sendHead();

        ensure($_GET['a'], ENSURE_STRING, 'main');

        $invalidEmailsList = Ldap::getInstance()->getInvalidEmailsList();

        switch ($_GET['a']) {
            case 'invalidEmails':
                $this->showInvalidEmails($invalidEmailsList);
                break;
            default:
                $this->showOverview($invalidEmailsList);
                break;
        }

        Tpl::submit();
    }

    private function showInvalidEmails(array &$invalidEmailsList): void
    {
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
    }

    private function showOverview(array &$invalidEmailsList): void
    {
        Tpl::set('countInvalidEmails', count($invalidEmailsList));

        Tpl::set('countAllEntries', $this->db->query('SELECT COUNT(id) FROM mitglieder')->get());
        Tpl::set('countDeleted', $this->db->query('SELECT COUNT(id) FROM deleted_usernames')->get());

        Tpl::set('countAfterOct2018', $this->db->query('SELECT COUNT(id) FROM mitglieder WHERE aufnahmedatum >= 20181005')->get());
        Tpl::set('countConfirmedMembership', $this->db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum < 20181005 OR aufnahmedatum IS NULL) AND membership_confirmation IS NOT NULL')->get());
        Tpl::set('countResignations', $this->db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL) AND resignation IS NOT NULL')->get());

        Tpl::set('eintritte', $this->db->query('SELECT COUNT(id) AS anzahl, MAX(id) as max_id, YEAR(aufnahmedatum + INTERVAL 89 DAY) AS eintrittsjahr FROM mitglieder WHERE aufnahmedatum IS NOT NULL GROUP BY eintrittsjahr ORDER BY eintrittsjahr')->getAll());

        Tpl::render('StatisticsController/main');
    }
}
