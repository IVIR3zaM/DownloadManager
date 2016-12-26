<?php
namespace IVIR3aM\DownloadManager\OutPuts;

/**
 * Interface HandlerInterface
 * @package PAdmin\DownloadManager\OutPuts
 */
interface HandlerInterface
{
    /**
     * HandlerInterface constructor.
     * @param string $link the link of the file
     * @param integer $size the size of the file
     */
    public function __construct($link, $size);

    /**
     * HandlerInterface putting data to the output
     * @param mixed $data
     * @param integer $position
     * @return boolean
     */
    public function put($data, $position = 0);
}