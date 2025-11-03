<?php

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ResponseFormatter
{
    protected ResponseFactory $factory;

    public function __construct()
    {
        $this->factory = new ResponseFactory();
    }

    /**
     * 判斷是否要以 JSON 模式輸出
     */
    public function isJson(ServerRequestInterface $request): bool
    {
        // URL 參數強制 JSON
        $query = $request->getQueryParams();
        if (!empty($query['json'])) {
            return true;
        }

        if (!empty($query['type'])) {
            if ($query['type'] == 'json') {
                return true;
            }
            if ($query['type'] == 'html') {
                return false;
            }
        }

        // Accept header 判斷
        $accept = strtolower($request->getHeaderLine('Accept') ?? '');
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * 自動輸出資料
     */
    public function format(ServerRequestInterface $request, $data, int $status = 200): ResponseInterface
    {
        $response = $this->factory->createResponse($status);

        if ($this->isJson($request)) {
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        // HTML fallback
        if (is_array($data) || is_object($data)) {
            $html = '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';
        } else {
            $html = htmlspecialchars((string)$data);
        }

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
