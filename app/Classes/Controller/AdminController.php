<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Router\Attribute\Route;
use App\Service\CurrentUser;
use App\Service\Ldap;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller {
    #[Route('GET /admin', allow: ['role' => 'mvedit'])]
    public function show(Ldap $ldap, CurrentUser $user): Response {
        $groups = [];
        if ($user->hasRole('rechte')) {
            $groups = $ldap->getAllGroups($skipMembersOfGroups = ['alleMitglieder', 'listen']);
            usort($groups, function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });
        }

        return $this->render('AdminController/admin', [
            'groups' => $groups
        ]);
    }
}
