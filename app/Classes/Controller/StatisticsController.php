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
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller {
    public function __construct(
        private Ldap $ldap,
        private UserRepository $userRepository,
    ) {}

    #[Route('GET /statistics'), AllowIf(role: 'mvread')]
    public function show(Db $db): Response
    {
        return $this->render('StatisticsController/main', [
            'countAllEntries' => $db->query('SELECT COUNT(id) FROM mitglieder')->get(),
            'countDeleted' => $db->query('SELECT COUNT(id) FROM deleted_usernames')->get(),
            'countAfterOct2018' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE aufnahmedatum >= 20181005')->get(),
            'countConfirmedMembership' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum < 20181005 OR aufnahmedatum IS NULL) AND membership_confirmation IS NOT NULL')->get(),
            'countResignations' => $db->query('SELECT COUNT(id) FROM mitglieder WHERE (aufnahmedatum >= 20181005 OR membership_confirmation IS NOT NULL) AND resignation IS NOT NULL')->get(),
            'eintritte' => $db->query('SELECT COUNT(id) AS anzahl, MAX(id) as max_id, YEAR(aufnahmedatum + INTERVAL 89 DAY) AS eintrittsjahr FROM mitglieder WHERE aufnahmedatum IS NOT NULL GROUP BY eintrittsjahr ORDER BY eintrittsjahr')->getAll(),
        ]);
    }
}
