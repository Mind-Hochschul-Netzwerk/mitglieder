<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\Response;

class UserMessageController extends Controller {
    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
    ) {}

    #[Route('POST /user/{username=>user}/message'), RequireLogin]
    public function sendMessage(User $user, #[RequestValue()] string $message, #[RequestValue()] bool $includeSenderAddress = false): Response {
        if (!$message) {
            throw new InvalidUserDataException('Die Nachricht darf nicht leer sein.');
        }

        $includeSenderAddress = $includeSenderAddress || $this->currentUser->get('sichtbarkeit_email');

        $body = $this->renderToString('mails/user-message', [
            'user' => $user,
            'sender' => $this->currentUser,
            'message' => $message,
            'includeSenderAddress' => $includeSenderAddress,
            'return' => $return = new \stdclass(),
        ]);
        $subject = $return->subject;

        try {
            $this->emailService->sendToUser(
                $user,
                $subject,
                $body,
                [
                   'Reply-To' => $includeSenderAddress ? $this->currentUser->get('email') : null,
                   'Content-Type' => 'text/html; charset=UTF-8',
                ]
            );
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false], 500);
        }

        return $this->json(['success' => true]);
    }
}
