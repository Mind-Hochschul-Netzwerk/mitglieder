<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Attribute\Route;
use App\Service\AuthService;
use App\Service\Ldap;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller {
    #[Route('GET /admin')]
    public function show(): Response {
        $this->requireRole('mvedit');

        $groups = [];
        if (AuthService::hatRecht('rechte')) {
            $groups = Ldap::getInstance()->getAllGroups($skipMembersOfGroups = ['alleMitglieder', 'listen']);
            usort($groups, function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });
        }

        return $this->render('AdminController/admin', [
            'groups' => $groups
        ]);
    }
}
