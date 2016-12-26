<?php
namespace IVIR3aM\DownloadManager;

use IVIR3aM\ObjectArrayTools\AbstractActiveArray;

class Proxies extends AbstractActiveArray
{
    protected $used = [];
    protected function filterInputHook($offset, $data)
    {
        if (is_array($data)) {
            $ip = current($data);
            next($data);
            $port = current($data);
            return Proxy::isValid($ip, $port);
        } elseif (is_object($data) && is_a($data, Proxy::class)) {
            return Proxy::isValid($data->getIp(), $data->getPort());
        }
        return false;
    }

    protected function sanitizeInputHook($offset, $data)
    {
        if (is_array($data)) {
            $ip = current($data);
            next($data);
            $port = current($data);
            $data = new Proxy($ip, $port);
        }
        return $data;
    }

    public function getRandomProxy()
    {
        if (count($this) && ($key = $this->array_rand(1)) !== false) {
            $proxy = $this[$key];
            $this->useProxyByIndex($key);
        } else {
            $proxy = new Proxy();
        }

        return $proxy;
    }

    public function getProxyIndex(Proxy $proxy)
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

    public function useProxy(Proxy $proxy)
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

    public function freeProxy(Proxy $proxy)
    {
        $key = $this->getProxyIndex($proxy);
        if ($key !== false) {
            unset($this->used[$key]);
            $this[$key] = $proxy;
            return true;
        }
        return false;
    }
    
}