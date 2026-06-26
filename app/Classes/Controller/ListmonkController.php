<?php
/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Ldap;
use App\Service\Listmonk;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Synchronisation der Mitglieder (Name + E-Mail) mit einer ListMonk-Instanz.
 */
class ListmonkController extends Controller {
    /**
     * Synchronisation manuell aus dem Admin-Bereich auslösen.
     */
    #[Route('POST /admin/listmonk-sync'), AllowIf(role: 'newsletter-export')]
    public function syncFromAdmin(Ldap $ldap, Listmonk $listmonk): Response {
        $result = $listmonk->sync($ldap->getAll());
        return $this->render('ListmonkController/sync-result', ['result' => $result]);
    }

    /**
     * Synchronisation per token-geschütztem Endpoint für einen externen Cronjob.
     */
    #[Route('GET /cron/listmonk-sync'), PublicAccess]
    public function syncFromCron(Ldap $ldap, Listmonk $listmonk): Response {
        $expected = getenv('LISTMONK_SYNC_TOKEN') ?: '';
        $given = (string) $this->request->query->get('token', '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            throw new AccessDeniedException();
        }
        $result = $listmonk->sync($ldap->getAll());
        return $this->json($result);
    }
}
