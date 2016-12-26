<?php
namespace IVIR3aM\DownloadManager\Threads;

use IVIR3aM\DownloadManager\HttpClient;

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
        $this->client = $client;
        $this->position = $client->getFile()->getPosition();
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

    public function download()
    {
        return $this->getClient()->download($this->position, $this->getClient()->getDownloadStepLength());
    }

    /**
     * @return mixed data
     */
    abstract public function run();
}