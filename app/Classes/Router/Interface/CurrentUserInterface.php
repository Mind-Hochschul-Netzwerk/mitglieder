<?php

namespace App\Router\Interface;

interface CurrentUserInterface {
    public function isLoggedIn(): bool;
}
