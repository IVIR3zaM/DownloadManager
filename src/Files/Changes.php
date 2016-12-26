<?php
namespace IVIR3aM\DownloadManager\Files;

use IVIR3aM\DownloadManager\Files;

class Changes
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
            throw new \Exception('filed is not valid');
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
}