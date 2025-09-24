<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\Ldap;
use Hengeb\Router\Attribute\CheckCsrfToken;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\AccessDeniedException;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tracy\Debugger;

class DevController extends Controller {
    public function __construct()
    {
        if (Debugger::$productionMode) {
            throw new AccessDeniedException('DevController is not available in production mode');
        }
    }

    /**
     * add a new user for testing purposes
     */
    #[Route('PUT /users'), PublicAccess, CheckCsrfToken(false)]
    public function addUser(
        Ldap $ldap,
        UserRepository $repo,
        #[RequestValue] string $username,
        #[RequestValue] string $password,
        #[RequestValue] string $email,
        ParameterBag $values,
    ): array {
        if (!$username) {
            throw new InvalidUserDataException('username must not be empty');
        }
        if (!User::isUsernameAllowed($username)) {
            throw new InvalidUserDataException("username '$username' is not allowed.");
        }
        if (!$repo->isUsernameAvailable($username)) {
            throw new InvalidUserDataException("username '$username' is not available.");
        }

        $user = new User(
            username: $username,
            password: $password,
            email: $email,
            ldap: $ldap,
            userRepository: $repo,
        );

        foreach (User::felder as $key => $default) {
            if (in_array($key, ['username', 'email', 'password', 'id'])) {
                continue;
            }
            if ($values->has($key)) {
                $user->set($key, $values->get($key));
            }
        }

        $repo->save($user);

        $ldap->addUserToGroup($username, 'alleMitglieder');
        $ldap->addUserToGroup($username, 'listen');

        return ['id' => $user->get('id')];
    }
}
