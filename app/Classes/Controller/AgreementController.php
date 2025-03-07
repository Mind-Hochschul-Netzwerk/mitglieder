<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Controller;

use App\Model\Agreement;
use App\Repository\AgreementRepository;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Exception\InvalidUserDataException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * AgreementController management of agreement texts (API backend)
 */
class AgreementController extends Controller {
    /**
     * Renders the agreement index page.
     *
     * @return Response The rendered HTML response.
     */
    #[Route('GET /agreements', allow: ['role' => 'datenschutz'])]
    public function html(): Response {
        return $this->render('AgreementController/index');
    }

    /**
     * Returns a JSON response with all agreements.
     *
     * @param AgreementRepository $repo The repository for agreements.
     *
     * @return JsonResponse A JSON response containing all agreements.
     */
    #[Route('GET /agreements/api', allow: ['role' => 'datenschutz'])]
    public function index(AgreementRepository $repo): JsonResponse {
        return new JsonResponse(array_map(fn($agreement) => [
            'id' => $agreement->id,
            'name' => $agreement->name,
            'version' => $agreement->version,
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp->format('c'),
            'count' => $agreement->countUsers(),
        ], $repo->findAll()), 200);
    }

    /**
     * Stores a new agreement version.
     *
     * @param AgreementRepository $repo The repository for agreements.
     * @param string $name The name of the agreement.
     * @param string $text The text content of the agreement.
     *
     * @return JsonResponse A JSON response with the updated list of agreements.
     *
     * @throws InvalidUserDataException If the agreement name is invalid.
     */
    #[Route('POST /agreements/api', allow: ['role' => 'datenschutz'])]
    public function store(AgreementRepository $repo, #[RequestValue()] string $name, #[RequestValue()] string $text): JsonResponse {
        $oldVersions = $repo->findAllByName($name);
        if (count($oldVersions) === 0) {
            throw new InvalidUserDataException('`name` is invalid');
        }

        // calculate new version number based on newest version in the database
        $version = $oldVersions[0]->version + 1;

        // create a new agreement and store it
        $agreement = new Agreement(name: $name, version: $version, text: trim($text));
        $repo->persist($agreement);

        return $this->index($repo);
    }
}
