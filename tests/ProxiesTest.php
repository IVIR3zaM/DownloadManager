<?php
namespace IVIR3aM\DownloadManager\Tests;

use IVIR3aM\DownloadManager\Proxies;
use IVIR3aM\DownloadManager\Proxy;

/**
 * Class BasicArrayTest
 * @package IVIR3aM\ObjectArrayTools\Tests
 */
class ProxiesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Proxies
     */
    private $proxies;

    /**
     * @var Proxy
     */
    private $proxy;
    public function setUp()
    {
        $this->proxy = new Proxy('127.0.0.1', '8080');
        $this->proxies = new Proxies([$this->proxy]);
    }

    public function testInputOutput()
    {
        $this->assertEquals($this->proxy->getPort(), 8080);
        
        $this->proxies[] = ['127.0.0.1', '3128'];
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = '127.0.0.1::3128';
        $this->assertCount(2, $this->proxies);

        $proxy = $this->proxies->getProxy();
        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertEquals($proxy->getIp(), '127.0.0.1');
        $this->assertCount(1, $this->proxies);

        $newProxy = $this->proxies->getProxy();
        $this->assertNotEquals($proxy, $newProxy);
        $this->assertEquals($proxy->getIp(), '127.0.0.1');
        $this->assertCount(0, $this->proxies);

        $emptyProxy = $this->proxies->getProxy();
        $this->assertEquals(new Proxy('0.0.0.0', 0), $emptyProxy);

        $this->proxies->freeProxy($proxy);
        $this->assertCount(1, $this->proxies);

        $this->proxies->freeProxy($newProxy);
        $this->assertCount(2, $this->proxies);

        $this->proxies->freeProxy($emptyProxy);
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = $emptyProxy;
        $this->assertCount(3, $this->proxies);
        unset($this->proxies[2]);
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = $emptyProxy;
        $this->assertTrue($this->proxies->useProxy($emptyProxy));
        $this->assertCount(2, $this->proxies);
        $this->assertFalse($this->proxies->useProxy($emptyProxy));
    }

    public function testValidation()
    {
        $this->assertTrue(Proxy::isValid('0.0.0.0', 0));
        $this->assertFalse(Proxy::isValid('0.0.0.0', -1));
        $this->assertFalse(Proxy::isValid('0.0.0.0::25', -1));
    }

    public function testUsability()
    {
        $this->assertTrue($this->proxy->isUsable());
        $proxy = new Proxy('0.0.0.0', 0);
        $this->assertFalse($proxy->isUsable());
    }
}
