<?php

use App\ResponseFormatter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$sendDownload = function (Request $request, string $modFilePath) : Response {

    // 若指定的是資料夾，輸出檔案清單（JSON）
    if (is_dir($modFilePath)) {
        $list = [];
        $it = new \DirectoryIterator($modFilePath);
        foreach ($it as $item) {
            if ($item->isDot()) continue;
            $list[] = [
                'name'   => $item->getFilename(),
                'is_dir' => $item->isDir(),
                'size'   => $item->isFile() ? $item->getSize() : null,
                'mtime'  => $item->getMTime(),
            ];
        }
        $formatter = new ResponseFormatter();
        return $formatter->format($request, $list);
    }

    // 若檔案不存在或不是檔案，回傳 404
    if (!file_exists($modFilePath) || !is_file($modFilePath)) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    // 偵測 MIME type（finfo -> mime_content_type -> fallback）
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $modFilePath);
            if ($detected !== false) $mime = $detected;
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $detected = @mime_content_type($modFilePath);
        if ($detected !== false) $mime = $detected;
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"');
    header('Content-Length: ' . filesize($modFilePath));
    header('X-Served-By: PHP');
    readfile($modFilePath);
    exit;
};


$registeredDlPaths = [];
foreach ($GLOBALS['config']['other_folders'] as $folderPath => $dlUrlPath) {
    // 若已註冊過相同的 dl_urlpath 就跳過（若要遇到第一個就完全停止註冊，改成 break）
    if (in_array($dlUrlPath, $registeredDlPaths, true)) {
        continue;
    }
    $registeredDlPaths[] = $dlUrlPath;

    $app->get($dlUrlPath.'{filename:.*}', function (Request $request, Response $response, array $args) use ($folderPath, $sendDownload) {

        $modFileName = $args['filename'];
        $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($folderPath, '/'), $modFileName]);

        $response = $sendDownload($request, $modFilePath);
        return $response;
    });
}
