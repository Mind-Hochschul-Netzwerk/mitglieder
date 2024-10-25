<?php
declare(strict_types=1);
namespace App\Router;

use App\Controller\AuthController;
use App\Controller\Controller;
use App\Router\Exception\AccessDeniedException;
use App\Router\Exception\InvalidRouteException;
use App\Router\Exception\InvalidUserDataException;
use App\Router\Exception\NotFoundException;
use App\Router\Exception\NotLoggedInException;
use App\Service\CurrentUser;
use Exception;
use InvalidArgumentException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Router
 */
class Router {
    const ROUTES_CACHE_FILE = '/tmp/routes.cache.php';

    /**
     * known types for dependency injection
     * [[string $type, callable $retriever, ?string $identifier], ...]
     */
    private array $types = [];

    /**
     * known services for dependency injection
     * [$className => callable $retriever, ...]
     */
    private array $services = [];

    private Request $request;

    private RouteMap $routeMap;

    public function __construct(string $controllerDir)
    {
        $this->routeMap = new RouteMap($controllerDir);

        $this->addType('string', fn($v) => $v);
        $this->addType('int', 'intval');
        $this->addType('bool', 'boolval');
        $this->addType('float', 'floatval');

        $this->addService(ParameterBag::class, fn() => $this->request->getPayload());
        $this->addService(Request::class, fn() => $this->request);
    }

    /**
     * @param $matcher is an (optional) HTTP method and URL path like "GET /path/to/the/resource" or "POST /users/{id}" or "/users/{[0-9]+:id}"
     * @param $controller is the classname of a Controller subclass
     * @param $functionName is the function in the controller that will be called
     *                      the function takes the arguments in the same order they appear in the matcher
     */
    public function add(string $matcher, string $controller, string $functionName): void
    {
        $this->routeMap->add($matcher, $controller, $functionName);
    }

    public function addType(string $type, callable $retriever, ?string $identifierName = null): void {
        $this->types[] = [$type, $retriever, $identifierName];
    }

    public function addService(string $class, callable $retriever): void {
        $this->services[$class] = $retriever;
    }

    public function dispatch(Request $request): Response {
        $this->request = $request;

        if (!$this->request->hasSession()) {
            $this->request->setSession(new Session());
        }

        CurrentUser::setRequest($this->request);

        try {
            foreach ($this->routeMap->getRoutes() as $matcher => [$httpMethod, $pathPattern, $queryInfo, $controller, $functionName]) {
                if ($request->getMethod() !== $httpMethod || !preg_match($pathPattern, $request->getPathInfo(), $matches)) {
                    continue;
                }
                foreach ($queryInfo as $parameterName => $parameterPattern) {
                    if (!$request->query->has($parameterName) || !preg_match($parameterPattern, $request->query->getString($parameterName), $parameterMatches)) {
                        continue 2;
                    }
                    $matches += $parameterMatches;
                }

                $matches = $this->prepareMatches($matches);

                return $this->call($controller, $functionName, $matches);
            }
            throw new InvalidRouteException();
        } catch (InvalidRouteException $e) {
            return (new Controller($request))->showMessage($e->getMessage() ?: 'URL ungültig', 404);
        } catch (NotLoggedInException $e) {
            return (new AuthController($request))->loginForm();
        } catch (NotFoundException $e) {
            return (new Controller($request))->showMessage($e->getMessage() ?: 'nicht gefunden', 404);
        } catch (AccessDeniedException $e) {
            return (new Controller($request))->showMessage($e->getMessage() ?: 'fehlende Rechte', 403);
        } catch (InvalidUserDataException $e) {
            return (new Controller($request))->showMessage($e->getMessage() ?: 'fehlerhafte Eingabedaten', 400);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return (new Controller($request))->showMessage('Ein interner Fehler ist aufgetreten.', 500);
        }
    }

    private function prepareMatches(array $matches): array {
        $return = [];
        foreach ($matches as $name=>$match) {
            if (is_numeric($name)) {
                continue;
            }
            if (!str_contains($name, '__')) {
                $return[$name] = [null, $match];
            } else {
                [$attributeName, $identifierName] = explode('__', $name);
                $return[$attributeName] = [$identifierName, $match];
            }
        }
        return $return;
    }

    private function getModelRetriever(string $type, ?string $identifierName): callable {
        $best = null;

        foreach ($this->types as [$typeName, $retriever, $identifier]) {
            if ($typeName === $type && $identifier === $identifierName) {
                return $retriever;
            }
            if ($typeName === $type && $identifier === null) {
                $best = $retriever;
            }
        }
        if ($best) {
            return $best;
        }
        // TODO: default retriever ($type.'Repository'::getInstance()->{'findBy'.$identifierName})

        throw new InvalidArgumentException('no retriever found for model ' . $type . ($identifier ? (' identified by $' . $identifierName) : ''));
    }

    private function getService(string $className): object {
        $retriever = $this->services[$className] ?? null;
        if ($retriever) {
            return $retriever();
        }

        // default retriever: $className::getInstance
        if (method_exists($className, 'getInstance')) {
            $method = new ReflectionMethod($className, 'getInstance');
            if ($method->isStatic()) {
                return $method->invoke(null);
            }
        }

        throw new InvalidArgumentException('no retriever found for service ' . $className);
    }

    private function injectDependencies(ReflectionMethod $method, array $matches): array {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $type = (string)$parameter->getType();

            // inject parameters from path and query string
            if (isset($matches[$parameter->getName()])) {
                [$identifierName, $match] = $matches[$parameter->getName()];
                $retriever = $this->getModelRetriever($type, $identifierName);
                $arg = $retriever($match);
                if ($arg === null) {
                    throw new NotFoundException();
                }
                $args[] = $arg;
                continue;
            // inject services (or throw InvalidArgumentException)
            } else {
                $args[] = $this->getService($type);
            }
        }
        return $args;
    }

    private function call(string $class, string $functionName, array $matches): Response {
        $constructor = new ReflectionMethod($class, '__construct');
        $constructorArgs = $this->injectDependencies($constructor, $matches);
        $method = new ReflectionMethod($class, $functionName);
        $args = $this->injectDependencies($method, $matches);
        return $method->invokeArgs(new $class(...$constructorArgs), $args);
    }
}
