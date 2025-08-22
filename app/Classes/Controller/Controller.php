<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use Hengeb\Router\Exception\AccessDeniedException;
use Hengeb\Router\Exception\InvalidCsrfTokenException;
use Hengeb\Router\Exception\InvalidRouteException;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Router\Exception\NotFoundException;
use Hengeb\Router\Exception\NotLoggedInException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Controller {
    protected array $templateVariables = [];

    public function __construct(
        protected Request $request,
        protected \Latte\Engine $latte,
    )
    {
    }

    protected function setTemplateVariable(string $key, mixed $value): void {
        $this->templateVariables[$key] = $value;
    }

    protected function redirect(string $uri): RedirectResponse {
        return new RedirectResponse($uri);
    }

    protected function renderToString(string $templateName, array $data = []): string {
        $er = error_reporting();
        // surpress 'undefined variable' warnings
        error_reporting($er & ~E_WARNING);
        $res = $this->latte->renderToString($templateName . '.latte', [
            ...$this->templateVariables,
            ...$data
        ]);
        error_reporting($er);

        $error = error_get_last();
        if ($error &&
            // errors to ignore:
            !str_starts_with($error['message'], 'Undefined variable') &&
            !str_starts_with($error['message'], 'Undefined array key') &&
            !str_starts_with($error['message'], 'Undefined property') &&
            !str_starts_with($error['message'], 'Attempt to read property') &&
            !str_starts_with($error['message'], 'Trying to access array offset on null')
        ) {
            throw new \RuntimeException("Error in template $templateName (file: {$error['file']}, line {$error['line']}): {$error['message']}");
        }
        return $res;
    }

    protected function render(string $templateName, array $data = []): Response {
        return new Response($this->renderToString($templateName, $data));
    }

    public function showError(string $message, int $responseCode = 200): Response {
        $response = $this->render('errorpage', ['text' => $message]);
        $response->setStatusCode($responseCode);
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
            $rqms = explode(' ', $rqm);

            if (!$payload->has($key) && in_array('required', $rqms, true)) {
                throw new InvalidUserDataException('Eingabedaten unvollständig: ' . $key);
            }

            $value = null;
            if (in_array('string', $rqms)) {
                $value = $payload->getString($key);
                if (!in_array('untrimmed', $rqms)) {
                    $value = trim($value);
                }
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

    public static function handleException(\Exception $e, Request $request, CurrentUser $user, UserRepository $userRepository, EmailService $emailService, \Latte\Engine $latte): Response {
        $requireLogin = $e instanceof AccessDeniedException || $e instanceof NotFoundException;

        if ($e instanceof InvalidRouteException) {
            return (new self($request, $latte))->showError($e->getMessage() ?: 'URL ungültig', 404);
        } elseif ($e instanceof NotLoggedInException || $requireLogin && !$user->isLoggedIn()) {
            return (new AuthController($request, $latte, $user, $userRepository, $emailService))->loginForm();
        } elseif ($e instanceof NotFoundException) {
            return (new self($request, $latte))->showError($e->getMessage() ?: 'nicht gefunden', 404);
        } elseif ($e instanceof AccessDeniedException) {
            return (new self($request, $latte))->showError($e->getMessage() ?: 'fehlende Rechte', 403);
        } elseif ($e instanceof InvalidCsrfTokenException) {
            return (new self($request, $latte))->showError($e->getMessage() ?: 'Die Anfrage kann nicht wiederholt werden.', 400);
        } elseif ($e instanceof InvalidUserDataException) {
            return (new self($request, $latte))->showError($e->getMessage() ?: 'fehlerhafte Eingabedaten', 400);
        } else {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            return (new self($request, $latte))->showError('Ein interner Fehler ist aufgetreten.', 500);
        }
    }
}
