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
use App\Service\CurrentUser;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserAgreementController extends Controller {
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
     * @param UserAgreementRepository $repo Repository for user agreements.
     * @param AgreementRepository $agreementRepo Repository for agreements.
     * @return JsonResponse A JSON response containing the latest agreements and user agreement states.
     */
    #[Route('GET /user/{username=>user}/agreements/index', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function getLatest(User $user, UserAgreementRepository $repo, AgreementRepository $agreementRepo): JsonResponse {
        return new JsonResponse([
            'latest' => array_map(fn($agreement) => [
                'id' => $agreement->id,
                'text' => $agreement->text,
                'version' => $agreement->version,
                'textTimestamp' => $agreement->timestamp->format('c'),
            ], $agreementRepo->findLatestPerName()),
            'state' => array_map(fn ($userAgreement) => [
                'id' => $userAgreement->agreement->id,
                'action'=> $userAgreement->action,
                'version' => $userAgreement->agreement->version,
                'text' => $userAgreement->agreement->text,
                'textTimestamp' => $userAgreement->agreement->timestamp->format('c'),
                'timestamp' => $userAgreement->timestamp->format('c'),
            ], $repo->findLatestByUserPerName($user))
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
    public function action(User $user, UserAgreementRepository $repo, AgreementRepository $agreementRepo, Agreement $agreement, #[RequestValue()] UserAgreementAction $action, CurrentUser $currentUser): JsonResponse {
        $latest = $repo->findLatestByUserAndName($user, $agreement->name);

        // check if the action is valid
        if ($action === UserAgreementAction::Revoke && $latest === null) {
            throw new InvalidUserDataException("user never accepted the agreement '{$agreement->name}'");
        } elseif ($latest->agreement->version > $agreement->version) {
            throw new InvalidUserDataException("user already accepted or rejected a newer agreement");
        }

        // create new entry and store it
        $isAdmin = $currentUser->get('id') !== $user->get('id');
        $userAgreement = new UserAgreement(
            user: $user,
            agreement: $agreement,
            action: $action,
            admin: $isAdmin ? UserInfo::fromUser($currentUser->getWrappedUser()) : null
        );
        $repo->persist($userAgreement);

        return $this->getLatest($user, $repo, $agreementRepo);
    }
}
