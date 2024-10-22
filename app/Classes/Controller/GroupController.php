<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller {
    public function getResponse(): Response {
        return $this->showMessage("hi");
    }
}
