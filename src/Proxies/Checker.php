<?php
namespace IVIR3aM\DownloadManager\Proxies;

use IVIR3aM\DownloadManager\TimeoutHolderTrait;
/**
 * Class Checker
 * @package IVIR3aM\DownloadManager\Proxies
 * @todo must implement tests for all kind of proxies
 */
class Checker
{
    use TimeoutHolderTrait;

    /**
     * @var int the minimum download speed of proxy in Bytes per Seconds
     */
    private $minSpeed;

    /**
     * @var string the url for testing the proxy
     */
    private $url;

    /**
     * @var array list of keywords that must appeared one of them in response of url
     */
    private $keywords;

    public function __construct(
        $connectTimeout = 5,
        $fetchTimeout = 10,
        $minSpeed = 100000,
        $url = 'https://www.google.com/?hl=en',
        $keywords = ['Google Search', 'Google automatically']
    ) {
        $this->setConnectTimeout($connectTimeout);
        $this->setFetchTimeout($fetchTimeout);
        $this->setMinSpeed($minSpeed);
        $this->setUrl($url);
        $this->setKeywords($keywords);
    }

    public function getMinSpeed()
    {
        return $this->minSpeed;
    }

    /**
     * @param int $speed
     * @return $this
     */
    public function setMinSpeed($speed)
    {
        $this->minSpeed = intval($speed);
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = (string) $url;
        return $this;
    }

    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param array $keywords
     * @return $this
     */
    public function setKeywords($keywords)
    {
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $this->keywords = [];
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (strlen($keyword)) {
                $this->keywords[] = $keyword;
            }
        }
        return $this;
    }

    /**
     * @param Proxies $proxy
     * @return bool is the proxy responding correctly
     */
    public function check(Proxies $proxy)
    {
        if (!$proxy->isUsable()) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->getFetchTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->getConnectTimeout());
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_PROXY, $proxy->getIp());
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->getPort());
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy->getTypeCurl());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        $content = curl_exec($ch);
        $head = curl_getinfo($ch);
        curl_close($ch);
        foreach($this->getKeywords() as $keyword) {
            if (stripos($content, $keyword) !== false) {
                echo "- {$proxy->getIp()}:{$proxy->getPort()} => speed is: {$head['speed_download']}\n";
                return !isset($head['speed_download']) || ($head['speed_download'] >= $this->getMinSpeed());
            }
        }
        return false;
    }

    /**
     * @param string $ip
     * @param int $port
     * @return bool is the port opened
     */
    public function portIsOpened($ip, $port)
    {
        if (!function_exists('fsockopen')) {
            throw new Exception('function fsockopen() is not exists', 1);
        }
        $fp = @fsockopen($ip, $port, $errno, $errstr, $this->getConnectTimeout());
        if (is_resource($fp)) {
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * @param string $ip
     * @param int $port
     * @return bool|int the number of
     * @todo must implement a property to define what proxy types must tested
     */
    public function checkType($ip, $port)
    {
        if (!$this->portIsOpened($ip, $port)) {
            return false;
        }
        if ($this->checkHttp($ip, $port)) {
            return Proxies::HTTP;
        }
        if ($this->checkSocks5($ip, $port)) {
            return Proxies::SOCKS5;
        }
        return false;
    }

    /**
     * @param string $ip
     * @param int $port
     * @return bool is the proxy responding correctly
     */
    public function checkHttp($ip, $port)
    {
        $proxy = new Proxies($ip, $port, Proxies::HTTP);
        return $this->check($proxy);
    }

    /**
     * @param string $ip
     * @param int $port
     * @return bool is the proxy responding correctly
     */
    public function checkSocks5($ip, $port)
    {
        $proxy = new Proxies($ip, $port, Proxies::SOCKS5);
        return $this->check($proxy);
    }
}