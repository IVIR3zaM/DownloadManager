<?php
namespace IVIR3aM\DownloadManager\Tests;

use IVIR3aM\DownloadManager\Proxies\Proxies;
use IVIR3aM\DownloadManager\Proxies\Stack;

/**
 * Class ProxiesTest
 * @package IVIR3aM\DownloadManager\Tests
 */
class ProxiesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Stack
     */
    private $proxies;

    /**
     * @var Proxies
     */
    private $proxy;
    public function setUp()
    {
        $this->proxy = new Proxies('127.0.0.1', '8080');
        $this->proxies = new Stack([$this->proxy]);
    }

    public function testInputOutput()
    {
        $this->assertEquals(8080, $this->proxy->getPort());
        
        $this->proxies[] = ['127.0.0.1', '3128'];
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = '127.0.0.1::3128';
        $this->assertCount(2, $this->proxies);

        $proxy = $this->proxies->getRandomProxy();
        $this->assertInstanceOf(Proxies::class, $proxy);
        $this->assertEquals('127.0.0.1', $proxy->getIp());

        $newProxy = $this->proxies->getRandomProxy();
        $this->assertNotEquals($proxy, $newProxy);
        $this->assertEquals('127.0.0.1', $proxy->getIp());
        $this->assertNotEquals($proxy->getPort(), $newProxy->getPort());
        $this->assertCount(2, $this->proxies);

        $index = $this->proxies->getProxyIndex($proxy);
        $this->assertNotFalse($index);
        $this->proxies[$index]->setPort(2222);
        $this->assertEquals(2222, $proxy->getPort());

        $emptyProxy = $this->proxies->getRandomProxy();
        $this->assertEquals(new Proxies('0.0.0.0', 0), $emptyProxy);
        $this->assertCount(2, $this->proxies);

        $this->proxies->freeProxy($proxy);
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = $emptyProxy;
        $this->assertCount(3, $this->proxies);
        unset($this->proxies[2]);
        $this->assertCount(2, $this->proxies);

        $this->proxies[] = $emptyProxy;
        $this->assertTrue($this->proxies->useProxy($emptyProxy));
        $this->assertFalse($this->proxies->useProxy($emptyProxy));
    }

    public function testValidation()
    {
        $this->assertTrue(Proxies::isValid('0.0.0.0', 0));
        $this->assertFalse(Proxies::isValid('0.0.0.0', -1));
        $this->assertFalse(Proxies::isValid('0.0.0.0::25', -1));
    }

    public function testUsability()
    {
        $this->assertTrue($this->proxy->isUsable());
        $proxy = new Proxies('0.0.0.0', 0);
        $this->assertFalse($proxy->isUsable());
    }
}
