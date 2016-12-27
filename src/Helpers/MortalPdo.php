<?php
namespace IVIR3aM\DownloadManager\Helpers;

use PDO;
use PDOStatement;
use Iterator;
use Countable;

class MortalPdo implements Iterator, Countable
{
    private $list = [];
    private $driver;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $options;

    public function __construct($host, $dbname, $username, $password, $driver = 'mysql', $options = array())
    {
        $this->driver = $driver;
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    private function getPdo()
    {
        return new PDO("{$this->driver}:dbname={$this->dbname};host={$this->host}",
            $this->username, $this->password, $this->options);
    }

    /**
     * @param PDO $pdo
     * @param $query
     * @return PDOStatement
     */
    private function query(PDO $pdo, $query, $values)
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
        $result = $this->query($this->getPdo(), $query, $values);
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
        $pdo = $this->getPdo();
        $this->query($pdo, $query, $values);
        $id = $pdo->lastInsertId();
        unset($pdo);
        return $id;
    }

    public function update($query, $values = null)
    {
        $result = $this->query($this->getPdo(), $query, $values);
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