<?php
namespace App;

use Slim\Handlers\ErrorHandler;
use Slim\Error\Renderers\JsonErrorRenderer;
use Slim\Error\Renderers\HtmlErrorRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AppErrorHandler extends ErrorHandler
{
    protected function isJson(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        if (!empty($query['json'])) return true;

        if (!empty($query['type'])) {
            if ($query['type'] === 'json') return true;
            if ($query['type'] === 'html') return false;
        }

        $accept = strtolower($request->getHeaderLine('Accept') ?? '');
        return strpos($accept, 'application/json') !== false;
    }

    protected function determineStatusCode(): int
    {
        $exception = $this->exception;
        if ($exception instanceof \xPaw\MinecraftPingException) {
            return 502;
        }
        if ($exception instanceof \xPaw\MinecraftQueryException) {
            return 502;
        }

        // 沿用父類別邏輯
        return parent::determineStatusCode();
    }

    protected function respond(): ResponseInterface
    {
        $request = $this->request;
        $exception = $this->exception;
        $statusCode = $this->determineStatusCode($exception);
        $isJson = $this->isJson($request);

        // 建立 Response
        $response = $this->responseFactory->createResponse($statusCode);

        // 使用 Slim 官方 Renderer
        if ($isJson) {
            $renderer = new JsonErrorRenderer($this->displayErrorDetails);
            $contentType = 'application/json';
        } else {
            $renderer = new HtmlErrorRenderer($this->displayErrorDetails);
            $contentType = 'text/html; charset=UTF-8';
        }

        // 產生內容
        $body = $renderer($exception, $statusCode, $request);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', $contentType);
    }
}
