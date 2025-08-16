<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Ldap;
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class WahlleitungController extends Controller {
    /**
     * Auflistung aller E-Mail-Adressen für die Wahlleitung
     */
    #[Route('GET /wahlleitung', allow: ['role' => 'wahlleitung'])]
    public function show(Ldap $ldap): Response {
        $emails = $ldap->getAllValidEmails();

        $response = new Response(implode("\r\n", $emails));
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');

        return $response;
    }
}
