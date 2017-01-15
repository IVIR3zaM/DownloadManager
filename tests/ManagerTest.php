<?php
namespace IVIR3aM\DownloadManager\Tests;

use IVIR3aM\DownloadManager\Manager;

/**
 * Class ManagerTest
 * @package IVIR3aM\DownloadManager\Tests
 */
class ManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Manager
     */
    private $manager;

    public function setUp()
    {
        $this->manager = new Manager();
    }

    public function testWorkRules()
    {
        $this->manager->setWorkDirect(true);
        $direct = $this->manager->getWorkDirect();
        $this->assertTrue($this->manager->getWorkDirect());

        $this->manager->setWorkDirect(false);
        $this->assertFalse($this->manager->getWorkDirect());

        $this->manager->setWorkProxy(true);
        $this->assertTrue($this->manager->getWorkProxy());

        $this->manager->setWorkProxy(false);
        $this->assertFalse($this->manager->getWorkProxy());

        $this->manager->setWorkRule(Manager::WORK_NONE);
        $this->assertFalse($this->manager->getWorkProxy());
        $this->assertFalse($this->manager->getWorkDirect());

        $this->manager->setWorkRule(Manager::WORK_ALL);
        $this->assertTrue($this->manager->getWorkProxy());
        $this->assertTrue($this->manager->getWorkDirect());
    }
}
