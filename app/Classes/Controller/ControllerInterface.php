<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

interface ControllerInterface {
    public function route(string $path): Response;
}
