<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\User;
use App\Repository\UserRepository;
use LogicException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * send emails
 */
class EmailService
{
    private UserRepository $userRepository;
    private Ldap $ldap;

    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $secure,
        private string $port,
        private string $fromAddress,
        private string $domain,
    )
    {
    }

    public function setLdap(Ldap $ldap) {
        $this->ldap = $ldap;
    }

    public function setUserRepository(UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    private function getMailer(): ?PHPMailer
    {
        if (!$this->host || $this->host === 'log') {
            return null;
        }

        $mailer = new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->Host = $this->host;
        $mailer->Port = $this->port;

        $mailer->SMTPAuth = true;
        $mailer->Username = $this->user;
        $mailer->Password = $this->password;

        switch ($this->secure) {
            case "ssl":
            case "smtps":
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case "tls":
            case "starttls":
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            default:
                throw new \Exception('unexpected value for parameter $secure');
                break;
        }

        $mailer->setFrom($this->fromAddress, 'Mind-Hochschul-Netzwerk');
        $mailer->addReplyTo('IT@' . $this->domain, 'IT-Team');
        $mailer->CharSet = 'utf-8';

        return $mailer;
    }

    /**
     * @throws \RuntimeException wenn eine E-Mail nicht versandt werden konnte.
     */
    public function send(string|array $addresses, string $subject, string $body): bool
    {
        if (!is_array($addresses)) {
            $addresses = [$addresses];
        }

        $mailer = $this->getMailer();

        if (!$mailer) {
            error_log("
--------------------------------------------------------------------------------
SMTP_HOST is not set in .env
Mail to: ".(implode(', ', $addresses))."
Subject: $subject

$body
--------------------------------------------------------------------------------
");
            return true;
        }

        $mailer->Subject = $subject;
        $mailer->Body = $body;

        try {
            foreach ($addresses as $address) {
                $mailer->addAddress($address);
            }
            return $mailer->send();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @throws \RuntimeException wenn eine E-Mail nicht versandt werden konnte.
     */
    public function sendToUser(User $user, string $subject, string $body): void
    {
        try {
            $this->send($user->get('email'), $subject, $body);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Beim Versand der E-Mail an ' . $user->get('email') . ' (ID ' . $user->get('id') . ') ist ein Fehler aufgetreten.', 1522422201);
        }
    }

    /**
     * @throws \RuntimeException wenn eine E-Mail nicht versandt werden konnte.
     */
    public function sendToGroup(string $groupname, string $subject, string $body): void
    {
        if (!isset($this->ldap)) {
            throw new \LogicException('use EmailService::setLdap() before sendToGroup()');
        }
        if (!isset($this->ldap)) {
            throw new \LogicException('use EmailService::setUserRepository() before sendToUser()');
        }
        $ids = $this->ldap->getIdsByGroup('mvedit');
        foreach ($ids as $id) {
            $user = $this->userRepository->findOneById($id);
            if ($user !== null) {
                $this->sendToUser($user, $subject, $body);
            }
        }
    }
}
