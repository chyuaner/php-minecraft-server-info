<?php
// 要同步的

// http://localhost:8000/mods (old)
// http://localhost:8000/mods/:file (old)
// http://localhost:8000/mods/:file/download
// http://localhost:8000/mods/zip
// [mods = common-mods]

// http://localhost:8000/client-mods
// http://localhost:8000/client-mods/:file
// http://localhost:8000/client-mods/:file/download
// http://localhost:8000/client-mods/zip

// ---
// 參考用的

// http://localhost:8000/server-mods
// http://localhost:8000/server-mods/:file
// http://localhost:8000/server-mods/:file/download
// http://localhost:8000/server-mods/zip

// http://localhost:8000/all-mods
// http://localhost:8000/all-mods/:file
// http://localhost:8000/all-mods/:file/download
// http://localhost:8000/all-mods/zip

use App\ResponseFormatter;
use McModUtils\Mod;
use McModUtils\Mods;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$modTypes = [/*'mods',*/ 'client-mods', 'server-mods', 'all-mods'];

foreach ($modTypes as $modType) {
    $app->group("/$modType", function (RouteCollectorProxy $group) {
        $group->get('/zip', function (Request $request, Response $response, array $args) {
            $response->getBody()->write("/zip");
            return $response;
        });

        $group->get('', function (Request $request, Response $response, array $args) {
            $response->getBody()->write("/");
            return $response;
        });

        $group->get('/{filename}', function (Request $request, Response $response, array $args) {
            $response->getBody()->write("/{filename}");
            return $response;
        });

        $group->get('/{filename}/download', function (Request $request, Response $response, array $args) {
            $response->getBody()->write("/{filename}/download");
            return $response;
        });

    });
}


