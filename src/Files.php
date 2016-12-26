<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Files\Changes;
use SplSubject;
use SplObserver;
use SplObjectStorage;

class Files extends AbstractActiveArray implements SplSubject
{
    const FIELDS = ['link', 'size', 'maxSpeed', 'speed', 'position', 'headers', 'running', 'active'];
    const INTEGER_FIELDS = ['size', 'maxSpeed', 'position', 'speed'];
    private $stepLength = 0;

    /**
     * @var SplObjectStorage
     */
    private $observers;

    /**
     * @var Changes
     */
    private $data;

    /**
     * @var int
     */
    private $changeType;

    public function __construct(array $data = array())
    {
        $this->observers = new SplObjectStorage();
        $this->setOuterChange();
        if (!isset($data['headers']) || !is_array($data['headers'])) {
            $data['headers'] = array();
        }
        $data['active'] = isset($data['active']) ? boolval($data['active']) : true;
        $data['running'] = false;
        parent::__construct($data);
    }

    protected function filterInputHook($offset, $value)
    {
        $result = in_array($offset, self::FIELDS);
        return $result;
    }

    protected function sanitizeOutputHook($offset, $value)
    {
        if (in_array($offset, self::INTEGER_FIELDS)) {
            $value = intval($value);
        }
        return $value;
    }

    public function attach(SplObserver $observer)
    {
        $this->observers->attach($observer);
    }
    public function detach(SplObserver $observer)
    {
        $this->observers->detach($observer);
    }

    public function notify()
    {
        if (is_a($this->getData(), Changes::class)) {
            foreach ($this->observers as $observer) {
                $observer->update($this);
            }
        }
    }

    public function setData(Changes $data)
    {
        $this->setOuterChange();
        $this->data = $data;
        $this->notify();
    }

    public function getData()
    {
        return $this->data;
    }

    protected function updateHook($offset, $data, $oldData)
    {
        $this->setData(new Changes($offset, $data, $oldData, $this->changeType));
    }

    protected function removeHook($offset, $oldData)
    {
        $this->setData(new Changes($offset, null, $oldData, $this->changeType));
    }

    protected function insertHook($offset, $data)
    {
        $this->setData(new Changes($offset, $data, null, $this->changeType));
    }

    protected function setInnerChange()
    {
        $this->changeType = Changes::INNER;
    }

    protected function setOuterChange()
    {
        $this->changeType = Changes::OUTER;
    }

    public function getLink()
    {
        return $this['link'];
    }

    public function setLink($link)
    {
        $this->setInnerChange();
        $this['link'] = $link;
        return $this;
    }

    public function getSize()
    {
        return intval($this['size']);
    }

    public function setSize($size)
    {
        $this->setInnerChange();
        $size = intval($size);
        if ($size < 0) {
            $size = 0;
        }
        $this['size'] = $size;
        return $this;
    }

    public function getMaxSpeed()
    {
        if ((!isset($this['maxSpeed']) || $this['maxSpeed'] <= 0)
            && isset($this['size']) && $this['size'] > 0) {
            $this['maxSpeed'] = $this['size'];
        }
        return $this['maxSpeed'];
    }

    public function setSpeed($speed)
    {
        $this->setInnerChange();
        $this['speed'] = intval($speed);
        return $this;
    }

    public function getSpeed()
    {
        return intval($this['speed']);
    }

    public function setMaxSpeed($speed)
    {
        $this->setInnerChange();
        $this['maxSpeed'] = intval($speed);
        return $this;
    }

    public function getPosition()
    {
        return intval($this['position']);
    }

    public function setPosition($position)
    {
        $this->setInnerChange();
        $position = intval($position);
        if ($position < 0) {
            $position = 0;
        }
        $this['position'] = $position;
        return $this;
    }

    public function getActive()
    {
        return boolval($this['active']);
    }

    public function setActive($active)
    {
        $this->setInnerChange();
        $this['active'] = boolval($active);
        return $this;
    }

    public function getRunning()
    {
        return boolval($this['running']);
    }

    public function setRunning($active)
    {
        $this->setInnerChange();
        $this['running'] = boolval($active);
        return $this;
    }

    public function getHeaders()
    {
        return $this['headers'];
    }

    public function setHeaders($headers)
    {
        $this->setInnerChange();
        if (is_array($headers)) {
            $this['headers'] = $headers;
        }
        return $this;
    }

    public function addHeader($name, $value)
    {
        $this->setInnerChange();
        $this['headers'][$name] = $value;
        return $this;
    }

    public function removeHeader($name)
    {
        $this->setInnerChange();
        if (isset($this['headers'][$name])) {
            unset($this['headers'][$name]);
        }
        return $this;
    }

    public function getDownloadStepLength()
    {
        if (!$this->stepLength) {
            $this->stepLength = $this->getMaxSpeed() * 5;
            if ($this->stepLength + $this->getPosition() > $this->getSize()) {
                $this->stepLength = $this->getSize() - $this->getPosition();
            }
        }
        return $this->stepLength;
    }


    public function isCompleted()
    {
        return isset($this['size']) && $this->getPosition() >= $this->getSize();
    }

    public function moveForward()
    {
        if (!$this->isCompleted()) {
            $position = $this->getPosition() + $this->getDownloadStepLength();
            $this->stepLength = 0;
            if ($position >= $this->getSize()) {
                $position = $this->getSize();
            }
            $this->setPosition($position);
        }
        return $this;
    }
}