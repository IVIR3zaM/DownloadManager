<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Threads\AbstractManager as ThreadsManager;
use IVIR3aM\DownloadManager\Outputs\AbstractStorage as OutputsStorage;
use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;
use SplObserver;
use SplSubject;
use SplObjectStorage;

/**
 * Class Manager
 * @todo must seperate the storage of the files
 * @todo must implement a mediator pattern for whole system
 * @package IVIR3aM\DownloadManager
 */
class Manager extends AbstractActiveArray implements SplObserver, SplSubject
{
    /**
     * @var ThreadsManager
     */
    private $threadManager;

    /**
     * @var Proxies
     */
    private $proxies;

    /**
     * @var OutputsStorage
     */
    private $output;

    /**
     * @var bool all required objects exists or not
     */
    private $setupCompleted = false;

    /**
     * @var SplObjectStorage
     */
    public $observers;

    /**
     * @var Changes
     */
    private $data;

    /**
     * @var bool
     */
    private $active;

    /**
     * @var callable
     */
    private $beforeThreadHook;

    /**
     * @var callable
     */
    private $parentHook;

    /**
     * @var callable
     */
    private $childHook;

    /**
     * @var int total maximum download speed in bytes per seconds
     */
    private $maxSpeed = 0;

    /**
     * @var int the size of each packet download per thread in bytes
     */
    private $packetSize = 1048576; // equal to 1MB

    public function __construct(array $data = array())
    {
        $this->active = false;
        $this->observers = new SplObjectStorage();
        parent::__construct($data);
    }

    protected function filterInputHook($offset, $value)
    {
        return is_a($value, Files::class);
    }

    protected function insertHook($index, Files $file)
    {
        $file->setPacketSize($this->getPacketSize());
        $this->initMaxSpeed();
        $file->attach($this);
        $this->start($index);
    }

    protected function filterRemoveHook($index)
    {
        $file = $this->getFileByIndex($index);
        $this->stop($index);
        $file->detach($this);
        return true;
    }

    protected function removeHook($index, Files $file)
    {
        $this->initMaxSpeed();
    }

    public function update(SplSubject $subject)
    {
        if (is_a($subject, Files::class)) {
            $change = $subject->getData();
            $index = $this->getIndexByFile($subject);
            if ($index !== false && $change instanceof FilesChanges) {
                if ($change->getType() == FilesChanges::INNER && $change->getField() == 'running') {
                    $this->checkResumeThreads();
                }
                $this->setData(new Changes($index, $change->getField(), $change->getValue(), $change->getOldValue(),
                    $change->getType()));
            }
        }
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
        $this->data = $data;
        $this->notify();
    }

    public function getData()
    {
        return $this->data;
    }

    public function setActive($active = true)
    {
        $this->active = boolval($active);
    }

    public function getActive()
    {
        return $this->active;
    }

    private function initializeFile(Files $file)
    {
        if (!$file->getRunning() && $file->getActive()) {
            if (!$file->getProxy()->isUsable()) {
                $file->setProxy($this->getRandomProxy());
            }
            if (!$file->getClient()) {
                $file->setClient(new HttpClient($file));
                $file->getClient()->getInfo();
            }
        }
    }

    public function start($index = null)
    {
        if (!$this->active || !$this->setupCompleted) {
            return false;
        }
        if (is_null($index)) {
            $result = true;
            foreach ($this->getFilesIndexes() as $index) {
                if ($this->canStartNewThread($index) === false) {
                    $result = false;
                }
            }
        } else {
            $result = $this->canStartNewThread($index);
        }
        return boolval($result);
    }

    public function stop($index = null, $running = false)
    {
        if (!$this->setupCompleted) {
            return false;
        }
        if (is_null($index)) {
            $result = true;
            foreach ($this->getFilesIndexes() as $index) {
                if ($this->getThreadsManager()->stop($index, $running) === false) {
                    $result = false;
                }
            }
        } else {
            $result = $this->getThreadsManager()->stop($index, $running);
        }
        return boolval($result);
    }

    public function successThread($index)
    {
        // TODO: must implement strategy pattern
        $file = $this->getFileByIndex($index);
        if (!$file) {
            return;
        }
        $file->moveForward();
        $this->stop($index, true);
        $this->start($index);
    }

    public function errorThread($index)
    {
        // TODO: must implement strategy pattern
        $this->stop($index);
        sleep(1);
        $this->start($index);
//        $file = $this->getManager()->getFileByIndex($index);
//        if ($this->fileIsValid($file)) {
//            $file->setActive(false);
//        }
    }

