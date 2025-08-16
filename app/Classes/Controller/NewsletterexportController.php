<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Ldap;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class NewsletterexportController extends Controller {
    /**
     * Generierung der Exportliste für den Newsletter
     */
    #[Route('GET /newsletter-export', allow: ['role' => 'newsletter-export'])]
    public function show(Ldap $ldap): Response {
        $list = $ldap->getAll();

        ob_start();
        $out = fopen('php://output', 'w');

        fputcsv($out, ['email', 'name', 'attributes']);

        foreach ($list as $user) {
            fputcsv($out, [
                $user['email'],
                $user['firstname'] . ' ' . $user['lastname'],
                json_encode([
                    'mitgliedsnummer' => (int) $user['id'],
                    'vorname' => $user['firstname'],
                    'nachname' => $user['lastname'],
                ])
            ]);
        }
        fclose($out);
        $csv = ob_get_contents();
        ob_end_clean();

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');

        return $response;
    }
}
