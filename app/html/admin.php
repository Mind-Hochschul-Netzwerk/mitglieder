<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Admin-Panel
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Ldap;

require_once '../lib/base.inc.php';

Auth::intern();

Tpl::set('htmlTitle', 'Mitgliederverwaltung');
Tpl::set('title', 'Mitgliederverwaltung');
Tpl::set('navId', 'admin');

Tpl::sendHead();

if (!Auth::hatRecht('mvedit')) {
    die('Fehlende Rechte.');
}

$groups = Ldap::getInstance()->getAllGroups($skipMembersOfGroups = ['alleMitglieder', 'listen']);
usort($groups, function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

Tpl::set('groups', $groups);

Tpl::render('Admin/admin');

Tpl::submit();
