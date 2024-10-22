<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Service\Db;
use Symfony\Component\HttpFoundation\Response;

class WahlleitungController extends Controller {
    /**
     * Auflistung aller E-Mail-Adressen für die Wahlleitung
     */
    public function getResponse(): Response {
        $this->requireRole('wahlleitung');

        $emails = Db::getInstance()->query('SELECT email FROM mitglieder ORDER BY email')->getColumn();

        $response = new Response(implode("\r\n", $emails));
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');

        return $response;
    }
}