    protected function canStartNewThread($index)
    {
        // TODO: must implement strategy pattern
        $file = $this->getFileByIndex($index);
        if (!$file) {
            return false;
        }
        $this->initializeFile($file);
        if ($this->getSpeed() < $this->getMaxSpeed()) {
            if ($file->getWaiting()) {
                $file->setWaiting(false);
            }
            return $this->getThreadsManager()->start($index);
        }
        $this->stop($index);
        if (!$file->getWaiting()) {
            $file->setWaiting(true);
        }
        return null;
    }

    protected function checkResumeThreads()
    {
        // TODO: must implement strategy pattern
        foreach ($this->getFiles() as $index => $file) {
            if ($file->getWaiting()) {
                if ($this->canStartNewThread($index) === null) {
                    break;
                }
            }
        }
    }

    private function checkSetupStatus()
    {
        $this->setupCompleted = $this->threadManager && $this->proxies && $this->output;
        return $this;
    }

    public function setOutput(OutputsStorage $output)
    {
        $this->output = $output;
        return $this->checkSetupStatus();
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function output(Files $file, $data, $position)
    {
        $this->getOutput()->put($file, $data, $position);
    }

    public function setThreadsManager(ThreadsManager $threadManager)
    {
        $this->threadManager = $threadManager;
        return $this->checkSetupStatus();
    }

    public function getThreadsManager()
    {
        return $this->threadManager;
    }

    public function mustWait()
    {
        return $this->getThreadsManager() ? $this->getThreadsManager()->mustWait() : false;
    }

    public function setProxies(Proxies $proxies)
    {
        $this->proxies = $proxies;
        return $this->checkSetupStatus();
    }

    public function getProxies()
    {
        return $this->proxies;
    }

    public function getRandomProxy()
    {
        return $this->getProxies() ? $this->getProxies()->getRandomProxy() : new Proxy();
    }

    public function freeProxy(Proxy $proxy)
    {
        if ($this->getProxies()) {
            $this->getProxies()->freeProxy($proxy);
        }
    }

    public function getFiles()
    {
        return $this;
    }

    public function getFileByIndex($index)
    {
        return $this[$index];
    }

    public function getIndexByFile(Files $file)
    {
        return $this->array_search($file, true);
    }

    public function getFilesIndexes()
    {
        $list = [];

        foreach ($this->getFiles() as $index => $file) {
            $list[] = $index;
        }

        return $list;
    }

    public function setBeforeThreadHook(callable $callback)
    {
        $this->beforeThreadHook = $callback;
        return $this;
    }

    public function getBeforeThreadHook()
    {
        if (!is_callable($this->beforeThreadHook)) {
            $this->beforeThreadHook = function () {
            };
        }
        return $this->beforeThreadHook;
    }

    public function setParentHook(callable $callback)
    {
        $this->parentHook = $callback;
        return $this;
    }

    public function getParentHook()
    {
        if (!is_callable($this->parentHook)) {
            $this->parentHook = function () {
            };
        }
        return $this->parentHook;
    }

    public function setChildHook(callable $callback)
    {
        $this->childHook = $callback;
        return $this;
    }

    public function getChildHook()
    {
        if (!is_callable($this->childHook)) {
            $this->childHook = function () {
            };
        }
        return $this->childHook;
    }

    private function initMaxSpeed()
    {
        // TODO: must implement strategy pattern
        foreach ($this->getFiles() as $file) {
            if ($file->getMaxSpeed() != $this->getMaxSpeed()) {
                $file->setMaxSpeed($this->getMaxSpeed());
            }
        }
    }

    public function setMaxSpeed($speed)
    {
        $this->maxSpeed = intval($speed);
        $this->initMaxSpeed();
        return $this;
    }

    public function getMaxSpeed()
    {
        return $this->maxSpeed;
    }

    public function getSpeed()
    {
        $speed = 0;
        foreach ($this->getFiles() as $index => $file) {
            if ($file->getRunning()) {
                $speed += $file->getSpeed();
            }
        }
        return $speed;
    }

    public function setPacketSize($size)
    {
        $this->packetSize = intval($size);
        foreach ($this->getFiles() as $file) {
            $file->setPacketSize($this->packetSize);
        }
        return $this;
    }

    public function getPacketSize()
    {
        return $this->packetSize;
    }

    public function __destruct()
    {
        foreach ($this->getFiles() as $index => $file) {
            if ($file->getRunning()) {
                $file->stop($index);
            }
        }
    }
}