<?php

namespace queasy\db;

use PDO;
use ArrayAccess;

use queasy\config\ConfigInterface;
use queasy\config\ConfigAwareTrait;

use queasy\db\query\SingleInsertQuery;
use queasy\db\query\SingleNamedInsertQuery;
use queasy\db\query\BatchInsertQuery;
use queasy\db\query\BatchNamedInsertQuery;

class Table implements ArrayAccess
{
    use ConfigAwareTrait;

    private $db;

    private $name;

    private $fields;

    public function __construct(PDO $db, $name)
    {
        $this->db = $db;
        $this->name = $name;
        $this->fields = array();
    }

    public function __get($fieldName)
    {
        return $this[$fieldName];
    }

    public function offsetExists($offset)
    {
        return true;
    }

    public function offsetGet($offset)
    {
        if (!isset($this->fields[$offset])) {
            $this->fields[$offset] = new Field($this->name, $offset);
        }

        return $this->fields[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($value)) {
            throw new DbException('Cannot assign null to table field.');
        } elseif (is_array($value)) {
            $keys = array_keys($value);
            if (count($keys) && is_array($value[$keys[0]])) { // Batch inserts
                if ((2 === count($value))
                        && is_array($value[1])
                        && (0 < count($value[1]))
                        && isset($value[1][0])
                        && is_array($value[1][0])) { // Batch insert with field names listed in a separate array
                    $query = new BatchSeparatelyNamedInsertQuery($this->db, $this->name);
                } else {
                    $keys = array_keys($value[$keys[0]]);
                    if (!count($keys) || is_numeric($keys[0])) { // Batch insert
                        $query = new BatchInsertQuery($this->db, $this->name);
                    } else { // Batch insert with field names
                        $query = new BatchNamedInsertQuery($this->db, $this->name);
                    }
                }
            } else { // Single inserts
                if (!count($keys) || is_numeric($keys[0])) { // By order, without field names
                    $query = new SingleInsertQuery($this->db, $this->name);
                } else { // By field names
                    $query = new SingleNamedInsertQuery($this->db, $this->name);
                }
            }
        } else {
            throw new DbException('Invalid assignment type (must be array).');
        }

        return $query->run($value);
    }

    public function offsetUnset($offset)
    {
        throw new Exception('Cannot unset table field.');
    }

    /**
     * Calls an user-defined (in configuration) method
     *
     * @param string $method Method name
     * @param array $args Arguments
     *
     * @return mixed Return type depends on configuration. It can be a single value, an object, an array, or an array of objects or arrays
     *
     * @throws DbException On error
     */
    public function __call($method, array $args)
    {
        if (isset($this->config[$method])) {
            $query = $this->config[$method]['query'];

            $this->db->execute(array_merge(array($query), $args));
        } else {
            throw DbException::tableMethodNotImplemented($this->name, $method);
        }
    }
}

