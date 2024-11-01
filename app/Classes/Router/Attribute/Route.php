<?php
declare(strict_types=1);
namespace App\Router\Attribute;

use Attribute;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Route {
    /**
     * @param array $allow = [$property => $valueTemplate, ...] allow access if one of the conditions is fullfilled
     *      Router will determine the $value of $valueTemplate and then check if $currentUser->{'has' . $property}($value) === true
     *      ($value will be casted to int if neccessary)
     *      i.e. a method of this name has to exist in the $currentUser object
     *      $valueTemplate might be something like:
     *          literal value like 'team', e.g. ['group' => 'team']: will check if current user has the group 'team'
     *          list of literals like 'admin|superadmin', e.g. ['role' => 'admin|superadmin'] will check if the user has one of the roles
     *          list of literals like 'admin&superadmin', e.g. ['role' => 'admin&superadmin'] will check if the user has all of the roles
     *          a simple function call to an argument of the function like '$group->getName()' or '$user->getId()', e.g.
     *              ['group' => '$group->getName()', 'id' => '$user->get("id")] will check if either
     *              $currentUser->hasGroup($group->getName()) OR $currentUser->hasId($user->get("id")) is true
     *              where $group and $user have to be parameters of the routing method
     * @param bool $checkCsrfToken null => auto by HTTP method (GET: false, otherwise: true)
     */
    public function __construct(public string $matcher, public array $allow = [], public ?bool $checkCsrfToken = null)
    {
    }
}
