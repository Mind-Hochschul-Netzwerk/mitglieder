<?php
declare(strict_types=1);

namespace App\Controller;

use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller {
    #[Route('GET /groups', allow: ['role' => 'user'])]
    public function index(): Response {
        return $this->showMessage("Gruppen", "hi");
    }
}
