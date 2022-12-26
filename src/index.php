<?php

declare(strict_types=1);

use HttpSoft\Message\ResponseFactory;
use HttpSoft\ServerRequest\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\Middleware\Dispatcher\WrapperFactory;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Group;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$responseFactory = new ResponseFactory();

// Define routes
$routes = [
    Route::get('/')
        ->action(
            static function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($responseFactory) {
                $response = $responseFactory->createResponse();
                $response
                    ->getBody()
                    ->write('You are at homepage.');
                return $response;
            }
        ),
    Route::get('/test/{id:\w+}')
        ->action(static function (CurrentRoute $currentRoute, RequestHandlerInterface $next) use ($responseFactory) {
            $id = $currentRoute->getArgument('id');

            $response = $responseFactory->createResponse();
            $response
                ->getBody()
                ->write('You are at test with argument ' . $id);
            return $response;
        })
];

// Add routes defined to route collector
$collector = new RouteCollector();
$collector->addGroup(Group::create()->routes(...$routes));

// Initialize URL matcher
$urlMatcher = new UrlMatcher(new RouteCollection($collector));

$currentRouter = new CurrentRoute();
$container = new Container(ContainerConfig::create()->withDefinitions([CurrentRoute::class => $currentRouter]));
$middlewareFactory = new MiddlewareFactory($container, new WrapperFactory($container));

$router = new Router(
    $urlMatcher,
    new ResponseFactory(),
    $middlewareFactory,
    $currentRouter,
);

// Do the match against $request which is PSR-7 ServerRequestInterface.
$request = ServerRequestCreator::create();

$notFoundHandler = new class() implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(404);
        $response->getBody()->write('Page not found.');
        return $response;
    }
};

$response = $router->process($request, $notFoundHandler);

echo $response->getBody();