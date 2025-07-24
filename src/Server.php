<?php
namespace McModUtils;

use xPaw\MinecraftPing;
use xPaw\MinecraftPingException;

final class Server
{
    protected $id;
    protected $host;
    protected $port;
    protected $name;
    protected $qport;

    private function loadFromServerId($id) : bool {
        $serverId = $id;
        if (!empty($serverId) && array_key_exists($serverId, ($GLOBALS['config']['minecraft_servers']))) {
            $mc_server = $GLOBALS['config']['minecraft_servers'][$serverId];

            $this->id = $serverId;
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

    public function outputPing() : array {
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
        return $output;
    }
}
