<?php
namespace IVIR3aM\DownloadManager\OutPuts\BlackHole;

use IVIR3aM\DownloadManager\OutPuts\HandlerInterface;

/**
 * Class Handler
 * BlackHole is an output driver that doing nothing at all
 * @package PAdmin\DownloadManager\OutPuts\BlackHole
 */
class Handler implements HandlerInterface
{
    /**
     * Handler constructor.
     * this will do nothing at all
     * @param string $link
     * @param integer $size
     */
    public function __construct($link, $size) {}

    /**
     * Handler putting to output
     * this will do nothing at all
     * @param mixed $data
     * @param int $position
     * @return boolean
     */
    public function put($data, $position = 0) {
        return true;
    }
}