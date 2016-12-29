<?php
namespace IVIR3aM\DownloadManager\Helpers;

use PDO;
use PDOStatement;
use PDOException;
use Iterator;
use Countable;

class MortalPdo implements Iterator, Countable
{
    protected $list = [];
    protected $readDriver;
    protected $readHost;
    protected $readDbname;
    protected $readUsername;
    protected $readPassword;
    protected $readOptions;
    protected $writeDriver;
    protected $writeHost;
    protected $writeDbname;
    protected $writeUsername;
    protected $writePassword;
    protected $writeOptions;
    const READ = 0;
    const WRITE = 1;

    public function __construct($host, $dbname, $username, $password, $driver = 'mysql', $options = array())
    {
        $this->readDriver = $this->writeDriver = $driver;
        $this->readHost = $this->writeHost = $host;
        $this->readDbname = $this->writeDbname = $dbname;
        $this->readUsername = $this->writeUsername = $username;
        $this->readPassword = $this->writePassword = $password;
        $this->readOptions = $this->writeOptions = $options;
    }

    public function setReadConnection($host, $dbname, $username, $password, $driver = 'mysql', $options = array())
    {
        $this->readDriver = $driver;
        $this->readHost = $host;
        $this->readDbname = $dbname;
        $this->readUsername = $username;
        $this->readPassword = $password;
        $this->readOptions = $options;
    }

    public function setWriteConnection($host, $dbname, $username, $password, $driver = 'mysql', $options = array())
    {
        $this->writeDriver = $driver;
        $this->writeHost = $host;
        $this->writeDbname = $dbname;
        $this->writeUsername = $username;
        $this->writePassword = $password;
        $this->writeOptions = $options;
    }

    protected function getPdo($state = self::READ)
    {
        try {
            return $state == self::WRITE ?
                new PDO("{$this->writeDriver}:dbname={$this->writeDbname};host={$this->writeHost}",
                    $this->writeUsername, $this->writePassword, $this->writeOptions) :
                new PDO("{$this->readDriver}:dbname={$this->readDbname};host={$this->readHost}",
                    $this->readUsername, $this->readPassword, $this->readOptions);
        } catch(PDOException $e) {
            sleep(1);
            return $this->getPdo($state);
        }
    }

    /**
     * @param PDO $pdo
     * @param $query
     * @return PDOStatement
     */
    protected function query(PDO $pdo, $query, $values)
    {
        if (is_array($values) && !empty($values)) {
            $result = $pdo->prepare($query);
            $result->execute($values);
        } else {
            $result = $pdo->query($query);
        }
        return $result;
    }

    public function select($query, $values = null, $object = true)
    {
        $result = $this->query($this->getPdo(self::READ), $query, $values);
        $this->list = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $this->list[] = $object ? (object)$row : $row;
        }
        unset($result, $pdo);
        return $this->list;
    }

    public function selectOne($query, $values = null, $object = true)
    {
        $this->select($query, $values, $object);
        return empty($this->list) ? false : current($this->list);
    }

    public function countQuery($query, $values = null)
    {
        $count = 0;
        $c = $this->select($query, $values, false);
        if ($c) {
            $count = current($c);
        }
        return $count;
    }

    public function insert($query, $values = null)
    {
        $pdo = $this->getPdo(self::WRITE);
        $this->query($pdo, $query, $values);
        $id = $pdo->lastInsertId();
        unset($pdo);
        return $id;
    }

    public function update($query, $values = null)
    {
        $result = $this->query($this->getPdo(self::WRITE), $query, $values);
        $count = $result->rowCount();
        unset($result, $pdo);
        return $count;
    }

    public function delete($query, $values = null)
    {
        return $this->update($query, $values);
    }

    public function getData()
    {
        return $this->list;
    }

    public function count()
    {
        return count($this->list);
    }

    public function rewind()
    {
        reset($this->list);
    }

    public function current()
    {
        return current($this->list);
    }

    public function key()
    {
        return key($this->list);
    }

    public function next()
    {
        return next($this->list);
    }

    public function valid()
    {
        return !is_null(key($this->list));
    }
}