<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\UserController;
use App\Service\Router;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');

Service\Session::getInstance()->start();

$router = new Router(__DIR__ . '/../Classes/Controller');
$router->addType(Mitglied::class, [UserController::class, 'retrieveByUsername'], 'username');
$router->addType(Mitglied::class, [UserController::class, 'retrieveById'], 'id');
$router->dispatch(Request::createFromGlobals())->send();

// TODO: CSRF tokens
