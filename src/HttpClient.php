<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;
use SplObserver;
use SplSubject;

class HttpClient implements SplObserver
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
     * @var string
     */
    private $link;

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

        $this->ch = curl_init();
    }

    public static function sanitizeUriPart($string)
    {
        return rawurlencode(rawurldecode($string));
    }

    public static function sanitizeLink($link)
    {
        $p = parse_url($link);
        if (!isset($p['host'])) {
            return false;
        }
        $path = '';
        if (isset($p['path'])) {
            $path = array_map(function ($part) {
                return static::sanitizeUriPart($part);
            }, explode('/', $p['path']));
            $path = implode('/', $path);
        }
        return $p['scheme'] . '://' . (
        isset($p['user']) ?
            static::sanitizeUriPart($p['user']) . (
            isset($p['pass']) ?
                ':' . static::sanitizeUriPart($p['pass']) :
                ''
            ) . '@' :
            ''
        ) . $p['host'] . (
        isset($p['port']) ?
            ':' . intval($p['port']) :
            ''
        ) . $path . (
        isset($p['query']) ?
            '?' . $p['query'] :
            ''
        ) . (
        isset($p['fragment']) ?
            '#' . $p['fragment'] :
            ''
        );
    }

    /**
     * @param $link string
     * @return void
     */
    private function setLink($link)
    {
        $link = static::sanitizeLink($link);
        if (!$link) {
            throw new \Exception('invalid link specified');
        }
        $this->link = $link;
    }

    public function getLink()
    {
        return $this->link;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile(Files $file)
    {
        $this->file = $file;
        $this->setLink($file->getLink());
        $file->attach($this);
        return $this;
    }

    public function update(SplSubject $subject)
    {
        if ($subject instanceof Files) {
            $change = $subject->getData();
            if ($change instanceof FilesChanges && $change->getField() == 'link') {
                $this->setLink($change->getValue());
            }
        }
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

    public function getPacketSize()
    {
        return $this->getFile()->getPacketSize();
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
        curl_setopt($this->ch, CURLOPT_URL, $this->getLink());
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->getUserAgent());
        $this->state = $state;
        switch ($state) {
            case self::ONLY_HEAD:
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                curl_setopt($this->ch, CURLOPT_MAX_RECV_SPEED_LARGE, 0);
                break;
            default:
            case self::ONLY_BODY:
            case self::BOTH:
            curl_setopt($this->ch, CURLOPT_NOBODY, false);
            curl_setopt($this->ch, CURLOPT_MAX_RECV_SPEED_LARGE, ($this->getFile()->getMaxSpeed() > 0 ? $this->getFile()->getMaxSpeed() : 0));
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
        } else {
            curl_setopt($this->ch, CURLOPT_PROXY, null);
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
        $head = curl_getinfo($this->ch);
        if (isset($head['url']) && $head['url'] != $this->getLink()) {
            $this->setLink($head['url']);
        }
        if (isset($head['download_content_length']) && $head['download_content_length'] >= 0 && $this->getFile()->getSize() != $head['download_content_length']) {
            $this->getFile()->setSize($head['download_content_length']);
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

    public function __destruct()
    {
        curl_close($this->ch);
    }
}