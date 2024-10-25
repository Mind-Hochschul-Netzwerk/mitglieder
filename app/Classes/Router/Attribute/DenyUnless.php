<?php
declare(strict_types=1);
namespace App\Router\Attribute;

use Attribute;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class DenyUnless {
    /**
     * @param string $role string to get the required role, i.e.
     *                  '' => no role required
     *                  'user' => role 'user' required
     *                  'admin|superadmin' => one of the roles 'admin' or 'superadmin'
     *                  'admin&superadmin' => both of the roles 'admin' or 'superadmin'
     *                  '$group->getName()' => require the role given by the group name
     *                          of the $group argument that was passed to the function
     * @param string $userId string to get the required user id, i.e.
     *                  '' => no specific user id required
     *                  '$user->getId()' => current user has to be the one given by the
     *                          $user argument of the function
     *                  '$user->get("id")' => current user has to be the one given by the
     *                          $user argument of the function
     */
    public function __construct(public string $role = '', public string $userId = '')
    {
    }
}
