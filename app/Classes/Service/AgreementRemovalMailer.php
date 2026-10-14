<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Agreement;
use App\Model\Group;
use App\Model\MailAddress;
use App\Repository\UserRepository;
use Latte\Engine as Latte;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Informiert, wenn Mitglieder aus einer Gruppe entfernt wurden, weil sie die von der Gruppe verlangte Version der
 * Verpflichtung zum Datenschutz nicht abgegeben haben: die Betroffenen selbst und die Gruppenverwaltung (Owner).
 * Fehler beim Versand werden nur geloggt, die Entfernung selbst ist dann schon geschehen.
 */
class AgreementRemovalMailer
{
    public function __construct(
        private Latte $latte,
        private EmailService $emailService,
        private UserRepository $userRepository,
    ) {}

    /** Mail an das entfernte Mitglied */
    public function notifyMember(string $username, Group $group, Agreement $agreement): void
    {
        $this->send($username, 'mails/group-removed-agreement', [
            'group' => $group,
            'agreement' => $agreement,
            'agreementUrl' => 'https://mitglieder.' . getenv('DOMAINNAME') . '/user/' . rawurlencode($username) . '/agreements',
        ], $group);
    }

    /**
     * Mail an die Gruppenverwaltung mit den Namen der entfernten Mitglieder
     *
     * @param string[] $removedUsernames
     * @param string[] $removedNames
     */
    public function notifyOwners(Group $group, array $removedUsernames, array $removedNames, Agreement $agreement): void
    {
        foreach ($group->ownerUsernames as $ownerUsername) {
            if (in_array($ownerUsername, $removedUsernames, true)) {
                continue; // wer selbst entfernt wurde, bekommt die Mail für Betroffene
            }
            $this->send($ownerUsername, 'mails/group-members-removed-agreement', [
                'group' => $group,
                'agreement' => $agreement,
                'removedNames' => $removedNames,
            ], $group);
        }
    }

    private function send(string $username, string $template, array $params, Group $group): void
    {
        try {
            $user = $this->userRepository->findOneByUsername($username);
            if ($user === null || MailAddress::isInvalid($user->get('email'))) {
                error_log('Gruppe ' . $group->name . ': ' . $username . ' hat keine gültige Mailadresse; keine Information gesendet.');
                return;
            }
            $body = $this->latte->renderToString($template . '.latte', $params + [
                'fullName' => $user->get('fullName'),
                'return' => $return = new \stdClass(),
            ]);
            $this->emailService->sendToUser($user, $return->subject, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            error_log('Gruppe ' . $group->name . ': Information an ' . $username . ' über die Entfernung fehlgeschlagen: ' . $e->getMessage()
                . ($e->getPrevious() ? ' (' . $e->getPrevious()->getMessage() . ')' : ''));
        }
    }
}
