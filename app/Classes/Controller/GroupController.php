<?php
declare(strict_types=1);

namespace App\Controller;

use App\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller {
    #[Route('GET /groups')]
    public function index(): Response {
        return $this->showMessage("hi");
    }
}
