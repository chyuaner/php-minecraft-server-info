<?php
namespace McModUtils;

use xPaw\MinecraftPing;
use xPaw\MinecraftPingException;

final class Server
{
    protected $id;
    protected $publicHostString;
    protected $host;
    protected $port;
    protected $name;
    protected $qport;

    protected $pingData;

    public static function isExistServerId($id) : bool {
        $serverId = $id;
        return (!empty($serverId) && array_key_exists($serverId, ($GLOBALS['config']['minecraft_servers'])));
    }

    private function loadFromServerId($id) : bool {
        $serverId = $id;
        if (self::isExistServerId($serverId)) {
            $mc_server = $GLOBALS['config']['minecraft_servers'][$serverId];

            $this->id = $serverId;
            $this->publicHostString = $mc_server['public_hoststring'];
            $this->host = $mc_server['host'];
            $this->port = $mc_server['port'];
            $this->name = $mc_server['name'];
            $this->qport = $mc_server['qport'];

            return true;
        } else {
            return false;
        }
    }

    private function loadFromeDefaultServer() : bool {
        $this->publicHostString = $GLOBALS['config']['minecraft_public_hoststring'];
        $this->host = $GLOBALS['config']['minecraft_host'];
        $this->port = $GLOBALS['config']['minecraft_port'];

        return !empty($this->host) && !empty($this->port);
    }

    public function __construct($host=null, $port=null, $id=null, $name='', $qport=null) {
        if (!empty($id)) {
            $this->loadFromServerId($id);
            $this->port = $port;
            $this->host = $host;
            if (!empty($name)) { $this->name = $name; }
            if (!empty($qport)) { $this->qport = $qport; }
        }
        elseif (!empty($port)) {
            $this->port = $port;
            $this->host = $host;
        }
        elseif (!empty($host)) {
            $this->loadFromServerId($host);
        }
        else {
            $this->loadFromeDefaultServer();
        }

    }

    public function fetchPing() : array {
        $host = $this->host;
        $port = $this->port;

        try
        {
            $Query = new MinecraftPing( $host, $port );
            $output = $Query->Query();
        }
        catch( MinecraftPingException $e )
        {
            $output = ['error' => $e->getMessage()];
            throw $e;

        }
        finally
        {
            if( $Query )
            {
                $Query->Close();
            }
        }
        $this->pingData = $output;
        return $output;
    }

    public function outputPing() : array {
        if (empty($this->pingData)) {
            return $this->fetchPing();
        }
        return $this->pingData;
    }

    public function getHost() : string {
        return $this->host;
    }

    public function getHostString() : string {
        $output = $this->host;

        if ($this->port != 25565) {
            $output .= ':'.$this->port;
        }
        return $output;
    }

    public function getPublicHostString() : string {
        if (!empty($this->publicHostString)) {
            return $this->publicHostString;
        } else {
            return $this->getHostString();
        }
    }

    public function getPort() : int {
        return $this->port;
    }

    public function getName() : string|null {
        return $this->name;
    }

    public function getMaxPlayersCount() : int {
        $fetchedOutput = $this->outputPing();

        if (!empty($fetchedOutput['players'])) {
            if (!empty($fetchedOutput['players']['max'])) {
                return (int)$fetchedOutput['players']['max'];
            }
        }
        return 0;
    }

    public function getOnlinePlayersCount() : int {
        $fetchedOutput = $this->outputPing();

        if (!empty($fetchedOutput['players'])) {
            if (!empty($fetchedOutput['players']['online'])) {
                return (int)$fetchedOutput['players']['online'];
            }
        }
        return 0;
    }

    public function getPlayers() : array {
        $fetchedOutput = $this->outputPing();

        if (!empty($fetchedOutput['players'])) {
            if (!empty($fetchedOutput['players']['sample'])) {
                $playerInfos = $fetchedOutput['players']['sample'];
                return $playerInfos;
            }
        }
        return [];
    }

    public function getPlayersName() : array {
        $playerInfos = $this->getPlayers();
        $playerNames = array_map(function($player) {
            return $player['name'];
        }, $playerInfos);

        return $playerNames;
    }

    public function getDescription() : string {
        $fetchedOutput = $this->outputPing();
        if (empty($fetchedOutput['description'])) {
            return $this->name ?? '';
        }

        $desc = $fetchedOutput['description'];
        $parsed = $this->parseChatComponent($desc);
        if ($parsed === '') {
            return $this->name ?? '';
        }

        if (!str_starts_with($parsed, '§')) {
            $parsed = '§f' . $parsed;
        }

        return $parsed;
    }

    public function getDescriptionClean() : string {
        $desc = $this->getDescription();
        return preg_replace('/§[0-9a-fk-or]/i', '', $desc);
    }

