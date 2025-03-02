<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Agreement;
use App\Model\Enum\UserAgreementAction;
use App\Model\User;
use App\Model\UserAgreement;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Service\CurrentUser;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserAgreementController extends Controller {
    #[Route('GET /user/{username=>user}/agreements', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function html(User $user): Response {
        return $this->render('UserAgreementController/index', [
            'user' => $user
        ]);
    }

    #[Route('GET /user/{username=>user}/agreements/index', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function index(User $user, UserAgreementRepository $repo, AgreementRepository $agreementRepo): JsonResponse {
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

    #[Route('POST /user/{username=>user}/agreements/{id=>agreement}', allow: ['role' => 'datenschutz', 'id' => '$user->get("id")'])]
    public function action(User $user, UserAgreementRepository $repo, AgreementRepository $agreementRepo, Agreement $agreement, #[RequestValue()] UserAgreementAction $action, CurrentUser $currentUser): JsonResponse {
        $latest = $repo->findLatestByUserAndName($user, $agreement->name);
        if ($action === UserAgreementAction::Revoke && $latest === null) {
            throw new InvalidUserDataException("user never accepted the agreement '{$agreement->name}'");
        } elseif ($latest->agreement->version > $agreement->version) {
            throw new InvalidUserDataException("user already accepted or rejected a newer agreement");
        }
        $isAdmin = $currentUser->get('id') !== $user->get('id');
        $userAgreement = new UserAgreement(user: $user, agreement: $agreement, action: $action, admin: $isAdmin ? $currentUser : null);
        $repo->persist($userAgreement);
        return $this->index($user, $repo, $agreementRepo);
    }
}
