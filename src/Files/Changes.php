<?php
namespace IVIR3aM\DownloadManager\Files;

use IVIR3aM\DownloadManager\HttpClient\FilesChangesInterface as HttpClientFilesChanges;
use Serializable;

class Changes implements HttpClientFilesChanges, Serializable
{
    const INNER = 0;
    const OUTER = 1;

    /**
     * @var int
     */
    private $type;

    private $field;

    private $value;

    private $oldValue;

    public function __construct($field, $value, $oldValue = null, $type = self::INNER)
    {
        if (!in_array($field, Files::FIELDS)) {
            throw new Exception('field is not valid', 1);
        }
        $this->field = $field;
        $this->value = $value;
        $this->oldValue = $oldValue;
        $this->type = ($type == self::INNER ? self::INNER : self::OUTER);
    }

    public function getType()
    {
        return $this->type;
    }

    public function getField()
    {
        return $this->field;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getOldValue()
    {
        return $this->oldValue;
    }

    public function serialize()
    {
        return serialize([
            'type' => $this->type,
            'field' => $this->field,
            'value' => $this->value,
            'oldValue' => $this->oldValue,
        ]);
    }

    public function unserialize($serialized)
    {
        $list = unserialize($serialized);
        if (is_array($list)) {
            if (isset($list['type'])) {
                $this->type = $list['type'];
            }
            if (isset($list['field'])) {
                $this->field = $list['field'];
            }
            if (isset($list['value'])) {
                $this->value = $list['value'];
            }
            if (isset($list['oldValue'])) {
                $this->oldValue = $list['oldValue'];
            }
        }
    }
}