<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App\Controller;

use App\Model\User;
use App\Repository\GroupRepository;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class MailboxSelectorController extends Controller {
    public function __construct(
        private GroupRepository $groupRepository,
    ) {}

    #[Route('GET /mail-login'), RequireLogin]
    public function show(): Response
    {
        $mailboxes = $this->getAvailableMailboxes($this->currentUser->getWrappedUser(), $this->groupRepository);

        return match (count($mailboxes)) {
            0 => $this->showError('Du hast kein MHN-Postfach zugeordnet.'),
            1 => $this->redirectToMailbox($mailboxes[0]),
            default => $this->render('MailboxSelectorController/show', [
                'mailboxes' => $mailboxes,
            ]),
        };
    }

    #[Route('GET /mail-login?mailbox={mailbox}'), RequireLogin]
    public function select(string $mailbox): Response
    {
        if (!in_array($mailbox, $this->getAvailableMailboxes($this->currentUser->getWrappedUser(), $this->groupRepository), true)) {
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

        return array_unique($mailboxes);
    }

    private function redirectToMailbox(string $mailbox): Response
    {
        $token = self::signToken([
            'mailbox' => $mailbox,
            'sub'     => $this->currentUser->get('username'),
            'exp'     => time() + 12*3600,
        ]);
        $url = 'https://mail.' . getenv('DOMAINNAME') . '/';
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
