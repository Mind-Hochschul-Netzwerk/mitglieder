<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Ldap;
use Hengeb\Db\Db;
use App\Repository\UserRepository;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller {
    private array $invalidEmailsList;

    public function __construct(
        protected Request $request,
        protected \Latte\Engine $latte,
        private Ldap $ldap,
        private UserRepository $userRepository,
    )
    {
        $this->invalidEmailsList = $this->ldap->getInvalidEmailsList();
    }

    #[Route('GET /statistics/invalidEmails', allow: ['role' => 'mvread'])]
    public function showInvalidEmails(): Response
    {
        $users = [];
        foreach ($this->invalidEmailsList as $id) {
            $user = $this->userRepository->findOneById($id);
            if (!$user) {
                error_log("$id");
                continue;
            }
            $users[] = [
                'id' => $user->get('id'),
                'username' => $user->get('username'),
                'fullName' => $user->get('fullName'),
                'ort' => $user->get('ort'),
                'email' => substr($user->get('email'), 0, -strlen('.invalid')),
                'aufnahmedatum' => $user->get('aufnahmedatum') ? $user->get('aufnahmedatum')->format('d.m.Y') : 'unbekannt',
                'lastLogin' => $user->get('last_login') ? $user->get('last_login')->format('d.m.Y') : 'vor 2014',
                'moodle' => ($user->get('last_login') && $user->get('last_login') > new \DateTimeImmutable('2021-05-22')) ? 'ja' : 'nein',
            ];
        }

        return $this->render('StatisticsController/invalidEmails', [
            'invalidEmailsList' => $users,
        ]);
    }

    #[Route('GET /statistics', allow: ['role' => 'mvread'])]
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
