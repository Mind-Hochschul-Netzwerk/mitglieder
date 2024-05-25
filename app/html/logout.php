<?php
declare(strict_types=1);
namespace App;

/**
 * Logout
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Auth;
use App\Tpl;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'Logout');
Tpl::set('title', 'Logout');
Tpl::set('navId', 'logout');

if (!isset($_REQUEST['step']) || (int)$_REQUEST['step'] < 2) {
    Auth::logOut('/logout.php?step=2');
}

Tpl::render('Auth/logout');

Tpl::submit();
