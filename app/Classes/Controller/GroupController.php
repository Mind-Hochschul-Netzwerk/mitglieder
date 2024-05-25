<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class GroupController implements ControllerInterface {
    public function route(string $path): Response {
        return new Response("hi");
    }
}