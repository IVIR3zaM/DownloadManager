<?php
namespace IVIR3aM\DownloadManager\HttpClient;

/**
 * Interface FilesChangesInterface
 * 
 * what is expected of a change file object in HttpClient package
 * 
 * @package IVIR3aM\DownloadManager\HttpClient
 */
interface FilesChangesInterface
{
    /**
     * @return string The name of changed field
     */
    public function getField();


    /**
     * @return mixed The value of changed field
     */
    public function getValue();
}