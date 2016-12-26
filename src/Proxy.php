<?php
namespace IVIR3aM\DownloadManager;

class Proxy
{
    protected $ip;
    protected $port;
    protected $type;
    const HTTP = 0;
    const SOCKS4 = 4;
    const SOCKS5 = 5;
    const SOCKS4A = 6;
    const SOCKS5_HOSTNAME = 7;

    public function __construct($ip = '0.0.0.0', $port = 0, $type = self::HTTP)
    {
        $this->setIp($ip);
        $this->setPort($port);
        $this->setType($type);
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip)
    {
        $this->ip = (string)$ip;
        return $this;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setPort($port)
    {
        $this->port = intval($port);
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        switch ($type) {
            case self::SOCKS4:
                $type = 'SOCKS4';
                break;
            case self::SOCKS5:
                $type = 'SOCKS5';
                break;
            case self::SOCKS4A:
                $type = 'SOCKS4A';
                break;
            case self::SOCKS5_HOSTNAME:
                $type = 'SOCKS5_HOSTNAME';
                break;
            default:
            case self::HTTP:
                $type = 'HTTP';
                break;
        }
        $name = 'CURLPROXY_' . $type;
        if (!defined($name)) {
            $name = self::class . '::' . $type;
        }
        $this->type = constant($name);
        return $this;
    }

    public static function isValid($ip, $port)
    {
        return $port >= 0 && $port <= 65535 && filter_var($ip, FILTER_VALIDATE_IP);
    }

    public function isUsable()
    {
        return intval($this->getPort()) > 0 && intval($this->getIp()) > 0 && static::isValid($this->getIp(),
            $this->getPort());
    }
}