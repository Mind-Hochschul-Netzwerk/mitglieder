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
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\CheckCsrfToken;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;

class DevController extends Controller {
    /**
     * add a new user for testing purposes
     */
    #[Route('PUT /user/{username}'), AllowIf(productionMode: false), CheckCsrfToken(false)]
    public function addUser(
        Ldap $ldap,
        UserRepository $repo,
        string $username,
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

    /**
     * import users from LDAP
     */
    #[Route('GET /users/ldap-sync'), AllowIf(productionMode: false)]
    public function ldapSync(
        Ldap $ldap,
        UserRepository $repo,
        Db $db,
    ): Response {
        $ldapUsers = $ldap->getAll();

        $importedUsers = [];

        foreach ($ldapUsers as $userinfo) {
            $username = $userinfo['username'];
            $user = $repo->findOneByUsername($username);
            if (!$user) {
                $id = (int) $db->query('INSERT INTO mitglieder SET
                    username=:username,
                    vorname=:vorname,
                    nachname=:nachname,
                    kenntnisnahme_datenverarbeitung_aufnahme_text="",
                    einwilligung_datenverarbeitung_aufnahme_text=""
                ', [
                    'username' => $username,
                    'vorname' => $userinfo['firstname'],
                    'nachname' => $userinfo['lastname'],
                ])->getInsertId();

                $ldap->modifyUser($username, ['id' => $id]);
                $ldap->addUserToGroup($username, 'alleMitglieder');
                $ldap->addUserToGroup($username, 'listen');

                $importedUsers[] = [
                    ... $userinfo,
                    'id' => $id,
                ];
            }
        }

        return $this->render('DevController/ldapSync', ['importedUsers' => $importedUsers]);
    }
}
