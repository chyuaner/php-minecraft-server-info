<?php
return [
    // 請依據你的環境與需求修改
    'mods_path' => '/var/www/html/mods',
    'base_url' => 'http://localhost:8000',

    // 主要伺服器進入點（若有用到Velocity做為群組伺服器(代理伺服器)，這部份填代理伺服器主要進入點）
    'minecraft_host' => '127.0.0.1',
    'minecraft_port' => '25565',
    'minecraft_qport' => null,

    // 有多個伺服器
    'minecraft_servers' => [
        'youer1' => [ // 對應到ID
            'name'  => 'youer1', // 之後串前端網頁時，顯式給使用者看的
            'host'  => '127.0.0.1',
            'port'  => 24565,
            'qport' => 24565, // 若有啟用 enable-query=true query.port=24565
        ],
        'youer2' => [
            'name'  => 'youer2',
            'host'  => '127.0.0.1',
            'port'  => 23565,
            'qport' => null,
        ],
    ],
];
