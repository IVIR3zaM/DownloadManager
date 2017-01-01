<?php
namespace IVIR3aM\DownloadManager\Threads\Pcntl;

use IVIR3aM\DownloadManager\Threads\AbstractThread;
use IVIR3aM\DownloadManager\HttpClient\HttpClient;
use IVIR3aM\DownloadManager\Threads\Exception;

class Thread extends AbstractThread
{
    private $running = false;
    public function __construct(HttpClient $client)
    {
        pcntl_signal(SIGABRT, array($this, 'abort'));
        parent::__construct($client);
    }

    public function run()
    {
        $this->running = true;
        $data = $this->download();
        if ($data === null) {
            throw new Exception('data is null', 12);
        }
        $this->running = false;
        return $data;
    }

    public function abort()
    {
        if ($this->running) {
            exit(4);
        }

    }
}