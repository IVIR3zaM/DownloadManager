<?php
namespace IVIR3aM\DownloadManager\Threads;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;
use IVIR3aM\DownloadManager\Manager as DownloadManager;
use IVIR3aM\DownloadManager\Files;

abstract class AbstractManager extends AbstractActiveArray
{
    /**
     * @var DownloadManager
     */
    protected $manager;

    /**
     * @var bool status of downloading
     */
    protected $started = false;

    public function __construct(DownloadManager $manager, $data = array())
    {
        parent::__construct($data);
        $this->manager = $manager;
    }

    protected function filterInputHook($offset, $value)
    {
        return is_a($value, AbstractThread::class);
    }

    public function setManager(DownloadManager $manager)
    {
        $this->manager = $manager;
        return $this;
    }

    public function getManager()
    {
        return $this->manager;
    }

    public function startDownloads()
    {
        if ($this->started) {
            return true;
        }

        $this->started = true;
        foreach ($this->getManager()->getFilesIndexes() as $index) {
            $this->start($index);
        }

        return $this;
    }

    public function stopDownloads()
    {
        $this->started = false;
        foreach ($this as $index => $thread) {
            $this->stop($index);
        }
        return $this;
    }

    public function start($index)
    {
        if (isset($this[$index])) {
            return true;
        }
        $file = $this->getManager()->getFileByIndex($index);
        if (!$this->fileIsValid($file) || !$file->getActive()) {
            return false;
        }
        if (!$file->isCompleted()) {
            $thread = $this->createThread($file, $index);
            if (!$this->threadIsValid($thread)) {
                throw new \Exception('Invalid thread returned from createThread() method');
            }
            $file->setRunning(true);
            $this[$index] = $thread;
        }
        return true;
    }

    public function stop($index, $running = false)
    {
        if (!$running) {
            $file = $this->getManager()->getFileByIndex($index);
            if ($this->fileIsValid($file)) {
                $file->setRunning(false);
            }
        }
        if (isset($this[$index])) {
            $this->destroyThread($index);
            unset($this[$index]);
        }
        return $this;
    }

    protected function fileIsValid($file)
    {
        return $file && is_a($file, Files::class);
    }

    protected function threadIsValid($thread)
    {
        return $thread && is_a($thread, AbstractThread::class);
    }

    public function output(Files $file, $data, $position)
    {
        $this->getManager()->output($file, $data, $position);
    }
    
    protected function errorThread($index)
    {
        $this->stop($index);
        $file = $this->getManager()->getFileByIndex($index);
        if ($this->fileIsValid($file)) {
            $file->setActive(false);
        }
        return $this;
    }

    protected function successThread($index)
    {
        $file = $this->getManager()->getFileByIndex($index);
        if ($this->fileIsValid($file)) {
            $file->moveForward();
            $this->getManager()->success($index);
        }
        return $this;
    }

    /**
     * this function could have process to check threads statuses and report that there is
     * a running thread or not. always in implementing the system there is a while over this method
     * @return bool
     */
    abstract public function mustWait();

    /**
     * in thread or thread manager if the result was success it must call output() method
     * with appropriate arguments, and also call successThread() method. the reason that
     * they are separated is in an multi thread php system parent thread is separated from
     * child thread always child fetch final data and can only send a signal to parent thread
     * that the job was successful, then parent can call successMethod() to continue the proccess
     * of downloading the file.
     * @param Files $file
     * @param mixed $index
     * @return AbstractThread
     */
    abstract protected function createThread(Files $file, $index);

    /**
     * this is the logic of destroying a single thread
     * @param $index
     * @return bool thread is destroyed?
     */
    abstract protected function destroyThread($index);
}