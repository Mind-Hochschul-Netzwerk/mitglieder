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
    
    public function hasRole(string $username, string $role) 
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($role) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_ROLES_DN'), $query)->execute()[0];
        return !empty($entry);
    }

    private function getDnByUsername($username) 
    {
        return 'cn=' . ldap_escape($username) . ',' . getenv('LDAP_PEOPLE_DN');
    }
    
    public function addRole($username, $role) 
    {
        if ($this->hasRole($username, $role)) {
            return;
        }
        $userEntry = $this->getEntryByUsername($username);
        if (!$userEntry) {
            throw new \InvalidArgumentException('no such user: ' . $username, 1612375712);            
        }
        $entry = $this->ldap->query(getenv('LDAP_ROLES_DN'), '(&(objectclass=groupOfNames)(cn=' . ldap_escape($role) . '))')->execute()[0];
        if (!$entry) {
            throw new \OutOfRangeException('invalid role: ' . $role, 1612375711);
        }
        $member = $entry->getAttribute('member');
        $member[] = $this->getDnByUsername($username);
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function removeRole($username, $role) 
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($role) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_ROLES_DN'), $query)->execute()[0];
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
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function getRolesByUsername($username): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(member=' . $this->getDnByUsername($username) . '))';
        $result = $this->ldap->query(getenv('LDAP_ROLES_DN'), $query)->execute();
        $roles = [];
        foreach ($result as $role) {
            $roles[] = $role->getAttribute('cn')[0];
        }
        return $roles;
    }

    public function getIdsByRole(string $roleName): array
    {
        $roles = $this->getRoles();
        foreach ($roles as $role) {
            if ($role['name'] !== $roleName) {
                continue;
            }
            return array_map(function ($user) {return (int)$user['id'];}, $role['users']);
        }
        throw new \OutOfRangeException('invalid role name', 1614374821);
    }

    /**
     * @return array [
     *  ['name' => role name, 
     *   'description' => description,
     *    'users' => [
     *        ['id' => uid, 'username' => username, 'firstname' => first name, 'lastname' => last name],
     *         ...
     *        ], 
     *    ...
     *  ], ... ]
     */
    public function getRoles(): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames))';
        $result = $this->ldap->query(getenv('LDAP_ROLES_DN'), $query)->execute();
        $roles = [];
        foreach ($result as $role) {
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
            }, $role->getAttribute('member'));
            $members = array_filter($members, function ($entry) {
                return $entry !== null;
            });
            $roles[] = [
                    'name' => $role->getAttribute('cn')[0], 
                    'description' => $role->getAttribute('description')[0],
                    'users' => $members
            ];
        }
        return $roles;
    }


    public function hasMoodleCourse(string $username, string $moodleCourse)
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($moodleCourse) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_COURSES_DN'), $query)->execute()[0];
        return !empty($entry);
    }

    public function addMoodleCourse($username, $moodleCourse)
    {
        if ($this->hasMoodleCourse($username, $moodleCourse)) {
            return;
        }
        $userEntry = $this->getEntryByUsername($username);
        if (!$userEntry) {
            throw new \InvalidArgumentException('no such user: ' . $username, 1612375712);
        }
        $entry = $this->ldap->query(getenv('LDAP_COURSES_DN'), '(&(objectclass=groupOfNames)(cn=' . ldap_escape($moodleCourse) . '))')->execute()[0];
        if (!$entry) {
            throw new \OutOfRangeException('invalid moodleCourse: ' . $moodleCourse, 1612375711);
        }
        $member = $entry->getAttribute('member');
        $member[] = $this->getDnByUsername($username);
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function removeMoodleCourse($username, $moodleCourse)
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(cn=' . ldap_escape($moodleCourse) . ')(member=' . $this->getDnByUsername($username) . '))';
        $entry = $this->ldap->query(getenv('LDAP_COURSES_DN'), $query)->execute()[0];
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
        $entry->setAttribute('member', $member);
        $this->ldap->getEntryManager()->update($entry);
    }

    public function getMoodleCoursesByUsername($username): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames)(member=' . $this->getDnByUsername($username) . '))';
        $result = $this->ldap->query(getenv('LDAP_COURSES_DN'), $query)->execute();
        $moodleCourses = [];
        foreach ($result as $moodleCourse) {
            $moodleCourses[] = $moodleCourse->getAttribute('cn')[0];
        }
        return $moodleCourses;
    }

    public function getIdsByMoodleCourse(string $moodleCourseName): array
    {
        $moodleCourses = $this->getMoodleCourses();
        foreach ($moodleCourses as $moodleCourse) {
            if ($moodleCourse['name'] !== $moodleCourseName) {
                continue;
            }
            return array_map(function ($user) {return (int)$user['id'];}, $moodleCourse['users']);
        }
        throw new \OutOfRangeException('invalid moodleCourse name', 1614374821);
    }

    /**
     * @return array [
     *  ['name' => course name,
     *   'description' => description,
     *    'users' => [
     *        ['id' => uid, 'username' => username, 'firstname' => first name, 'lastname' => last name],
     *         ...
     *        ],
     *    ...
     *  ], ... ]
     */
    public function getMoodleCourses(): array
    {
        $this->bind();
        $query = '(&(objectclass=groupOfNames))';
        $result = $this->ldap->query(getenv('LDAP_COURSES_DN'), $query)->execute();
        $moodleCourses = [];
        foreach ($result as $moodleCourse) {
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
            }, $moodleCourse->getAttribute('member'));
            $members = array_filter($members, function ($entry) {
                return $entry !== null;
            });
            $moodleCourses[] = [
                    'name' => $moodleCourse->getAttribute('cn')[0],
                    'description' => $moodleCourse->getAttribute('description')[0],
                    'users' => $members
            ];
        }
        return $moodleCourses;
    }
}
