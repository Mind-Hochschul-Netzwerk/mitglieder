<?php
declare(strict_types=1);
namespace MHN\Mitglieder\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use Symfony\Component\Ldap\Ldap as SymfonyLdap;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Entry;
use MHN\Mitglieder\Password;
use MHN\Mitglieder\Config;

/**
 * ldap connection
 */
class Ldap implements \MHN\Mitglieder\Interfaces\Singleton
{
    use \MHN\Mitglieder\Traits\Singleton;

    private $ldap;
    private $isAdmin = false;

    private function __construct()
    {
        $this->ldap = SymfonyLdap::create('ext_ldap', ['connection_string' => getenv('LDAP_HOST')]);
    }

    private function bind(): void
    {
        if ($this->isAdmin) {
            return;
        }
        $this->ldap->bind(getenv('LDAP_BIND_DN'), getenv('LDAP_BIND_PASSWORD'));
        $this->isAdmin = true;
    }

    public function checkPassword(string $username, string $password): bool
    {
        $this->isAdmin = false;
        try {
            $this->ldap->bind($this->getDnByUsername($username), $password);
        } catch (InvalidCredentialsException $e) {
            return false;
        }
        return true;
    }

    public function getEntryByUsername(string $username): ?Entry
    {
        $this->bind();
        try {
            $result = $this->ldap->query(getenv('LDAP_PEOPLE_DN'), '(&(objectclass=inetOrgPerson)(cn=' . ldap_escape($username) . '))')->execute();
        } catch (\Exception $e) {
            return null;
        }
        if ($result) {
            return $result[0];
        } else {
            return null;
        }
    }

    public function getInvalidEmailsList(): array
    {
        $this->bind();
        try {
            $result = $this->ldap->query(getenv('LDAP_PEOPLE_DN'), '(&(objectclass=inetOrgPerson)(mail=*.invalid))')->execute();
        } catch (\Exception $e) {
            return [];
        }
        $list = [];
        foreach ($result as $user) {
            $list[] = (int) $user->getAttribute('employeeNumber')[0];
        }
        return $list;
    }

    public function getEntryByEmail(string $email): ?Entry
    {
        $this->bind();
        try {
            $result = $this->ldap->query(getenv('LDAP_PEOPLE_DN'), '(&(objectclass=inetOrgPerson)(mail=' . ldap_escape($email) . '))')->execute();
        } catch (\Exception $e) {
            return null;
        }
        if ($result) {
            return $result[0];
        } else {
            return null;
        }
    }

    private function setAttributes($entry, array $data): void
    {
        if (!empty($data['firstname'])) {
            $entry->setAttribute('givenName', [$data['firstname']]);
        }
        if (!empty($data['lastname'])) {
            $entry->setAttribute('sn', [$data['lastname']]);
        }
        if (!empty($data['email'])) {
            $entry->setAttribute('mail', [$data['email']]);
        }
        if (!empty($data['description'])) {
            $entry->setAttribute('description', [$data['description']]);
        }
        if (!empty($data['id'])) {
            $entry->setAttribute('employeeNumber', [$data['id']]);
        }
        if (!empty($data['suspended'])) {
            $entry->setAttribute('employeeType', [$data['suspended']]);
        }
        if (!empty($data['password'])) {
            $entry->setAttribute('userPassword', ['{CRYPT}' .  Password::hash($data['password'])]);
        }
        if (!empty($data['hashedPassword'])) {
            $entry->setAttribute('userPassword', [$data['hashedPassword']]);
        }
    }

    public function addUser(string $username, array $data): Entry
    {
        $this->bind();
        $entry = new Entry($this->getDnByUsername($username), [
            'objectClass' => ['inetOrgPerson', 'top'],
            'cn' => [$username],
            'givenName' => ['-'],
            'sn' => ['-'],
            'mail' => ['no@mail.invalid'],
            'userPassword' => ['{CRYPT}! no login'],
            'employeeNumber' => ['0'],
            'employeeType' => ['0'],
        ]);
        $this->setAttributes($entry, $data);
        $this->ldap->getEntryManager()->add($entry);
        return $entry;
    }

    public function modifyUser(string $username, array $data): bool
    {
        $this->bind();
        $entry = $this->getEntryByUsername($username);
        if (!$entry) {
            return false;
        }
        $this->setAttributes($entry, $data);
        $this->ldap->getEntryManager()->update($entry);
        return true;
    }

