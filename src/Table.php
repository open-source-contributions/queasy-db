<?php

namespace queasy\db;

use BadMethodCallException;

use PDO;
use ArrayAccess;
use Countable;
use Iterator;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

use queasy\db\query\CustomQuery;
use queasy\db\query\CountQuery;
use queasy\db\query\SelectQuery;
use queasy\db\query\UpdateQuery;
use queasy\db\query\DeleteQuery;

class Table implements ArrayAccess, Countable, Iterator, LoggerAwareInterface
{
    protected $pdo;

    protected $name;

    protected $fields;

    protected $rows;

    /**
     * Config instance.
     *
     * @var array|ArrayAccess
     */
    protected $config;

    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(PDO $pdo, $name, $config = array())
    {
        $this->logger = new NullLogger();

        $this->pdo = $pdo;
        $this->name = $name;
        $this->fields = array();
        $this->rows = null;
        $this->config = $config;
    }

    public function __get($fieldName)
    {
        return $this[$fieldName];
    }

    public function count()
    {
        $query = new CountQuery($this->pdo, $this->name);
        $query->setLogger($this->logger);

        $statement = $query();

        $row = $statement->fetch();

        return array_shift($row);
    }

    public function current()
    {
        return current($this->rows);
    }

    public function key()
    {
        return key($this->rows);
    }

    public function next()
    {
        return next($this->rows);
    }

    public function rewind()
    {
        $query = new SelectQuery($this->pdo, $this->name);

        $statement = $query();

        $this->rows = $statement->fetchAll();
    }

    public function valid()
    {
        return isset($this->rows[$this->key()]);
    }

    public function insert()
    {
        $params = (1 === func_num_args())
            ? func_get_arg(0)
            : func_get_args();
        if ((null === $params) || !is_array($params)) {
            throw new InvalidArgumentException('Wrong rows argument.');
        }

        $isSingleInsert = true;

        $keys = array_keys($params);
        if (count($keys) && is_array($params[$keys[0]])) { // Batch inserts
            $isSingleInsert = false;
            if (!((2 === count($params))
                    && is_array($params[1])
                    && (0 < count($params[1]))
                    && isset($params[1][0])
                    && is_array($params[1][0]))) { // Batch insert with field names listed in a separate array
                $keys = array_keys($params[$keys[0]]);

                $queryClass = (!count($keys) || is_numeric($keys[0]))
                    ? 'queasy\\db\\query\\BatchInsertQuery' // Batch insert
                    : 'queasy\\db\\query\\BatchNamedInsertQuery'; // Batch insert with field names
            } else {
                $queryClass = 'queasy\\db\\query\\BatchSeparatelyNamedInsertQuery';
            }
        } else { // Single inserts
            $queryClass = (!count($keys) || is_numeric($keys[0]))
                ? 'queasy\\db\\query\\SingleInsertQuery' // By order, without field names
                : 'queasy\\db\\query\\SingleNamedInsertQuery'; // By field names
        }

        $query = new $queryClass($this->pdo, $this->name);
        $query->setLogger($this->logger);

        $statement = $query($params);

        return $isSingleInsert
            ? $this->pdo->lastInsertId()
            : $statement->rowCount();
    }

    public function update(array $params, $fieldName = null, $fieldValue = null, array $options = array())
    {
        $query = new UpdateQuery($this->pdo, $this->name, $fieldName, $fieldValue);
        $query->setLogger($this->logger);

        $statement = $query($params, $options);

        return $statement->rowCount();
    }

    public function delete($fieldName = null, $fieldValue = null, array $options = array())
    {
        $query = new DeleteQuery($this->pdo, $this->name, $fieldName, $fieldValue);
        $query->setLogger($this->logger);

        $statement = $query(array(), $options);

        return $statement->rowCount();
    }

    public function offsetExists($offset)
    {
        throw new BadMethodCallException(sprintf('Not implemented.', $offset));
    }

    public function offsetGet($offset)
    {
        if (!isset($this->fields[$offset])) {
            $field = new Field($this->pdo, $this, $offset);
            $field->setLogger($this->logger);

            $this->fields[$offset] = $field;
        }

        return $this->fields[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (null !== $offset) {
            throw new BadMethodCallException('Not implemented. Use Field instead of Table to update record.');
        }

        $this->insert($value);
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException(sprintf('Not implemented.', $offset));
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
            $query = new CustomQuery($this->pdo, $this->config[$method]);
            $query->setLogger($this->logger);

            return call_user_func_array(array($query, 'run'), $args);
        }

        $field = $this[$method];

        return $field($args[0]);
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getName()
    {
        return $this->name;
    }
}

