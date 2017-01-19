<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;
use Serializable;

class Changes extends FilesChanges implements Serializable
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

    public function serialize()
    {
        $list = unserialize(parent::serialize());
        $list['index'] = $this->index;
        return serialize($list);
    }

    public function unserialize($serialized)
    {
        parent::unserialize($serialized);
        $list = unserialize($serialized);
        if (is_array($list)) {
            if (isset($list['index'])) {
                $this->index = $list['index'];
            }
        }
    }
}