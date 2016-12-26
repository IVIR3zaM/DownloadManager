<?php
namespace IVIR3aM\DownloadManager;

class HttpClient
{
    /**
     * @var Files
     */
    private $file;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var string
     */
    private $cookieFile;

    /**
     * @var int
     */
    private $redirects;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var resource
     */
    private $ch;

    /**
     * @var int
     */
    private $state;

    const BOTH = 0;
    const ONLY_HEAD = 1;
    const ONLY_BODY = 2;

    public function __construct(Files $file, $cookieFilePath = '', $timeout = 60, $redirects = 5)
    {
        $this->setCookieFile($cookieFilePath);
        $this->setTimeout($timeout);
        $this->setRedirects($redirects);
        $this->setUserAgent(StaticUserAgents::getRandomUserAgent());
        $this->setFile($file);
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile(Files $file)
    {
        $this->file = $file;
        return $this;
    }

    public function getCookieFile()
    {
        return $this->cookieFile;
    }

    public function setCookieFile($cookieFilePath)
    {
        $this->cookieFile = (string)$cookieFilePath;
        return $this;
    }

    public function getProxy()
    {
        return $this->getFile()->getProxy();
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setTimeout($timeout)
    {
        $timeout = intval($timeout);
        if ($timeout < 1) {
            $timeout = 1;
        }
        $this->timeout = $timeout;
        return $this;
    }

    public function getRedirects()
    {
        return $this->timeout;
    }

    public function setRedirects($redirects)
    {
        $this->redirects = intval($redirects);
        return $this;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setUserAgent($agent)
    {
        if (is_string($agent)) {
            $this->userAgent = $agent;
        }
        return $this;
    }

    public function getDownloadStepLength()
    {
        if (!$this->getFile()->getSize()) {
            $this->getInfo();
        }
        return $this->getFile()->getDownloadStepLength();
    }

    public function getInfo($header = array())
    {
        return $this->callCurl(self::ONLY_HEAD, $header);
    }

    public function download($position = 0, $length = 0, $header = array())
    {
        $position = intval($position);
        $length = intval($length);
        if ($position < 0) {
            throw new \Exception('position can not be less than zero');
        }
        if ($length < 0) {
            throw new \Exception('length can not be less than zero');
        }
        if ($length > 0) {
            $to = $position + $length - 1;
            $header['Range'] = "bytes={$position}-{$to}";
        }
        $data = $this->callCurl(self::BOTH, $header);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['head']['speed_download']) && $data['head']['speed_download'] > 0) {
            $this->getFile()->setSpeed($data['head']['speed_download']);
        }
        return $data['content'];
    }

    public function initCurl($state = self::BOTH, $header = [])
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $this->getFile()->getLink());
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->getUserAgent());
        $this->state = $state;
        switch ($state) {
            case self::ONLY_HEAD:
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                break;
            default:
            case self::ONLY_BODY:
            case self::BOTH:
                if ($this->getFile()->getMaxSpeed() > 0) {
                    curl_setopt($this->ch, CURLOPT_MAX_RECV_SPEED_LARGE, $this->getFile()->getMaxSpeed());
                }
                break;
        }
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->getCookieFile());
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->getTimeout());
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->getTimeout());
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->prepareHeaders($header));
        if ($this->getRedirects() > 0) {
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->ch, CURLOPT_MAXREDIRS, $this->getRedirects());
        } else {
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        }
        if ($this->getProxy()->isUsable()) {
            curl_setopt($this->ch, CURLOPT_PROXYTYPE, $this->getProxy()->getType());
            curl_setopt($this->ch, CURLOPT_PROXY, $this->getProxy()->getIp() . ':' . $this->getProxy()->getPort());
        }
        return $this->ch;
    }

    public function getCurlResults()
    {
        if (!$this->ch) {
            return false;
        }
        $content = curl_exec($this->ch);
        if (curl_errno($this->ch)) { // is timed out ?
            return false;
        }
        if ($this->state != self::ONLY_BODY) {
            $head = curl_getinfo($this->ch);
            if (!$this->getFile()->getSize() && isset($head['download_content_length']) && $head['download_content_length'] >= 0) {
                $this->getFile()->setSize($head['download_content_length']);
            }
        }
        switch ($this->state) {
            case self::ONLY_HEAD:
                $ret = $head;
                unset($head);
                break;
            case self::ONLY_BODY:
                $ret = $content;
                unset($content);
                break;
            default:
            case self::BOTH:
                $ret = ['head' => $head, 'content' => $content];
                unset($content, $head);
                break;
        }
        curl_close($this->ch);
        return $ret;
    }

    public function callCurl($state = self::BOTH, $header = [])
    {
        $this->initCurl($state, $header);
        return $this->getCurlResults();
    }


    private function prepareHeaders($header = array())
    {
        $ret = [];
        if (!is_array($header)) {
            $header = array();
        }
        $header = array_merge($this->getFile()->getHeaders(), $header);
        foreach ($header as $name => $value) {
            switch (strtolower($name)) {
                case 'cookie':
                    curl_setopt($this->ch, CURLOPT_COOKIE, $value);
                    break;
                case 'range':
                    curl_setopt($this->ch, CURLOPT_RANGE, str_replace('bytes=', '', $value));
                    break;
                case 'user-agent':
                    curl_setopt($this->ch, CURLOPT_USERAGENT, $value);
                    break;
                default:
                    $ret[] = "{$name}:{$value}";
            }
        }
        return $ret;
    }
}