<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Exception\AccessDeniedException;
use App\Controller\Exception\InvalidUserDataException;
use App\Controller\Exception\NotLoggedInException;
use App\Service\AuthService;
use App\Service\Tpl;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Controller {
    protected Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    protected function requireLogin(): void {
        if (!AuthService::istEingeloggt()) {
            throw new NotLoggedInException();
        }
    }

    protected function requireRole(string $role): void {
        $this->requireLogin();
        if (!AuthService::hatRecht($role)) {
            throw new AccessDeniedException('Fehlendes Recht: ' . $role);
        }
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

    public function showMessage(string $message, int $responseCode = 200): Response {
        $response = $this->render('Layout/layout', [
            '@@contents' => $message,
        ]);
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
}
