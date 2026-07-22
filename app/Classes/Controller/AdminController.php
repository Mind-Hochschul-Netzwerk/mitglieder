<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller {
    #[Route('GET /admin'), AllowIf(role: 'mvedit'), AllowIf(role: 'newsletter-export')]
    public function show(): Response {
        return $this->render('AdminController/admin');
    }
}
