<?php

namespace queasy\db\query;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use queasy\db\Db;
use queasy\db\DbException;

abstract class AbstractQuery implements QueryInterface
{
    use LoggerAwareTrait;

    private $db;

    private $query;

    private $statement;

    /**
     * Constructor.
     *
     * @param string $query Query string
     *
     * @throws DbException When query can't be prepared
     */
    public function __construct(Db $db, $query = null)
    {
        $this->db = $db;
        $this->query = $query;
    }

    abstract public function run(array $params = array());

    public function statement()
    {
        if (!$this->statement) {
            try {
                $this->statement = $this->db()->prepare($this->query());
            } catch (Exception $e) {
                throw DbException::cannotPrepareStatement($query, $e);
            }
        }

        return $this->statement;
    }

    protected function db()
    {
        return $this->db;
    }

    protected function query()
    {
        if (empty($this->query)) {
            throw new DbException('Query is not set.');
        }

        return $this->query;
    }

    protected function setQuery($query)
    {
        $this->query = $query;
    }

    protected function logger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }
}

