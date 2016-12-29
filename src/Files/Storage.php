<?php
namespace IVIR3aM\DownloadManager\Files;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Files;
//TODO: must implemeting the storage
class Storage extends AbstractActiveArray
{
    protected function filterInputHook($offset, $value)
    {
        return is_a($value, Files::class);
    }
}