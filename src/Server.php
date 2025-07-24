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
}
