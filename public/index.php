<?php

/**
 * @apiDefine McServers
 * @apiParam {String="forge1","auth2"} [server] 選填，伺服器名稱，例如 `forge1`。未填則使用預設伺服器。
 */

use App\AppErrorHandler;
use App\ResponseFormatter;
use Middlewares\TrailingSlash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../bootstrap.php';

/**
 * Instantiate App
 *
 * In order for the factory to work you need to ensure you have installed
 * a supported PSR-7 implementation of your choice e.g.: Slim PSR-7 and a supported
 * ServerRequest creator (included with Slim PSR-7)
 */
$app = AppFactory::create();

/**
  * The routing middleware should be added earlier than the ErrorMiddleware
  * Otherwise exceptions thrown from it will not be handled by the middleware
  */
$app->addRoutingMiddleware();

/**
 * Add Error Middleware
 *
 * @param bool                  $displayErrorDetails -> Should be set to false in production
 * @param bool                  $logErrors -> Parameter is passed to the default ErrorHandler
 * @param bool                  $logErrorDetails -> Display error details in error log
 * @param LoggerInterface|null  $logger -> Optional PSR-3 Logger
 *
 * Note: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */
$errorMiddleware = $app->addErrorMiddleware($GLOBALS['config']['debug'], true, true);
if ($GLOBALS['config']['debug']) {
    $callableResolver = $app->getCallableResolver();
    $responseFactory = $app->getResponseFactory();

    // 傳入實例，確保建構子正確
    $errorMiddleware->setDefaultErrorHandler(
        new AppErrorHandler($callableResolver, $responseFactory)
    );
}

$app->add(new TrailingSlash(trailingSlash: false)); // true adds the trailing slash (false removes it)

// ============================================================================

require __DIR__ . '/mods-v3.php';
require __DIR__ . '/server.php';

$app->get('/', function (Request $request, Response $response, $args) {
    $renderer = new PhpRenderer(__DIR__ . '/../src/templates');
    return $renderer->render($response, 'index.php');
});

$app->get('/favicon.ico', function (Request $request, Response $response, $args) {
    return $response->withStatus(204); // No Content
});

$app->run();
