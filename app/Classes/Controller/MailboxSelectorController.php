<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Model\User;
use App\Repository\GroupRepository;
use App\Service\CurrentUser;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class MailboxSelectorController extends Controller {
    const string LOGIN_FORM = 'login';

    private array $mailboxes = [];

    public function __construct(
        GroupRepository $groupRepository,
        CurrentUser $currentUser,
    ) {
        $this->mailboxes = $this->getAvailableMailboxes($currentUser->getWrappedUser(), $groupRepository);
    }

    #[Route('GET /mail-login'), RequireLogin]
    public function show(): Response
    {
        return match (count($this->mailboxes)) {
            0 => $this->redirectToMailbox(self::LOGIN_FORM),
            //1 => $this->redirectToMailbox($this->mailboxes[0]),
            default => $this->showSelector(),
        };
    }

    private function showSelector(): Response
    {
        return $this->render('MailboxSelectorController/show', [
            'mailboxes' => $this->mailboxes,
        ]);
    }

    #[Route('GET /mail-login?mailbox={mailbox}'), RequireLogin]
    public function select(string $mailbox): Response
    {
        if ($mailbox === '') {
            return $this->showSelector();
        }

        if ($mailbox !== self::LOGIN_FORM && !in_array($mailbox, $this->mailboxes)) {
            return $this->showError('Diese Postfach-Adresse ist nicht gültig.');
        }

        return $this->redirectToMailbox($mailbox);
    }

    public static function getAvailableMailboxes(?User $user, GroupRepository $groupRepository): array
    {
        if (!$user) {
            return [];
        }
        $mail = $user->get('email');
        $domain = strtolower(substr($mail, strpos($mail, '@') + 1));
        if (!in_array($domain, [getenv('DOMAINNAME'), 'mind-akademie.de'])) {
            $mail = '';
        }
        $orgEmail = $user->get('orgEmail');
        $groupNames = $user->getGroups();
        $groups = array_map(fn($name) => $groupRepository->findOneByName($name), $groupNames);
        $groups = array_filter($groups, fn($group) => !$group->isMailingList() && $group->mailAddress !== null);
        $mailboxes = array_filter([
            $mail,
            $orgEmail,
            ...array_map(fn($group) => $group->mailAddress, $groups),
        ]);
        foreach ($mailboxes as &$address) {
            [$localpart, $domain] = explode('@', $address);
            if (str_contains($localpart, '+')) {
                $name = substr($localpart, strpos($localpart, '+'));
                $address = $name . '@' . $domain;
            }
	    }

        return array_values(array_unique($mailboxes));
    }

    private function redirectToMailbox(string $mailbox): Response
    {
        $token = self::signToken([
            'mailbox' => $mailbox,
            'sub'     => $this->currentUser->get('username'),
            'exp'     => time() + 12*3600,
        ]);
        $url = 'https://mail.' . getenv('DOMAINNAME') . '/mail?mailbox=' . urlencode($mailbox);
        return $this->redirect($url . '&mbx=' . urlencode($token));
    }

    private static function base64urlencode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function signToken(array $payload): string {
        $body = self::base64urlencode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig  = self::base64urlencode(hash_hmac('sha256', $body, getenv('TOKEN_KEY'), true));
        return $body . '.' . $sig;
    }
}