    public function parseChatComponent(mixed $component) : string {
        if (is_string($component)) {
            return $this->normalizeColorCodes($component);
        }

        if (!is_array($component)) {
            return '';
        }

        if (array_is_list($component)) {
            $result = '';
            foreach ($component as $item) {
                $result .= $this->parseChatComponent($item);
            }
            return $result;
        }

        $result = '';

        if (!empty($component['color'])) {
            $color = strtolower($component['color']);
            $colorMap = [
                'black' => '§0',
                'dark_blue' => '§1',
                'dark_green' => '§2',
                'dark_aqua' => '§3',
                'dark_red' => '§4',
                'dark_purple' => '§5',
                'gold' => '§6',
                'gray' => '§7',
                'dark_gray' => '§8',
                'blue' => '§9',
                'green' => '§a',
                'aqua' => '§b',
                'red' => '§c',
                'light_purple' => '§d',
                'yellow' => '§e',
                'white' => '§f',
                'reset' => '§f',
            ];

            if (isset($colorMap[$color])) {
                $result .= $colorMap[$color];
            } elseif (str_starts_with($color, '#')) {
                $result .= $this->hexToMinecraftColor($color);
            }
        }

        $styleMap = [
            'bold' => '§l',
            'italic' => '§o',
            'underlined' => '§n',
            'strikethrough' => '§m',
            'obfuscated' => '§k',
        ];
        foreach ($styleMap as $style => $code) {
            if (!empty($component[$style])) {
                $result .= $code;
            }
        }

        if (isset($component['text']) && is_string($component['text'])) {
            $result .= $this->normalizeColorCodes($component['text']);
        } elseif (isset($component['translate']) && is_string($component['translate'])) {
            $result .= $component['translate'];
        }

        if (!empty($component['extra']) && is_array($component['extra'])) {
            foreach ($component['extra'] as $extra) {
                $result .= $this->parseChatComponent($extra);
            }
        }

        return $result;
    }

    public function normalizeColorCodes(string $text) : string {
        $text = preg_replace_callback('/§x(?:§[0-9a-fA-F]){6}/i', function($matches) {
            $hex = str_ireplace(['§x', '§'], '', $matches[0]);
            return $this->hexToMinecraftColor($hex);
        }, $text);

        $text = preg_replace_callback('/§([0-9a-fk-orA-FK-OR])/', function($matches) {
            $code = strtolower($matches[1]);
            if ($code === 'r') {
                return '§f';
            }
            return '§' . $code;
        }, $text);

        return $text;
    }

    private function hexToMinecraftColor(string $hex) : string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '§f';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $palette = [
            '0' => [0, 0, 0],
            '1' => [0, 0, 170],
            '2' => [0, 170, 0],
            '3' => [0, 170, 170],
            '4' => [170, 0, 0],
            '5' => [170, 0, 170],
            '6' => [255, 170, 0],
            '7' => [170, 170, 170],
            '8' => [85, 85, 85],
            '9' => [85, 85, 255],
            'a' => [85, 255, 85],
            'b' => [85, 255, 255],
            'c' => [255, 85, 85],
            'd' => [255, 85, 255],
            'e' => [255, 255, 85],
            'f' => [255, 255, 255],
        ];

        $minDist = PHP_INT_MAX;
        $closest = 'f';
        foreach ($palette as $code => $rgb) {
            $dist = ($r - $rgb[0]) ** 2 + ($g - $rgb[1]) ** 2 + ($b - $rgb[2]) ** 2;
            if ($dist < $minDist) {
                $minDist = $dist;
                $closest = $code;
            }
        }

        return '§' . $closest;
    }

    public function getFavicon() : ?string {
        $fetchedOutput = $this->outputPing();
        if (empty($fetchedOutput['favicon']) || !is_string($fetchedOutput['favicon'])) {
            return null;
        }
        return $fetchedOutput['favicon'];
    }

    public function getFaviconData() : ?string {
        $favicon = $this->getFavicon();
        if (empty($favicon)) {
            return null;
        }

        $favicon = str_replace(["\r", "\n", ' '], '', $favicon);

        if (preg_match('#^data:image/\w+;base64,(.+)$#i', $favicon, $matches)) {
            $base64 = $matches[1];
        } else {
            $base64 = $favicon;
        }

        $base64 = str_replace(' ', '+', $base64);
        $binary = base64_decode($base64, true);
        return $binary !== false ? $binary : null;
    }

    public function getFaviconImage() : ?\GdImage {
        $binary = $this->getFaviconData();
        if ($binary === null) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width !== 64 || $height !== 64) {
            $resized = imagecreatetruecolor(64, 64);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, 64, 64, $width, $height);
            return $resized;
        }

        return $image;
    }
}
