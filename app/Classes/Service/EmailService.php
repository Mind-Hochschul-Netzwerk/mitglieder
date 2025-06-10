<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use PHPMailer\PHPMailer\PHPMailer;

/**
 * send emails
 */
class EmailService implements \App\Interfaces\Singleton
{
    use \App\Traits\Singleton;

    private $mailer = null;

    private function __construct()
    {
        if (!getenv('SMTP_HOST') || getenv('SMTP_HOST') === 'log') {
            return;
        }

        $this->mailer = new PHPMailer(true);

        $this->mailer->isSMTP();
        $this->mailer->Host = getenv('SMTP_HOST');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('SMTP_USER');
        $this->mailer->Password = getenv('SMTP_PASSWORD');
        switch (getenv('SMTP_SECURE')) {
            case "ssl":
            case "smtps":
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case "tls":
            case "starttls":
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            default:
                throw new \Exception('unexpected value for SMTP_SECURE');
                break;
        }
        $this->mailer->Port = getenv('SMTP_PORT');
        $this->mailer->setFrom(getenv('FROM_ADDRESS'), 'Mind-Hochschul-Netzwerk');
        $this->mailer->addReplyTo('IT@' . getenv('DOMAINNAME'), 'IT-Team');
        $this->mailer->CharSet = 'utf-8';
    }

    public function send(string|array $addresses, string $subject, string $body): bool
    {
        if (!is_array($addresses)) {
            $addresses = [$addresses];
        }
        if ($this->mailer === null) {
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

        $this->mailer->ClearAddresses();
        $this->mailer->ClearCCs();
        $this->mailer->ClearBCCs();

        $this->mailer->Subject = $subject;
        $this->mailer->Body = $body;

        try {
            foreach ($addresses as $address) {
                $this->mailer->addAddress($address);
            }
            return $this->mailer->send();
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
