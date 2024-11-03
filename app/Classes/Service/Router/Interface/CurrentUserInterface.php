<?php

namespace App\Service\Router\Interface;

interface CurrentUserInterface {
    public function isLoggedIn(): bool;
}
