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
        $parsed = General::parseChatComponent($desc);
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
        return General::parseChatComponent($component);
    }

    public function normalizeColorCodes(string $text) : string {
        return General::normalizeColorCodes($text);
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

    public function renderBanner(bool $isShowPlayer = false, float|int $ping = 0, $background = null, ?string $overrideSubtitle = null) : \GdImage {
        $hostString = $this->getPublicHostString();
        if ($overrideSubtitle !== null) {
            $subtitle = $overrideSubtitle;
        } elseif ($isShowPlayer) {
            $playersStr = implode(', ', $this->getPlayersName());
            if (empty($playersStr)) {
                $playersStr = 'no player';
            }
            $subtitle = 'Online:  ' . $playersStr;
        } else {
            $subtitle = $this->getDescription();
            if (empty($subtitle)) {
                $subtitle = $this->getName() ?? '';
            }
        }

        $onlinePlayersCount = $this->getOnlinePlayersCount();
        $maxPlayersCount = $this->getMaxPlayersCount();
        $favicon = $this->getFaviconImage();

        return General::server($hostString, $subtitle, $onlinePlayersCount, $maxPlayersCount, $favicon, $background, $ping);
    }

    public static function serverBanner(
        string $address,
        string $motd = "§cOffline Server",
        int|string $players = -1,
        int|string $max_players = -1,
        $favicon = null,
        $background = null,
        float|int $ping = 0
    ) : \GdImage {
        return General::server($address, $motd, $players, $max_players, $favicon, $background, $ping);
    }
}
