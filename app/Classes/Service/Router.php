<?php
declare(strict_types=1);
namespace App\Service;

use App\Controller\AuthController;
use App\Controller\Controller;
use App\Controller\Exception\AccessDeniedException;
use App\Controller\Exception\InvalidRouteException;
use App\Controller\Exception\InvalidUserDataException;
use App\Controller\Exception\NotFoundException;
use App\Controller\Exception\NotLoggedInException;
use App\Service\Attribute\Route;
use Exception;
use InvalidArgumentException;
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
     * contains the routes
     * [matcher  => [HTTP method, path regex, query info, Controller class, function name]]
     */
    private array $routes = [];

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

    public function __construct(string $controllerDir)
    {
        $this->loadRoutesFromCache($controllerDir);

        $this->addType('string', fn($v) => $v);
        $this->addType('int', fn($v) => intval($v));
        $this->addType('bool', fn($v) => boolval($v));
        $this->addType('float', fn($v) => floatval($v));

        $this->addType(ParameterBag::class, fn() => $this->request->getPayload());
        $this->addType(Request::class, fn() => $this->request);
    }

    private function loadRoutesFromCache(string $controllerDir): void
    {
        $files = $this->collectControllerFiles($controllerDir);

        if (!is_file(self::ROUTES_CACHE_FILE) || max($files) >= filemtime(self::ROUTES_CACHE_FILE)) {
            $this->buildRoutesCache(array_keys($files));
        } else {
            $this->routes = include(self::ROUTES_CACHE_FILE);
        }
    }

    /**
     * @return array [filename => modified time]
     */
    private function collectControllerFiles(string $directory): array
    {
        $dir = dir($directory);
        $files = [];
        while (false !== ($file = $dir->read())) {
            if ($file[0] === '.') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $files = [...$files, ...$this->collectControllerFiles($path)];
            } else {
                $files[$path] = filemtime($path);
            }
        }
        $dir->close();
        return $files;
    }

    private function getClassFromFile(string $file): string
    {
        $namespace = '';
        $tokens = token_get_all(file_get_contents($file));

        foreach ($tokens as $i => $token) {
            if ($token[0] === T_NAMESPACE) {
                $namespace = $tokens[$i+2][1];
            } elseif ($token[0] === T_CLASS) {
                $class = $tokens[$i+2][1];
                return $namespace ? "$namespace\\$class" : $class;
            }
        }
    }

    private function buildRoutesCache(array $controllerFiles): void
    {
        $this->routes = [];
        $classes = array_map(fn($file) => $this->getClassFromFile($file), $controllerFiles);

        foreach ($classes as $classname) {
            $class = new \ReflectionClass($classname);
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $routes = $method->getAttributes(Route::class);
                foreach ($routes as $route) {
                    $matcher = $route->newInstance()->matcher;
                    $this->add($matcher, $classname, $method->name);
                }
            }
        }

        $this->sortRoutes();
        $this->cacheRoutes();
    }

    private function cacheRoutes(): void
    {
        $fp = fopen(self::ROUTES_CACHE_FILE, 'w');
        fwrite($fp, '<?php return ' . var_export(($this->routes), true) . ';');
        fclose($fp);
    }

    /**
     * @param $matcher is an (optional) HTTP method and URL path like "GET /path/to/the/resource" or "POST /users/{id}" or "/users/{[0-9]+:id}"
     * @param $controller is the classname of a Controller subclass
     * @param $functionName is the function in the controller that will be called
     *                      the function takes the arguments in the same order they appear in the matcher
     */
    public function add(string $matcher, string $controller, string $functionName): void
    {
        $httpMethod = 'GET';
        if (str_contains($matcher, ' ')) {
            [$httpMethod, $matcher] = explode(' ', $matcher, 2);
            $httpMethod = strtoupper($httpMethod);
        }
        [$pathPattern, $queryInfo] = $this->createPattern($matcher);
        $this->routes[$httpMethod . ' ' . $matcher] = [$httpMethod, $pathPattern, $queryInfo, $controller, $functionName];
    }

    private function createPattern(string $matcher) {
        $queryInfo = [];
        if (str_contains($matcher, '?')) {
            [$matcher, $queryMatcher] = explode('?', $matcher, 2);
            $queryInfo = $this->createQueryInfo($queryMatcher);
        }

        $matcher = $this->substituteNamedParametersInMatcher($matcher);
        $pathPattern = '(^' . preg_replace('=/+=', '/', '/' . $matcher . '/?') . '$)';

        return [$pathPattern, $queryInfo];
    }

    private function substituteNamedParametersInMatcher(string $matcher): string {
        $matcher = preg_replace('/\{([^:]+?)\}/', '(?P<$1>[^\/]*?)', $matcher);
        $matcher = preg_replace('/\{(.+?):(.+?)\}/', '(?P<$2>$1)', $matcher);
        $matcher = preg_replace('/\(\?P\<(.+?)=\>(.+?)\>/', '(?P<$2__$1>', $matcher);
        return $matcher;
    }

    /**
     * @param $queryMatcher is a query string like param1={name1}&param2={[0-9]+:name2}&param3=abc&param4={part1}-{partb}&param5
     * @return queryInfo where keys are the parameter names and the values are regular expressions with named groups (or null if no value is expected)
     */
    private function createQueryInfo(string $queryMatcher): array {
        $queryInfo = [];

        $args = explode('&', $queryMatcher);
        foreach ($args as $key_matcher) {
            if (!str_contains($key_matcher, '=')) {
                $queryInfo[$key_matcher] = null;
                continue;
            }
            [$key, $matcher] = explode('=', $key_matcher, 2);
            $matcher = $this->substituteNamedParametersInMatcher($matcher);
            $pattern = '(^' . $matcher . '$)';
            $queryInfo[$key] = $this->substituteNamedParametersInMatcher($pattern);
        }

        return $queryInfo;
    }

    private function sortRoutes(): void {
        // sort by controller name, query info length DESC, preserve pre-order
        uasort($this->routes, function (array $a, array $b) {
            return $a[3] <=> $b[3] ?: count($b[2]) <=> count($a[2]);
        });
    }

    public function addType(string $type, callable $retriever, ?string $identifierName = null): void {
        $this->types[] = [$type, $retriever, $identifierName];
    }

    public function addService(string $class, callable $retriever): void {
        $this->services[$class] = $retriever;
    }

    public function dispatch(Request $request): Response {
        $this->request = $request;

        try {
            foreach ($this->routes as $matcher => [$httpMethod, $pathPattern, $queryInfo, $controller, $functionName]) {
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
        $method = new ReflectionMethod($class, $functionName);
        $args = $this->injectDependencies($method, $matches);
        return $method->invokeArgs(new $class($this->request), $args);
    }
}
