<?php

namespace App\Slim;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AutoFormatMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        $body = (string)$response->getBody();
        $accept = strtolower($request->getHeaderLine('Accept') ?? '');

        if (strpos($accept, 'application/json') !== false) {
            if (!self::isJson($body)) {
                $body = json_encode(['data' => $body], JSON_UNESCAPED_UNICODE);
            }
            $response->getBody()->rewind();
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        // 預設 HTML
        $html = "<pre>" . htmlspecialchars($body) . "</pre>";
        $response->getBody()->rewind();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private static function isJson(string $data): bool
    {
        json_decode($data);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
