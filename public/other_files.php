<?php

use App\ResponseFormatter;
use McModUtils\Folder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group("/ofolder", function (RouteCollectorProxy $group) {

    // All
    $group->get('', function (Request $request, Response $response, array $args) {
        $isForce = $request->getQueryParams()['force'] ?? false;
        $directorys = array_keys($GLOBALS['config']['other_folders']);
        $fileInfos = [];
        foreach ($directorys as $directory) {
            $folder = new Folder($directory);
            $folder->fetchFilesRecursively(fetchMeta: true, fetchMd5: true, fetchSha1:  true, force: $isForce, enableCache: true);
            // $folder->fetchFilesRecursively(fetchMeta: true, fetchMd5: false, fetchSha1:  false, force: false, enableCache: false);
            $fileInfos += $folder->getFileInfos();
        }

        // print_r($fileInfos);exit;
        $formatter = new ResponseFormatter();
        return $formatter->format($request, $fileInfos);
    });


    $group->get('/zip', function (Request $request, Response $response, array $args) {
        $response->getBody()->write("Files root path. Please specify a file or folder to download.");
        return $response->withHeader('Content-Type', 'text/plain');
    });


    // 指定的Folder
    $group->get('/{folder}', function (Request $request, Response $response, array $args) {
        $isForce = $request->getQueryParams()['force'] ?? false;

        // 取得使用者傳入的 folder 並標準化（移除前後斜線）
        $raw = $args['folder'] ?? '';
        $requested = trim($raw, '/');

        // 如果開頭有 "files/"（或 "/files/"），移除它
        if (strpos($requested, 'files/') === 0) {
            $requested = substr($requested, strlen('files/'));
        }

        // 在 config 中尋找對應的資料夾路徑（other_folders 的 value 對應到請求）
        $foundPath = null;
        foreach ($GLOBALS['config']['other_folders'] as $diskPath => $dlUrlPath) {
            $norm = trim($dlUrlPath, '/');           // e.g. "files/config"
            if (strpos($norm, 'files/') === 0) {
                $norm = substr($norm, strlen('files/')); // e.g. "config"
            }
            if ($norm === $requested) {
                $foundPath = $diskPath;
                break;
            }
        }

        if ($foundPath === null) {
            // 找不到對應項，回傳 404
            throw new \Slim\Exception\HttpNotFoundException($request);
        }

        // foundPath 現在是不含 '/files/' 的對應磁碟路徑，後續照原本流程使用
        $folder = new Folder($foundPath);
        $folder->fetchFilesRecursively(fetchMeta: true, fetchMd5: true, fetchSha1:  true, force: $isForce, enableCache: true);
        // $folder->fetchFilesRecursively(fetchMeta: true, fetchMd5: false, fetchSha1:  false, force: false, enableCache: false);
        $fileInfos = $folder->getFileInfos();

        // $response->getBody()->write("Files root path. Please specify a file or folder to download.");
        // return $response->withHeader('Content-Type', 'text/plain');

        $formatter = new ResponseFormatter();
        return $formatter->format($request, $fileInfos);
    });


    $group->get('/{folder}/zip', function (Request $request, Response $response, array $args) {
        $response->getBody()->write("Files root path. Please specify a file or folder to download.");
        return $response->withHeader('Content-Type', 'text/plain');
    });


});


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
