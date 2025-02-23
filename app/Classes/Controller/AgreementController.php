<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Agreement;
use App\Repository\AgreementRepository;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Router\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AgreementController extends Controller {
    #[Route('GET /agreements', allow: ['role' => 'datenschutz'])]
    public function view(): Response {
        return $this->render('AgreementController/index');
    }

    #[Route('GET /agreements/index', allow: ['role' => 'datenschutz'])]
    public function index(AgreementRepository $repo, Router $router): JsonResponse {
        return new JsonResponse(array_map(fn($agreement) => [
            'id' => $agreement->id,
            'name' => $agreement->name,
            'version' => $agreement->version,
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp->format('c'),
        ], $repo->findAll()), 200);
    }

    #[Route('POST /agreements', allow: ['role' => 'datenschutz'])]
    public function store(AgreementRepository $repo, Router $router,
      #[RequestValue()] string $name, #[RequestValue()] string $text): JsonResponse {
        $oldVersions = $repo->findAllByName($name);
        if (count($oldVersions) === 0) {
            throw new InvalidUserDataException('`name` is invalid');
        }
        $version = $oldVersions[0]->version + 1;
        $agreement = new Agreement(name: $name, version: $version, text: $text);
        $repo->persist($agreement);
        return $this->index($repo, $router);
    }
}
