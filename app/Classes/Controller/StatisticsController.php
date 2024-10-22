<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Ldap;
use App\Service\Db;
use App\Mitglied;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller {
    public function getResponse(): Response {
        $this->requireRole('mvread');

        $invalidEmailsList = Ldap::getInstance()->getInvalidEmailsList();

        if ($this->path[2] === 'invalidEmails') {
            return $this->showInvalidEmails($invalidEmailsList);
        } else {
            return $this->showOverview($invalidEmailsList);
        }
    }

    private function showInvalidEmails(array &$invalidEmailsList): Response
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

        return $this->render('StatisticsController/invalidEmails', [
            'invalidEmailsList' => $users,
        ]);
    }

    private function showOverview(array &$invalidEmailsList): Response {
        $db = Db::getInstance();
        return $this->render('StatisticsController/main', [
            'countInvalidEmails' => count($invalidEmailsList),
            'countAllEntries' => $db->query('SELECT COUNT(id) FROM mitglieder')->get(),
            'countDeleted' => $db->query('SELECT COUNT(id) FROM deleted_usernames')->get(),
            'countAfterOct2018' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE aufnahmedatum >= 20181005')->get(),
            'countConfirmedMembership' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum < 20181005 OR aufnahmedatum IS NULL) AND membership_confirmation IS NOT NULL')->get(),
            'countResignations' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL) AND resignation IS NOT NULL')->get(),
            'eintritte' => $db->query('SELECT COUNT(id) AS anzahl, MAX(id) as max_id, YEAR(aufnahmedatum + INTERVAL 89 DAY) AS eintrittsjahr FROM mitglieder WHERE aufnahmedatum IS NOT NULL GROUP BY eintrittsjahr ORDER BY eintrittsjahr')->getAll(),
        ]);
    }
}
