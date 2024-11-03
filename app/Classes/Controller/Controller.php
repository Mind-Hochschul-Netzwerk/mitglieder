<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Router\Exception\AccessDeniedException;
use App\Service\Router\Exception\InvalidCsrfTokenException;
use App\Service\Router\Exception\InvalidRouteException;
use App\Service\Router\Exception\InvalidUserDataException;
use App\Service\Router\Exception\NotFoundException;
use App\Service\Router\Exception\NotLoggedInException;
use App\Service\Tpl;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Controller {
    public function __construct(protected Request $request)
    {
    }

    protected function setTemplateVariable(string $key, mixed $value): void {
        Tpl::getInstance()->set($key, $value);
    }

    protected function redirect(string $uri): RedirectResponse {
        return new RedirectResponse($uri);
    }

    protected function render(string $templateName, array $data = []): Response {
        return new Response(Tpl::getInstance()->render($templateName, $data));
    }

    public function showError(string $message, int $responseCode = 200): Response {
        $response = $this->render('Layout/errorpage', ['text' => $message]);
        $response->setStatusCode($responseCode);
        return $response;
    }

    public function showMessage(string $title, string $message): Response {
        $response = $this->render('Layout/message', [
            'title' => $title,
            'text' => $message,
        ]);
        return $response;
    }

    /**
     * checks if requirements are met and returns a sanitized array of the user data
     *
     * @param $requirements ['key' => requirement, ...]
     *      where requirements is a string of requirements joined by ' '
     *      requirements:
     *          "required": throw an InvalidUserDataException if the key is not present instead of setting a default value
     *          "string": value is a string (will be trimmed)
     *            "untrimmed":  do not trim
     *          "int": value is a (positive or negative) integer
     *          "uint": value is a positive intenger
     *          "bool": value is boolean
     *          "set": true if key is set (even if value is falsish)
     *          "date": YYYY-MM-DD, default: 0000-00-00
     *
     */
    protected function validatePayload(array $requirements): array {
        $values = [];
        $payload = $this->request->getPayload();
        foreach ($requirements as $key => $rqm) {
            $rqms = explode('|', $rqm);

            if (!$payload->has($key) && in_array('required', $rqms, true)) {
                throw new InvalidUserDataException('Eingabedaten unvollständig: ' . $key);
            }

            $value = null;
            if (in_array('string', $rqms)) {
                $value = $payload->getString($key);
            } elseif (in_array('set', $rqms)) {
                $value = $payload->has($key);
            } elseif (in_array('bool', $rqms)) {
                $value = $payload->getBoolean($key);
            } elseif (in_array('int', $rqms)) {
                $value = $payload->getInt($key);
            } elseif (in_array('uint', $rqms)) {
                $value = abs($payload->getInt($key));
            } elseif (in_array('date', $rqms, true)) {
                $value = trim($payload->getString($key));
                if (!preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $value)) {
                    $value = '0000-00-00';
                } else {
                    [$Y, $M, $D] = explode('-', $value);
                    $value = sprintf('%04d-%02d-%02d', $Y, $M, $D);
                }
            } else {
                $value = $payload->get($key);
            }
            $values[$key] = $value;
        }
        return $values;
    }

    public static function handleException(\Exception $e, Request $request): Response {
        if ($e instanceof InvalidRouteException) {
            return (new self($request))->showError($e->getMessage() ?: 'URL ungültig', 404);
        } elseif ($e instanceof NotLoggedInException) {
            return (new AuthController($request))->loginForm();
        } elseif ($e instanceof NotFoundException) {
            return (new self($request))->showError($e->getMessage() ?: 'nicht gefunden', 404);
        } elseif ($e instanceof AccessDeniedException) {
            return (new self($request))->showError($e->getMessage() ?: 'fehlende Rechte', 403);
        } elseif ($e instanceof InvalidCsrfTokenException) {
            return (new self($request))->showError($e->getMessage() ?: 'Die Anfrage kann nicht wiederholt werden.', 400);
        } elseif ($e instanceof InvalidUserDataException) {
            return (new self($request))->showError($e->getMessage() ?: 'fehlerhafte Eingabedaten', 400);
        } else {
            error_log($e->getMessage());
            return (new self($request))->showError('Ein interner Fehler ist aufgetreten.', 500);
        }
    }
}
