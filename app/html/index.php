<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\Controller;
use App\Service\CurrentUser;
use App\Service\TemplateVariable;
use App\Service\Tpl;
use Hengeb\Router\Exception\InvalidRouteException;
use Hengeb\Router\Router;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

$router = new Router(__DIR__ . '/../Classes/Controller');

$router->addExceptionHandler(InvalidRouteException::class, [Controller::class, 'handleException']);

$request = Request::createFromGlobals();
$currentUser = CurrentUser::getInstance();
$currentUser->setRequest($request);

Tpl::getInstance()->set('currentUser', $currentUser);
Tpl::getInstance()->set('_csrfToken', fn() => $router->createCsrfToken());
Tpl::getInstance()->set('_timeZone', new \DateTimeZone('Europe/Berlin'));

$router->dispatch($request, $currentUser)->send();
