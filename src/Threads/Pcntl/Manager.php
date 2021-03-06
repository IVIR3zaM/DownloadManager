<?php
namespace IVIR3aM\DownloadManager\Threads\Pcntl;

use IVIR3aM\DownloadManager\Threads\AbstractManager;
use IVIR3aM\DownloadManager\Manager as DownloadManager;
use IVIR3aM\DownloadManager\Files\Files;
use IVIR3aM\DownloadManager\Threads\Exception;

class Manager extends AbstractManager
{
    private $pid = [];
    private $isParent = false;

    public function __construct(DownloadManager $manager, $data = array())
    {
        pcntl_signal(SIGCHLD, array($this, 'childEnd'));
        parent::__construct($manager, $data);
    }

    public function mustWait()
    {
        return count($this->pid);
    }

    public function createThread(Files $file, $index)
    {
        $thread = new Thread($file->getClient());
        $callback = $this->getManager()->getBeforeThreadHook();
        $callback(posix_getpid());
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception('Pcntl fork failed', 11);
        } else {
            if ($pid) {
                $callback = $this->getManager()->getParentHook();
                $callback($pid);
                $this->isParent = true;
                $this->pid[$index] = $pid;
                return $thread;
            } else {
                $callback = $this->getManager()->getChildHook();
                $callback(posix_getpid());
                $this->isParent = false;
                $code = 0;
                try {
                    $position = $file->getPosition();
                    $data = $thread->run();
                    $this->output($file, $data, $position);
                } catch (\Exception $e) {
                    $code = 3;
                }
                exit($code);
            }
        }
    }

    public function childEnd()
    {
        $pid = pcntl_waitpid(-1, $status, WNOHANG);
        while ($pid > 0) {
            $code = pcntl_wexitstatus($status);
            if (($index = array_search($pid, $this->pid)) !== false) {
                unset($this->pid[$index]);
                if ($code === 0) {
                    $this->successThread($index);
                } else {
                    $this->errorThread($index);
                }
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
    }

    public function destroyThread($index)
    {
        if (isset($this->pid[$index])) {
            posix_kill($this->pid[$index], SIGABRT);
            unset($this->pid[$index]);
        }
        return true;
    }

    public function __destruct()
    {
        if ($this->isParent) {
            foreach ($this->pid as $pid) {
                posix_kill($pid, SIGABRT);
            }
        }
    }
}