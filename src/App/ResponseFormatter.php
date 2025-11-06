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

        // HTML fallback: 用遞迴的 ul/li 輸出陣列 / 物件
        if (is_object($data)) {
            $html = '<div class="object">' . htmlspecialchars(get_class($data)) . '</div>';
            $html .= $this->renderHtmlList((array)$data);
        } elseif (is_array($data)) {
            $html = $this->renderHtmlList($data);
        } else {
            $html = '<pre>' . htmlspecialchars((string)$data) . '</pre>';
        }

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * 將陣列 / 物件內容遞迴輸出為 ul/li
     */
    private function renderHtmlList($data): string
    {
        if (!is_array($data)) {
            return '<span>' . htmlspecialchars((string)$data) . '</span>';
        }

        $html = '<ul>';
        foreach ($data as $key => $value) {
            $k = htmlspecialchars((string)$key);
            if (is_array($value) || is_object($value)) {
                if (is_object($value)) {
                    $html .= '<li><strong>' . $k . ':</strong> <div class="object">' . htmlspecialchars(get_class($value)) . '</div>';
                    $html .= $this->renderHtmlList((array)$value);
                    $html .= '</li>';
                } else {
                    $html .= '<li><strong>' . $k . ':</strong>';
                    $html .= $this->renderHtmlList($value);
                    $html .= '</li>';
                }
            } else {
                if (is_bool($value)) {
                    $val = $value ? 'true' : 'false';
                }
                elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                    $url = (string)$value;
                    $val = '<a href="'.$url.'">'.$url.'</a>';
                }
                elseif ($value === null) {
                    $val = 'null';
                }
                else {
                    $val = htmlspecialchars((string)$value);
                }
                $html .= '<li><strong>' . $k . ':</strong> <span>' . $val . '</span></li>';
            }
        }
        $html .= '</ul>';

        return $html;
    }
}
