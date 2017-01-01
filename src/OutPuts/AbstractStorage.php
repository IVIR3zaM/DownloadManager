<?php
namespace IVIR3aM\DownloadManager\OutPuts;

use IVIR3aM\DownloadManager\Files\Files;

/**
 * Class AbstractStorage
 * this is the Factory Design Pattern and getHandler() method is FactoryMethod() actually
 * @package PAdmin\DownloadManager\OutPuts
 */
abstract class AbstractStorage
{
    /**
     * @var HandlerInterface[]
     */
    protected $storage = [];

    /**
     * put data to handler for specific file
     *
     * @param Files $file
     * @param mixed $data
     * @param int $position
     * @return mixed
     */
    public function put(Files $file, $data, $position = 0)
    {
        $handler = $this->getHandler($file->getLink(), $file->getSize());
        if (!is_object($handler) || !in_array(HandlerInterface::class, class_implements($handler))) {
            throw new Exception('invalid handler passed in', 1);
        }
        return $handler->put($data, $position);
    }

    /**
     * get a Handler Interface for a file with its link and size
     * this function must check for existing handler in $this->storage
     * and if that does not exists, make a new one and return it
     *
     * @param $link
     * @param $size
     * @return HandlerInterface
     */
    abstract protected function getHandler($link, $size);
}