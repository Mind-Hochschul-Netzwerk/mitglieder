<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\Controller;
use App\Router\Exception\InvalidRouteException;
use App\Router\Router;
use App\Service\CurrentUser;
use App\Service\Tpl;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

$router = new Router(__DIR__ . '/../Classes/Controller');

$router->addExceptionHandler(InvalidRouteException::class, [Controller::class, 'handleException']);

$request = Request::createFromGlobals();
$currentUser = CurrentUser::getInstance();
$currentUser->setRequest($request);

Tpl::getInstance()->set('currentUser', $currentUser);
Tpl::getInstance()->set('csrfToken', [$router, 'createCsrfToken']);

$router->dispatch($request, $currentUser)->send();