    public function deleteUser(string $username): void
    {
        $this->bind();
        try {
            $this->ldap->getEntryManager()->remove(new Entry($this->getDnByUsername($username)));
        } catch (\Exception $e) {

        }
    }

    private function getDnByUsername($username)
    {
        return 'cn=' . ldap_escape($username) . ',' . getenv('LDAP_PEOPLE_DN');
    }

    public function isUserMemberOfGroup(string $username, string $group): bool
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($group) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_GROUPS_DN'), $query)->execute()[0];
        return !empty($entry);
    }

    /**
     * @throws \InvalidArgumentException username does not exist
     * @throws \OutOfRangeException group does not exist
     */
    public function addUserToGroup(string $username, string $group): void
    {
        if ($this->isUserMemberOfGroup($username, $group)) {
            return;
        }
        $userEntry = $this->getEntryByUsername($username);
        if (!$userEntry) {
            throw new \InvalidArgumentException('no such user: ' . $username, 1612375712);
        }
        $entry = $this->ldap->query(getenv('LDAP_GROUPS_DN'), '(&(objectclass=groupOfNames)(cn=' . ldap_escape($group) . '))')->execute()[0];
        if (!$entry) {
            throw new \OutOfRangeException('invalid group name: ' . $group, 1612375711);
        }
        $member = $entry->getAttribute('member');
        $member[] = $this->getDnByUsername($username);
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function removeUserFromGroup(string $username, string $group): void
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($group) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_GROUPS_DN'), $query)->execute()[0];
        if (!$entry) {
            return;
        }
        $member = $entry->getAttribute('member');
        $lowerUsername = strtolower($username);
        foreach ($member as $i=>$dn) {
            preg_match('/^cn=(.*?),/', $dn, $matches);
            if ($lowerUsername === strtolower($matches[1])) {
                unset($member[$i]);
                break;
            }
        }
        $member = array_values($member);
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function getGroupsByUsername(string $username): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(member=' . $this->getDnByUsername($username) . '))';
        $result = $this->ldap->query(getenv('LDAP_GROUPS_DN'), $query)->execute();
        $groups = [];
        foreach ($result as $group) {
            $groups[] = $group->getAttribute('cn')[0];
        }
        return $groups;
    }

    public function getIdsByGroup(string $groupName): array
    {
        foreach ($this->getAllGroups() as $group) {
            if ($group['name'] !== $groupName) {
                continue;
            }
            return array_map(function ($user) {
                return (int)$user['id'];
            }, $group['users']);
        }
        throw new \OutOfRangeException('invalid group name: ' . $groupName, 1614374821);
    }

    /**
     * @return array [
     *  ['name' => group name,
     *   'description' => description,
     *    'users' => [
     *        ['id' => uid, 'username' => username, 'firstname' => first name, 'lastname' => last name],
     *         ...
     *        ],
     *    ...
     *  ], ... ]
     */
    public function getAllGroups(): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames))';
        $result = $this->ldap->query(getenv('LDAP_GROUPS_DN'), $query)->execute();
        $groups = [];
        foreach ($result as $group) {
            $members = array_map(function ($dn) {
                if (substr($dn, 0, strlen('cn=')) !== 'cn=') {
                    return null;
                }
                if (substr($dn, -strlen(getenv('LDAP_PEOPLE_DN'))) !== getenv('LDAP_PEOPLE_DN')) {
                    return null;
                }
                $username = substr(substr($dn, strlen('cn=')), 0, -1-strlen(getenv('LDAP_PEOPLE_DN')));
                $user = $this->getEntryByUsername($username);
                return [
                    'id' => $user->getAttribute('employeeNumber')[0],
                    'username' => $user->getAttribute('cn')[0],
                    'firstname' => $user->getAttribute('givenName')[0],
                    'lastname' => $user->getAttribute('sn')[0],
                ];
            }, $group->getAttribute('member'));
            $members = array_filter($members, function ($entry) {
                return $entry !== null;
            });
            $groups[] = [
                'name' => $group->getAttribute('cn')[0],
                'description' => $group->getAttribute('description')[0],
                'users' => $members
            ];
        }
        return $groups;
    }
}
