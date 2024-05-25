<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use Symfony\Component\HttpFoundation\Request;

require_once '../lib/base.inc.php';

$request = Request::createFromGlobals();
$path = $request->getPathInfo();

$controller =  explode('/', $path . '/', 3)[1];

switch ($controller) {
    case '':
        include 'search.php';
        exit;
        break;
    case 'group':
    case 'groups':
        (new \App\Controller\GroupController())->route($path)->send();
        break;
}

http_response_code(404);
die('invalid route');
