<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\DownloadManager\HttpClient\Exception as HttpClientException;
use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Threads\AbstractManager as ThreadsManager;
use IVIR3aM\DownloadManager\Outputs\AbstractStorage as OutputsStorage;
use IVIR3aM\DownloadManager\Files\Files;
use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;
use IVIR3aM\DownloadManager\Proxies\Stack as ProxiesStack;
use IVIR3aM\DownloadManager\Proxies\Proxies;
use IVIR3aM\DownloadManager\HttpClient\HttpClient;
use IVIR3aM\DownloadManager\TimeoutHolderTrait;
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
    use TimeoutHolderTrait;
    /**
     * @var ThreadsManager
     */
    private $threadManager;

    /**
     * @var ProxiesStack
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

    /**
     * @var int the system work with and without a valid proxy
     */
    private $workRule;

    const WORK_DIRECT = 1;

    const WORK_PROXY = 2;

    const WORK_NONE = 0;

    const WORK_ALL = 3;

    public function __construct(array $data = array())
    {
        $this->setWorkRule(static::WORK_ALL);
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
        if ($this->active) {
            $this->start();
        } else {
            $this->stop();
        }
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setWorkRule($workRule)
    {
        $this->workRule = intval($workRule);
    }

    public function getWorkRule()
    {
        return $this->workRule;
    }

    public function setWorkDirect($work = true)
    {
        $this->setWorkRule($work ? $this->getWorkRule() | static::WORK_DIRECT : $this->getWorkRule() & ~static::WORK_DIRECT);
    }

    public function getWorkDirect()
    {
        return ($this->getWorkRule() & static::WORK_DIRECT) === static::WORK_DIRECT;
    }

    public function setWorkProxy($work = true)
    {
        $this->setWorkRule($work ? $this->getWorkRule() | static::WORK_PROXY : $this->getWorkRule() & ~static::WORK_PROXY);
    }

    public function getWorkProxy()
    {
        return ($this->getWorkRule() & static::WORK_PROXY) === static::WORK_PROXY;
    }

    private function setNewProxyToFile(Files $file, $force = false)
    {
        $result = false;
        $proxy = $file->getProxy();
        if ($this->getWorkProxy()) {
            if (!$force && $proxy->isUsable()) {
                return true;
            }
            $file->setProxy($this->getRandomProxy());
            $result = $file->getProxy()->isUsable() || $this->getWorkDirect();
        } else if($this->getWorkDirect()) {
            $result = true;
            $file->setProxy(new Proxies());
        }
        $this->freeProxy($proxy);
        return $result;
    }

    private function initializeFile(Files $file)
    {
        if (!$file->getRunning() && $file->getActive()) {
            if (!$this->setNewProxyToFile($file)) {
                return false;
            }
            if (!$file->getClient()) {
                $file->setClient(new HttpClient($file, $this->getConnectTimeout(), $this->getFetchTimeout()));
                try {
                    $this->fetchFileInfo($file);
                } catch (HttpClientException $e) {
                    if (!$this->setNewProxyToFile($file, true)) {
                        return false;
                    }
                    try {
                        $this->fetchFileInfo($file);
                    } catch (HttpClientException $e) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function fetchFileInfo(Files $file)
    {
        if ($file->getSize() > 0) {
            return;
        }
        $head = $file->getClient()->getInfo();
        if (isset($head['http_code']) && $head['http_code'] == 200 &&
            isset($head['download_content_length']) && $head['download_content_length'] >= 0 &&
            $file->getSize() != $head['download_content_length']
        ) {
            $file->setSize($head['download_content_length']);
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
                if (!$this->stop($index, $running)) {
                    $result = false;
                }
            }
        } else {
            $result = false;
            $file = $this->getFileByIndex($index);
            if ($file) {
                $result = $this->getThreadsManager()->stop($index, $running);
                if ($result && $file->getSpeed() > 0) {
                    $file->setSpeed(0);
                }
            }
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
        $file = $this->getFileByIndex($index);
        if ($file) {
            $file->setSpeed(0);
            $file->setProxy($this->getRandomProxy());
        }
//        sleep(1);
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

        if ($this->initializeFile($file)) {
            if ($this->getSpeed() < $this->getMaxSpeed()) {
                if ($file->getWaiting()) {
                    $file->setWaiting(false);
                }
                return $this->getThreadsManager()->start($index);
            }
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
        $this->checkResumeThreads();
        return $this->getThreadsManager() ? $this->getThreadsManager()->mustWait() : false;
    }

    public function setProxies(ProxiesStack $proxies)
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
        return $this->getProxies() ? $this->getProxies()->getRandomProxy() : new Proxies();
    }

    public function freeProxy(Proxies $proxy)
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
}