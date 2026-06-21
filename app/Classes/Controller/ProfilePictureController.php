<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\ImageResizer;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\QueryValue;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\RequireLogin;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ProfilePictureController extends Controller {
    // Maximale Größe von Profilbildern
    const profilbildMaxWidth = 800;
    const profilbildMaxHeight = 800;
    const thumbnailMaxWidth = 200;
    const thumbnailMaxHeight = 200;

    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Route('GET /user/{username=>user}/profile-picture'), RequireLogin]
    public function show(User $user, #[QueryValue] string $size = 'full'): Response {
        if (!$user->get('profilbild') || !is_file(User::PROFILE_PICUTRE_DIRECTORY . '/' . $user->get('profilbild'))) {
            return $this->redirect('/img/profilbild-default.png');
        }

        // "jpeg" or "png"
        $type = strtolower(pathinfo($user->get('profilbild'), PATHINFO_EXTENSION));

        $prefix = $size === 'thumbnail' ? 'thumbnail-' : '';

        return new Response(
            file_get_contents(User::PROFILE_PICUTRE_DIRECTORY . '/' . $prefix . $user->get('profilbild')),
            headers: ['Content-Type' => 'image/' . $type]
        );
    }

    #[
        Route('POST /user/{username=>user}/profile-picture'),
        AllowIf(role: 'mvedit'),
        AllowIf(id: '$user->get("id")'),
    ]
    public function update(User $user, ?UploadedFile $profilbild = null, #[RequestValue] bool $bildLoeschen = false): Response
    {
        if ($bildLoeschen) {
            $user->deleteProfilePicture();
            $this->userRepository->save($user);
            return $this->isJsonResponse ? $this->json(["src" => ""]) : $this->redirect('/user/' . $user->get('username') . '/edit');
        }

        $file = $profilbild;

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE || !$file->isValid()) {
            throw new \RuntimeException('Das Profilbild konnte nicht hochgeladen werden.');
        }

        $type = null;
        switch ($file->getMimeType()) {
            case 'image/jpeg':
                $type = 'jpeg';
                break;
            case 'image/png':
                $type = 'png';
                break;
            default:
                if ($this->isJsonResponse) {
                    throw new \RuntimeException('Das Profilbild konnte nicht hochgeladen werden. Unbekanntes Format.');
                } else {
                    // TODO: über den Redirect merken
                    $this->setTemplateVariable('profilbild_format_unbekannt', true);
                    return $this->redirect('/user/' . $user->get('username') . '/edit');
                }
        }

        // Dateiname zufällig wählen
        $fileName = sprintf("%s-%s.%s", $user->get('id'), uniqid(), $type);

        // Datei und Thumbnail erstellen
        ImageResizer::resize($file->getPathname(), User::PROFILE_PICUTRE_DIRECTORY . '/' . $fileName, $type, $type, self::profilbildMaxWidth, self::profilbildMaxHeight);
        ImageResizer::resize($file->getPathname(), User::PROFILE_PICUTRE_DIRECTORY . '/thumbnail-' . $fileName, $type, $type, self::thumbnailMaxWidth, self::thumbnailMaxHeight);

        $user->deleteProfilePicture();
        $user->set('profilbild', $fileName);

        $this->userRepository->save($user);

        return $this->isJsonResponse ? $this->json(["src" => "/user/" . $user->get('username') . "/profile-picture?" . time()]) : $this->redirect('/user/' . $user->get('username') . '/edit');
    }
}
