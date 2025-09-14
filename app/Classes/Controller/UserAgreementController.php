<?php
/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Controller;

use App\Model\Agreement;
use App\Model\Enum\UserAgreementAction;
use App\Model\User;
use App\Model\UserAgreement;
use App\Model\UserInfo;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use App\Service\Ldap;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Latte\Engine as Latte;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAgreementController extends Controller {
    public function __construct(
        protected Request $request,
        protected Latte $latte,
        private CurrentUser $currentUser,
        private EmailService $emailService,
        private Ldap $ldap,
        private AgreementRepository $agreementRepository,
        private UserAgreementRepository $userAgreementRepository,
        private UserRepository $userRepository,
    )
    {
    }

    /**
     * Renders the HTML view for the user's agreements.
     *
     * @param User $user The user whose agreements should be displayed.
     * @return Response The rendered HTML response.
     */
    #[Route('GET /user/{username=>user}/agreements', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function html(User $user): Response {
        return $this->render('UserAgreementController/index', [
            'user' => $user
        ]);
    }

    /**
     * Returns the latest agreements and their current state for the given user.
     *
     * @param User $user The user whose agreements should be retrieved.
     * @return JsonResponse A JSON response containing the latest agreements and user agreement states.
     */
    #[Route('GET /user/{username=>user}/agreements/index', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function getLatest(User $user): JsonResponse {
        return new JsonResponse([
            'latest' => array_map(fn($agreement) => [
                'id' => $agreement->id,
                'text' => $agreement->text,
                'version' => $agreement->version,
                'textTimestamp' => $agreement->timestamp->format('c'),
            ], $this->agreementRepository->findLatestPerName()),
            'state' => array_map(fn ($userAgreement) => [
                'id' => $userAgreement->agreement->id,
                'action'=> $userAgreement->action,
                'version' => $userAgreement->agreement->version,
                'text' => $userAgreement->agreement->text,
                'textTimestamp' => $userAgreement->agreement->timestamp->format('c'),
                'timestamp' => $userAgreement->timestamp->format('c'),
            ], $this->userAgreementRepository->findLatestByUserPerName($user))
        ]);
    }

    /**
     * Handles user actions on agreements (accept or revoke).
     *
     * @param User $user The user performing the action.
     * @param UserAgreementRepository $repo Repository for user agreements.
     * @param AgreementRepository $agreementRepo Repository for agreements.
     * @param Agreement $agreement The agreement being acted upon.
     * @param UserAgreementAction $action The action taken on the agreement (accept/revoke).
     * @param CurrentUser $currentUser The currently authenticated user.
     * @return JsonResponse A JSON response with the updated agreement state.
     * @throws InvalidUserDataException If the action is invalid based on previous agreements.
     */
    #[Route('POST /user/{username=>user}/agreements/{id=>agreement}', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function action(User $user, Agreement $agreement, #[RequestValue()] UserAgreementAction $action): JsonResponse {
        $latest = $this->userAgreementRepository->findLatestByUserAndName($user, $agreement->name);

        // check if the action is valid
        if ($action === UserAgreementAction::Revoke && $latest === null) {
            throw new InvalidUserDataException("user never accepted the agreement '{$agreement->name}'");
        } elseif ($latest !== null && $latest->agreement->version > $agreement->version) {
            throw new InvalidUserDataException("user already accepted or rejected a newer agreement");
        }

        // create new entry and store it
        $isAdmin = $this->currentUser->get('id') !== $user->get('id');
        $userAgreement = new UserAgreement(
            user: $user,
            agreement: $agreement,
            action: $action,
            admin: $isAdmin ? UserInfo::fromUser($this->currentUser->getWrappedUser()) : null
        );
        $this->userAgreementRepository->persist($userAgreement);

        // send mail in case somebody revokes the 'Datenschutzverpflichtung' agreement
        if ($agreement->name === 'Datenschutzverpflichtung' && $userAgreement->action === UserAgreementAction::Revoke) {
            $text = $this->renderToString('UserAgreementController/revoke.mail', [
                'user' => $user,
                'recorderName' => $this->currentUser->get('fullName'),
                'url' => 'https://mitglieder.' . getenv('DOMAINNAME') . '/user/' . urlencode($user->get('username')),
                'return' => $return = new \stdclass,
            ]);
            $this->emailService->send([
                'datenschutz@mind-hochschul-netzwerk.de',
                'vorstand@mind-hochschul-netzwerk.de',
            ], $return->subject, $text);
        }

        return $this->getLatest($user);
    }
}
