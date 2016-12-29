<?php
namespace IVIR3aM\Strategies;

use IVIR3aM\DownloadManager\Files\Storage;

abstract class AbstractStrategy
{
    /**
     * @var Storage
     */
    protected $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param int $size speed of download in bytes per seconds
     * @return void
     */
    abstract function setMaxSpeed($size);

    /**
     * @param $index the index of the file in the storage
     * @return bool starting of downlod the file accepted
     */
    abstract function startAccepted($index);
}