<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;

class Changes extends FilesChanges
{
    /**
     * @var mixed
     */
    private $index;

    public function __construct($index, $field, $value, $oldValue = null, $type = self::INNER)
    {
        parent::__construct($field, $value, $oldValue, $type);
        $this->index = $index;
    }

    public function getIndex()
    {
        return $this->index;
    }
}