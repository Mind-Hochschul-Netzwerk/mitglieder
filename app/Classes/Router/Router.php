<?php
declare(strict_types=1);
namespace App\Router;

use App\Router\Exception\AccessDeniedException;
use App\Router\Exception\InvalidRouteException;
use App\Router\Exception\InvalidUserDataException;
use App\Router\Exception\NotFoundException;
use App\Router\Exception\NotLoggedInException;
use App\Router\Interface\CurrentUserInterface;
use Exception;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    private ?CurrentUserInterface $currentUser;

    private RouteMap $routeMap;
    private array $exceptionHandlers = [];

    public function __construct(string $controllerDir)
    {
        $this->routeMap = new RouteMap($controllerDir);

        $this->addType('string', fn($v) => $v);
        $this->addType('mixed', fn($v) => $v);
        $this->addType('', fn($v) => $v);
        $this->addType('int', 'intval');
        $this->addType('bool', 'boolval');
        $this->addType('float', 'floatval');

        $this->addService(ParameterBag::class, fn() => $this->request->getPayload());
        $this->addService(Request::class, fn() => $this->request);
    }

    public function addType(string $type, callable $retriever, ?string $identifierName = null): void
    {
        $this->types[] = [$type, $retriever, $identifierName];
    }

    public function addService(string $class, callable $retriever): void
    {
        $this->services[$class] = $retriever;
    }

    public function addExceptionHandler(string $exceptionClass, callable $handler): void
    {
        $this->exceptionHandlers[$exceptionClass] = $handler;
    }

    public function dispatch(Request $request, ?CurrentUserInterface $currentUser = null): Response {
        $this->request = $request;
        $this->currentUser = $currentUser;

        try {
            foreach ($this->routeMap->getRoutes() as $matcher => [$httpMethod, $pathPattern, $queryInfo, $controller, $functionName, $conditions]) {
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

                return $this->callConditionally($controller, $functionName, $matches, $conditions);
            }
            throw new InvalidRouteException();
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(Exception $e): Response {
        foreach ($this->exceptionHandlers as $exceptionClass => $handler) {
            if (is_a($e::class, $exceptionClass, true)) {
                // $handler = [$className, $methodName]
                if (is_array($handler)) {
                    return $this->call($handler[0], $handler[1], [], [$e]);
                // closure
                } else {
                    $function = new ReflectionFunction($handler);
                    $args = [$e, ...$this->injectDependencies($function, [], 1)];
                    return $function->invokeArgs($args);
                }
            }
        }
        return $this->defaultExceptionHandler($e);
    }

    private function defaultExceptionHandler(Exception $e): Response {
        $header = ['Content-Type' => 'text/plain; charset=utf-8'];
        if ($e instanceof InvalidRouteException) {
            return new Response($e->getMessage() ?: 'path not found', 404, $header);
        } elseif ($e instanceof NotLoggedInException) {
            return new Response($e->getMessage() ?: 'login required', 401, $header);
        } elseif ($e instanceof NotFoundException) {
            return new Response($e->getMessage() ?: 'resource not found', 404, $header);
        } elseif ($e instanceof AccessDeniedException) {
            return new Response($e->getMessage() ?: 'permission required', 403, $header);
        } elseif ($e instanceof InvalidUserDataException) {
            return new Response($e->getMessage() ?: 'submitted data is missing or invalid', 400, $header);
        } else {
            error_log($e->getMessage());
            $message = $e->getMessage() ?: 'internal error';
            if ($e->getCode()) {
                $message .= ' (code: ' . $e->getCode() . ')';
            }
            return new Response($message, 500, $header);
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

        // default retriever: $type::getRepository()::getInstance()->{'findBy'.$identifierName}
        try {
            $repository = null;
            if (!method_exists($type, 'getRepository')) {
                throw new Exception();
            }

            $repository = [$type, 'getRepository']()::getInstance();

            // findOneById, findOneByUsername etc.
            $tryMethods = [
                'findOneBy' . $identifierName, 'getOneBy' . $identifierName,
                'findBy' . $identifierName, 'getBy' . $identifierName,
                'findOne', 'getOne',
                'find', 'get',
            ];

            foreach ($tryMethods as $methodName) {
                $retriever = [$repository, $methodName];
                if (method_exists(...$retriever)) {
                    $type = (string)(new \ReflectionObject($repository))->getMethod($methodName)->getParameters()[0]->getType();
                    return ($type === 'int') ? (fn($id) => $retriever(intval($id))) : $retriever;
                }
            }

            // findOneBy('id', ...), getOneBy('username', ...) etc.
            foreach (['findOneBy', 'getOneBy', 'findBy', 'getBy'] as $methodName) {
                $retriever = [$repository, $methodName];
                if (method_exists(...$retriever)) {
                    return fn($value) => $retriever($identifier, $value);
                }
            }

            throw new Exception();
        } catch (Exception $e) {
            throw new InvalidArgumentException('no retriever found for model ' . $type . ($identifier ? (' identified by $' . $identifierName) : ''));
        }
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

    private function injectDependencies(ReflectionMethod|ReflectionFunction $method, array $matches, int $skipParameters = 0): array {
        $args = [];
        foreach ($method->getParameters() as $i=>$parameter) {
            if ($i < $skipParameters) {
                continue;
            }

            $type = (string)$parameter->getType();

            // inject parameters from path and query string
            if (isset($matches[$parameter->getName()])) {
                [$identifierName, $match] = $matches[$parameter->getName()];
                $retriever = $this->getModelRetriever($type, $identifierName);
                $arg = $retriever($match);
                if ($arg === null || $arg === []) {
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

    /**
     * @throws AccessDeniedException none of the conditions is met
     * @throws NotLoggedInException if user is not even logged in (should be thrown in CurrentUser)
     */
    private function checkConditions(array $conditions, array $args): void
    {
        if (!$conditions) {
            return;
        }

        foreach ($conditions as $propertyName => $valueTemplate) {
            $method = new ReflectionMethod($this->currentUser, 'has' . $propertyName);
            $type = (string)$method->getParameters()[0]->getType();

            // TODO: template string, combinations (see Route attribute constructor)
            $value = $valueTemplate;

            if ($type === 'int') {
                $value = intval($value);
            }

            if ($method->invoke($this->currentUser, $value)) {
                return;
            }
        }

        // none of the conditions was met
        throw new AccessDeniedException();
    }

    private function callConditionally(string $class, string $functionName, array $matches, array $conditions): Response
    {
        $constructor = new ReflectionMethod($class, '__construct');
        $constructorArgs = $this->injectDependencies($constructor, $matches);
        $method = new ReflectionMethod($class, $functionName);
        $args = $this->injectDependencies($method, $matches);
        $this->checkConditions($conditions, $args);
        return $method->invokeArgs(new $class(...$constructorArgs), $args);
    }

    private function call(string $class, string $functionName, array $matches, array $arguments = []): Response
    {
        $constructor = new ReflectionMethod($class, '__construct');
        $constructorArgs = $this->injectDependencies($constructor, $matches);
        $method = new ReflectionMethod($class, $functionName);
        $args = [...$arguments, ...$this->injectDependencies($method, $matches, skipParameters: count($arguments))];
        return $method->invokeArgs(new $class(...$constructorArgs), $args);
    }
}
