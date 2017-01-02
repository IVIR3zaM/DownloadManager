<?php
namespace IVIR3aM\DownloadManager;

/**
 * Class TimeoutHolderTrait
 *
 * a helper for holding two timeout values with setters and getters for using in curl sessions
 *
 * @package IVIR3aM\DownloadManager
 */
trait TimeoutHolderTrait
{
    /**
     * @var int connect timeout of a curl session
     */
    private $connectTimeout = 5;

    /**
     * @var int fetching data from url timeout of  a curl session
     */
    private $fetchTimeout = 30;

    /**
     * @return int connect timeout in seconds
     */
    public function getConnectTimeout()
    {
        return $this->connectTimeout;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setConnectTimeout($timeout)
    {
        $timeout = intval($timeout);
        if ($timeout < 1) {
            $timeout = 1;
        }
        $this->connectTimeout = $timeout;
        return $this;
    }

    /**
     * @return int fetch timeout in seconds
     */
    public function getFetchTimeout()
    {
        return $this->fetchTimeout;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setFetchTimeout($timeout)
    {
        $timeout = intval($timeout);
        if ($timeout < 1) {
            $timeout = 1;
        }
        $this->fetchTimeout = $timeout;
        return $this;
    }
}