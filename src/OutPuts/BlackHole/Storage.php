<?php
namespace IVIR3aM\DownloadManager\OutPuts\BlackHole;

use IVIR3aM\DownloadManager\OutPuts\AbstractStorage;

/**
 * Class Storage
 * BlackHole is an output driver that doing nothing at all
 * @package PAdmin\DownloadManager\OutPuts\BlackHole
 */
class Storage extends AbstractStorage
{
    /**
     * @param string $link the link of the file
     * @param integer $size the size of the file in bytes
     * @return \IVIR3aM\DownloadManager\OutPuts\HandlerInterface
     */
    protected function getHandler($link, $size)
    {
        if (!isset($this->storage[$link])) {
            $this->storage[$link] = new Handler($link, $size);
        }

        return $this->storage[$link];
    }
}