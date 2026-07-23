<?php
declare(strict_types=1);
namespace App\Controller;

use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\ListPostPolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Group;
use App\Model\User;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\RateLimiter;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\AccessDeniedException;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    private const JOIN_REQUEST_TTL = 7 * 24 * 3600;

    private function mayManage(Group $group): bool
    {
        if ($group->isOwner($this->currentUser->get('username'))) {
            return true;
        }
        if ($group->privileged) {
            return $this->currentUser->hasRole('rechte');
        }
        return $this->currentUser->hasRole('groupadmin');
    }

    private function requireManage(Group $group): void
    {
        if (!$this->mayManage($group)) {
            throw new AccessDeniedException();
        }
    }

    #[Route('GET /groups'), RequireLogin]
    public function index(GroupRepository $groupRepository): Response
    {
        $allGroups = $groupRepository->findAll();
        $isGroupAdmin = $this->currentUser->hasRole('groupadmin');
        $username = $this->currentUser->get('username');

        $groups = array_values(array_filter($allGroups, function (Group $g) use ($isGroupAdmin, $username) {
            return match ($g->visibility) {
                GroupVisibility::Public  => true,
                GroupVisibility::Members => $isGroupAdmin || $g->isMember($username) || $g->isOwner($username),
                GroupVisibility::Hidden  => $isGroupAdmin || $g->isOwner($username),
            };
        }));

        usort($groups, fn(Group $a, Group $b) => [$a->category, $a->displayName ?: $a->name] <=> [$b->category, $b->displayName ?: $b->name]);

        return $this->render('GroupController/index', [
            'groups' => $groups,
            'isGroupAdmin' => $isGroupAdmin,
            'username' => $username,
        ]);
    }

    #[Route('GET /groups/{name=>group}'), RequireLogin]
    public function show(Group $group, UserRepository $userRepository): Response
    {
        $username = $this->currentUser->get('username');
        $isGroupAdmin = $this->currentUser->hasRole('groupadmin');
        $isMember = $group->isMember($username);
        $isOwner = $group->isOwner($username);
        $mayManage = $this->mayManage($group);

        $canView = match ($group->visibility) {
            GroupVisibility::Public  => true,
            GroupVisibility::Members => $isMember || $isOwner || $isGroupAdmin,
            GroupVisibility::Hidden  => $isOwner || $isGroupAdmin,
        };
        if (!$canView) {
            throw new AccessDeniedException();
        }

        $showMembers = match ($group->memberVisibility) {
            MemberVisibility::All     => true,
            MemberVisibility::Members => $isMember || $isOwner || $isGroupAdmin,
            MemberVisibility::Owners  => $isOwner || $isGroupAdmin,
        };

        $nonMembers = [];

        $userInfoByUsername = [];
        foreach ($userRepository->getAllUserinfos() as $info) {
            $userInfoByUsername[$info->userName] = $info;
        }

        $ownerInfos = array_map(
            fn($u) => $userInfoByUsername[$u] ?? new \App\Model\UserInfo(userName: $u, realName: $u),
            $group->ownerUsernames
        );

        // owners are always shown (even without member-list access); the rest of the
        // member list is only added on top when the viewer is allowed to see it
        $displayInfos = $ownerInfos;
        if ($showMembers) {
            $memberInfos = array_map(
                fn($u) => $userInfoByUsername[$u] ?? new \App\Model\UserInfo(userName: $u, realName: $u),
                $group->memberUsernames
            );
            $displayByUsername = [];
            foreach ([...$memberInfos, ...$ownerInfos] as $info) {
                $displayByUsername[$info->userName] = $info;
            }
            $displayInfos = array_values($displayByUsername);
        }
        usort($displayInfos, fn($a, $b) => $a->realName <=> $b->realName);

        foreach ($userInfoByUsername as $info) {
            if (!$group->isMember($info->userName)) {
                $nonMembers[] = $info;
            }
        }
        usort($nonMembers, fn($a, $b) => $a->realName <=> $b->realName);

        return $this->render('GroupController/show', [
            'group' => $group,
            'isGroupAdmin' => $isGroupAdmin,
            'isRechteAdmin' => $this->currentUser->hasRole('rechte'),
            'isMember' => $isMember,
            'isOwner' => $isOwner,
            'mayManage' => $mayManage,
            'showMembers' => $showMembers,
            'displayInfos' => $displayInfos,
            'nonMembers' => $nonMembers,
            'username' => $username,
            'joinPolicyCases' => JoinPolicy::cases(),
            'leavePolicyCases' => LeavePolicy::cases(),
            'memberVisibilityCases' => MemberVisibility::cases(),
            'visibilityCases' => GroupVisibility::cases(),
            'listPostPolicyCases' => ListPostPolicy::cases(),
        ]);
    }

    #[Route('POST /groups'), AllowIf(role: 'groupadmin')]
    public function create(GroupRepository $groupRepository): Response
    {
        $input = $this->validatePayload([
            'name' => 'string required',
            'displayName' => 'string',
            'category' => 'string',
        ]);

        if (!preg_match('/^[a-z][a-z0-9\-_]*$/', $input['name'])) {
            throw new InvalidUserDataException('Gruppenname darf nur Kleinbuchstaben, Ziffern, - und _ enthalten und muss mit einem Buchstaben beginnen.');
        }
        if ($groupRepository->findOneByName($input['name'])) {
            throw new InvalidUserDataException('Eine Gruppe mit diesem Namen existiert bereits.');
        }

        $group = new Group(
            name: $input['name'],
            displayName: $input['displayName'],
            category: $input['category'],
        );
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true, 'redirect' => '/groups']);
        }
        return $this->redirect('/groups');
    }

    #[Route('POST /groups/{name=>group}'), RequireLogin]
    public function update(Group $group, GroupRepository $groupRepository): Response
    {
        $this->requireManage($group);
        $isGroupAdmin = $this->currentUser->hasRole('groupadmin');

        $input = $this->validatePayload([
            'displayName'      => 'string',
            'description'      => 'string',
            'category'         => 'string',
            'joinPolicy'       => 'string',
            'leavePolicy'      => 'string',
            'memberVisibility' => 'string',
            'visibility'       => 'string',
            'privileged'       => 'bool',
            'isMailGroup'      => 'bool',
            'mailAddress'      => 'string',
            'listLabel'        => 'string',
            'listPostPolicy'   => 'string',
            'listSenderRewrite'=> 'string',
        ]);

        $group->displayName = $input['displayName'];
        $group->description = $input['description'];
        $group->category    = $input['category'];

        if ($joinPolicy = JoinPolicy::tryFrom($input['joinPolicy'])) {
            $group->joinPolicy = $joinPolicy;
        }
        if ($leavePolicy = LeavePolicy::tryFrom($input['leavePolicy'])) {
            $group->leavePolicy = $leavePolicy;
        }
        if ($memberVisibility = MemberVisibility::tryFrom($input['memberVisibility'])) {
            $group->memberVisibility = $memberVisibility;
        }

        if ($this->currentUser->hasRole('rechte')) {
            $group->privileged = $input['privileged'];
        }

        if ($isGroupAdmin) {
            if ($visibility = GroupVisibility::tryFrom($input['visibility'])) {
                $group->visibility = $visibility;
            }
            $group->mailAddress = $input['mailAddress'] !== '' ? $input['mailAddress'] : null;
            $group->isMailGroup = $input['isMailGroup'];
        }

        if ($group->isMailGroup) {
            $group->listLabel = $input['listLabel'];
            if ($listPostPolicy = ListPostPolicy::tryFrom($input['listPostPolicy'])) {
                $group->listPostPolicy = $listPostPolicy;
            }
            $group->listSenderRewrite = $input['listSenderRewrite'];
        }

        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('DELETE /groups/{name=>group}'), AllowIf(role: 'groupadmin')]
    public function delete(Group $group, GroupRepository $groupRepository): Response
    {
        if ($group->privileged && !$this->currentUser->hasRole('rechte')) {
            throw new AccessDeniedException();
        }
        $groupRepository->delete($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true, 'redirect' => '/groups']);
        }
        return $this->redirect('/groups');
    }

    #[Route('POST /groups/{name=>group}/join'), RequireLogin]
    public function join(Group $group, GroupRepository $groupRepository): Response
    {
        if ($group->privileged) {
            throw new AccessDeniedException();
        }
        if ($group->joinPolicy !== JoinPolicy::Open) {
            throw new InvalidUserDataException('Diese Gruppe erlaubt kein freies Beitreten.');
        }
        $username = $this->currentUser->get('username');
        if ($group->isMember($username)) {
            throw new InvalidUserDataException('Du bist bereits Mitglied dieser Gruppe.');
        }
        $group->addMember($username);
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('POST /groups/{name=>group}/leave'), RequireLogin]
    public function leave(Group $group, GroupRepository $groupRepository): Response
    {
        if ($group->leavePolicy !== LeavePolicy::Allowed) {
            throw new InvalidUserDataException('Diese Gruppe kann nicht verlassen werden.');
        }
        $username = $this->currentUser->get('username');
        if (!$group->isMember($username)) {
            throw new InvalidUserDataException('Du bist kein Mitglied dieser Gruppe.');
        }
        $group->removeMember($username);
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('POST /groups/{name=>group}/join-requests'), RequireLogin]
    public function requestJoin(
        Group $group,
        UserRepository $userRepository,
        RateLimiter $rateLimiter,
        EmailService $emailService,
    ): Response {
        if ($group->privileged) {
            throw new AccessDeniedException();
        }
        if ($group->joinPolicy !== JoinPolicy::Request) {
            throw new InvalidUserDataException('Diese Gruppe verwendet keine Beitrittsanfragen.');
        }
        $username = $this->currentUser->get('username');
        if ($group->isMember($username)) {
            throw new InvalidUserDataException('Du bist bereits Mitglied dieser Gruppe.');
        }

        $rateLimiter->attempt('join-request', $username, 3, 3600);

        $baseUrl = 'https://mitglieder.' . getenv('DOMAINNAME');
        $approveToken = Token::encode(['approve', $group->name, $username, time()], '', getenv('TOKEN_KEY'));
        $rejectToken  = Token::encode(['reject',  $group->name, $username, time()], '', getenv('TOKEN_KEY'));

        $ownerEmails = [];
        foreach ($group->ownerUsernames as $ownerUsername) {
            $owner = $userRepository->findOneByUsername($ownerUsername);
            if ($owner !== null) {
                $ownerEmails[] = $owner->get('email');
            }
        }
        if (empty($ownerEmails)) {
            throw new \RuntimeException('Diese Gruppe hat keine Owner, die die Anfrage bearbeiten könnten.');
        }

        $body = $this->renderToString('mails/group-join-request', [
            'group' => $group,
            'requester' => $this->currentUser,
            'approveUrl' => "$baseUrl/groups/join-request?token=$approveToken",
            'rejectUrl'  => "$baseUrl/groups/join-request?token=$rejectToken",
            'return' => $return = new \stdclass(),
        ]);

        $emailService->send(
            $ownerEmails,
            $return->subject,
            $body,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );

        if ($this->isJsonResponse) {
            return $this->json(['success' => true, 'message' => 'Deine Anfrage wurde an die Gruppenverantwortlichen gesendet.']);
        }
        return $this->redirect('/groups');
    }

    #[Route('GET /groups/join-request?token={token}'), PublicAccess]
    public function handleJoinRequest(
        string $token,
        GroupRepository $groupRepository,
    ): Response {
        $action = '';
        $groupName = '';
        $username = '';

        try {
            Token::decode($token, function ($data) use (&$action, &$groupName, &$username) {
                if (time() - $data[3] > self::JOIN_REQUEST_TTL) {
                    throw new \Exception('token expired');
                }
                [$action, $groupName, $username] = $data;
                return '';
            }, getenv('TOKEN_KEY'));
        } catch (\Exception) {
            throw new InvalidUserDataException('Der Link ist ungültig oder abgelaufen.');
        }

        $group = $groupRepository->findOneByName($groupName);
        if (!$group) {
            throw new InvalidUserDataException('Die Gruppe wurde nicht gefunden.');
        }

        if ($action === 'approve' && !$group->isMember($username)) {
            $group->addMember($username);
            $groupRepository->save($group);
        }

        return $this->render('GroupController/join-request-result', [
            'action' => $action,
            'group' => $group,
            'username' => $username,
        ]);
    }

    #[Route('POST /groups/{name=>group}/members/{username=>user}'), RequireLogin]
    public function addMember(Group $group, User $user, GroupRepository $groupRepository): Response
    {
        $this->requireManage($group);

        if ($group->isMember($user->get('username'))) {
            throw new InvalidUserDataException('Die Person ist bereits Mitglied dieser Gruppe.');
        }
        $group->addMember($user->get('username'));
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('DELETE /groups/{name=>group}/members/{username=>user}'), RequireLogin]
    public function removeMember(Group $group, User $user, GroupRepository $groupRepository): Response
    {
        $this->requireManage($group);

        $group->removeMember($user->get('username'));
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('POST /groups/{name=>group}/owners/{username=>user}'), RequireLogin]
    public function addOwner(Group $group, User $user, GroupRepository $groupRepository): Response
    {
        $this->requireManage($group);

        if ($group->isOwner($user->get('username'))) {
            throw new InvalidUserDataException('Die Person ist bereits Owner dieser Gruppe.');
        }
        $group->addOwner($user->get('username'));
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }

    #[Route('DELETE /groups/{name=>group}/owners/{username=>user}'), RequireLogin]
    public function removeOwner(Group $group, User $user, GroupRepository $groupRepository): Response
    {
        $this->requireManage($group);

        $targetUsername = $user->get('username');
        if ($targetUsername === $this->currentUser->get('username') && !$this->currentUser->hasRole('groupadmin')) {
            throw new InvalidUserDataException('Du kannst dich nicht selbst als Owner entfernen.');
        }

        $group->removeOwner($targetUsername);
        $groupRepository->save($group);

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        }
        return $this->redirect('/groups');
    }
}
