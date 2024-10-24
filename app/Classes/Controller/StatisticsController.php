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
use App\Service\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller {
    private array $invalidEmailsList;

    public function __construct()
    {
        $this->requireRole('mvread');
        $this->invalidEmailsList = Ldap::getInstance()->getInvalidEmailsList();
    }

    #[Route('GET /statistics/invalidEmails')]
    public function showInvalidEmails(): Response
    {
        $users = [];
        foreach ($this->invalidEmailsList as $id) {
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

    #[Route('GET /statistics')]
    public function show(Db $db): Response
    {
        return $this->render('StatisticsController/main', [
            'countInvalidEmails' => count($this->invalidEmailsList),
            'countAllEntries' => $db->query('SELECT COUNT(id) FROM mitglieder')->get(),
            'countDeleted' => $db->query('SELECT COUNT(id) FROM deleted_usernames')->get(),
            'countAfterOct2018' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE aufnahmedatum >= 20181005')->get(),
            'countConfirmedMembership' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum < 20181005 OR aufnahmedatum IS NULL) AND membership_confirmation IS NOT NULL')->get(),
            'countResignations' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL) AND resignation IS NOT NULL')->get(),
            'eintritte' => $db->query('SELECT COUNT(id) AS anzahl, MAX(id) as max_id, YEAR(aufnahmedatum + INTERVAL 89 DAY) AS eintrittsjahr FROM mitglieder WHERE aufnahmedatum IS NOT NULL GROUP BY eintrittsjahr ORDER BY eintrittsjahr')->getAll(),
        ]);
    }
}
