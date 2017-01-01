<?php
namespace IVIR3aM\DownloadManager\Threads;

use IVIR3aM\DownloadManager\HttpClient\HttpClient;

abstract class AbstractThread
{
    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var int
     */
    protected $position = 0;

    public function __construct(HttpClient $client)
    {
        $this->setClient($client);
        $this->setPosition($client->getFile()->getPosition());
    }

    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setClient(HttpClient $client)
    {
        $this->client = $client;
        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getProxy()
    {
        return $this->getClient()->getProxy();
    }

    public function getPacketSize()
    {
        return $this->getClient()->getPacketSize();
    }

    public function download()
    {
        return $this->getClient()->download($this->getPosition(), $this->getPacketSize());
    }

    /**
     * @return mixed data
     */
    abstract public function run();
}