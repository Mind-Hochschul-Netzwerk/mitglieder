<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\UserController;
use App\Model\User;
use App\Router\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

require_once '../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');

$router = new Router(__DIR__ . '/../Classes/Controller');
$router->addType(User::class, [UserController::class, 'retrieveByUsername'], 'username');
$router->addType(User::class, [UserController::class, 'retrieveById'], 'id');
$router->dispatch(Request::createFromGlobals())->send();

// TODO: CSRF tokens
