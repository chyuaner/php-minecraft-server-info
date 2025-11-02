<?php

class ResponseFormatter implements ResponseFormatterInterface
{
    public function format(ServerRequestInterface $request, ResponseInterface $response, array|object|string $data): ResponseInterface
    {
        $accept = strtolower($request->getHeaderLine('Accept') ?? '');

        if (is_array($data) || is_object($data)) {
            $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $body = (string)$data;
        }

        if (strpos($accept, 'application/json') !== false) {
            $stream = \Slim\Psr7\Stream::create($body);
            return $response->withBody($stream)
                            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $html = "<pre>" . htmlspecialchars($body) . "</pre>";
        $stream = \Slim\Psr7\Stream::create($html);
        return $response->withBody($stream)
                        ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
