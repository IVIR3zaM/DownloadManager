<?php
namespace IVIR3aM\DownloadManager\HttpClient;

use SplObserver;

/**
 * Interface FilesInterface
 *
 * what is expected of a file object in HttpClient package
 *
 * @package IVIR3aM\DownloadManager\HttpClient
 */
interface FilesInterface
{
    /**
     * @return ProxyInterface
     */
    public function getProxy();

    /**
     * @return int The packet size in bytes
     */
    public function getPacketSize();

    /**
     * @return string The link of the file
     */
    public function getLink();

    /**
     * @param int $speed
     * @return void
     */
    public function setSpeed($speed);

    /**
     * @return int The max speed of download rate in Bytes per Seconds
     */
    public function getMaxSpeed();


    /**
     * @return array List of custom headers for using in curl
     */
    public function getHeaders();

    /**
     * @param SplObserver $observer
     * @return void
     */
    public function attach(SplObserver $observer);
}