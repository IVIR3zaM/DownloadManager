<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Threads\AbstractManager as ThreadsManager;
use IVIR3aM\DownloadManager\Outputs\AbstractStorage as OutputsStorage;
use IVIR3aM\DownloadManager\Files\Changes as FilesChanges;
use SplObserver;
use SplSubject;
use SplObjectStorage;

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
        // TODO: pause check must implemented
        $file->attach($this);
        $this->start($index);
    }

    protected function removeHook($index, Files $file)
    {
        // TODO: pause check must implemented
        $this->stop($index);
        $file->detach($this);
    }

    public function update(SplSubject $subject)
    {
        if (is_a($subject, Files::class)) {
            $change = $subject->getData();
            $index = $this->getIndexByFile($subject);
            if ($index !== false && is_a($change, FilesChanges::class)) {
                $this->setData(new Changes($index, $change->getField(), $change->getValue(), $change->getOldValue(), $change->getType()));
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

    public function start($index = null)
    {
        if (!$this->active || !$this->setupCompleted) {
            return false;
        }
        return boolval(is_null($index) ? $this->getThreadsManager()->startDownloads() : $this->getThreadsManager()->start($index));
    }

    public function stop($index = null)
    {
        if (!$this->setupCompleted) {
            return false;
        }
        return boolval(is_null($index) ? $this->getThreadsManager()->stopDownloads() : $this->getThreadsManager()->stop($index));
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

    public function getProxy()
    {
        return $this->getProxies() ? $this->getProxies()->getProxy() : new Proxy();
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

        foreach ($this as $index => $file) {
            $list[] = $index;
        }

        return $list;
    }
}