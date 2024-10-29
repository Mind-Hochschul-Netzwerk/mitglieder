<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\AuthController;
use App\Controller\Controller;
use App\Router\Exception\NotLoggedInException;
use App\Router\Router;
use App\Service\CurrentUser;
use App\Service\Tpl;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');

$router = new Router(__DIR__ . '/../Classes/Controller');

// TODO: Als Attribute
$router->addExceptionHandler(NotLoggedInException::class, [AuthController::class, 'handleNotLoggedInException']);
$router->addExceptionHandler(\Exception::class, [Controller::class, 'handleException']);

$request = Request::createFromGlobals();
$currentUser = CurrentUser::getInstance();
$currentUser->setRequest($request);

Tpl::getInstance()->set('currentUser', $currentUser);

$router->dispatch($request, $currentUser)->send();

// TODO: CSRF tokens
