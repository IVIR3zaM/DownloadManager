<?php
namespace IVIR3aM\DownloadManager\HttpClient;

/**
 * Interface ProxyInterface
 *
 * what is expected from a proxy object in HttpClient package
 *
 * @package IVIR3aM\DownloadManager\HttpClient
 */
interface ProxyInterface
{
    /**
     * @return bool Is this proxy usable
     */
    public function isUsable();

    /**
     * @return int The proxy type to use is CURL library
     */
    public function getTypeCurl();

    /**
     * @return string The proxy IP
     */
    public function getIp();

    /**
     * @return int The proxy Port
     */
    public function getPort();
}