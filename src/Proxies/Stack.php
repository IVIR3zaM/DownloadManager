<?php
namespace IVIR3aM\DownloadManager\Proxies;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;

class Stack extends AbstractActiveArray
{
    protected $used = [];
    protected function filterInputHook($offset, $data)
    {
        if (is_array($data)) {
            $ip = current($data);
            next($data);
            $port = current($data);
            return Proxies::isValid($ip, $port);
        } elseif (is_object($data) && is_a($data, Proxies::class)) {
            return Proxies::isValid($data->getIp(), $data->getPort());
        }
        return false;
    }

    protected function removeHook($offset)
    {
        unset($this->used[$offset]);
    }

    protected function sanitizeInputHook($offset, $data)
    {
        if (is_array($data)) {
            $ip = current($data);
            next($data);
            $port = current($data);
            $data = new Proxies($ip, $port);
        }
        return $data;
    }

    public function getRandomProxy()
    {
        if (count($this) && ($key = $this->array_rand(1)) !== false) {
            $proxy = $this[$key];
            $this->useProxyByIndex($key);
        } else {
            $proxy = new Proxies();
        }

        return $proxy;
    }

    public function getProxyIndex(Proxies $proxy)
    {
        foreach ($this->used as $key => $object) {
            if ($proxy == $object) {
                return $key;
            }
        }

        foreach ($this as $key => $object) {
            if ($proxy == $object) {
                return $key;
            }
        }
        return false;
    }

    public function useProxy(Proxies $proxy)
    {
        $key = $this->getProxyIndex($proxy);
        return $key !== false ? $this->useProxyByIndex($key) : false;
    }

    public function useProxyByIndex($index)
    {
        if (isset($this[$index]) && !isset($this->used[$index])) {
            $proxy = $this[$index];
            unset($this[$index]);
            $this->used[$index] = $proxy;
            return true;
        }
        return false;
    }

    public function freeProxy(Proxies $proxy)
    {
        $key = $this->getProxyIndex($proxy);
        if ($key !== false) {
            unset($this->used[$key]);
            if ($proxy->isUsable()) {
                $this[$key] = $proxy;
            }
            return true;
        }
        return false;
    }
    
}