<?php

namespace queasy\db\query;

use queasy\db\Db;
use queasy\db\DbException;

class SingleNamedInsertQuery extends TableQuery
{
    /**
     * Execute INSERT query with named parameters.
     *
     * @param array $params Query parameters (key - value array)
     *
     * @return int Insert id generated by database
     *
     * @throws DbException On error
     */
    public function run(array $params = array())
    {
        // Workaround for empty $params (for example when a record is inserted just to get an id and other columns can be NULL)
        if (empty($params)) {
            $query = new SingleInsertQuery($this->db(), $this->tableName());

            return $query->run();
        }

        $query = sprintf('
            INSERT  INTO `%s` (%s)
            VALUES  (%s)',
            $this->tableName(),
            implode(', ',
                array_map(function($paramName) {
                    return '`' . $paramName . '`';
                },  array_keys($params))
            ),
            implode(', ',
                array_map(function($paramName) {
                    return ':' . $paramName;
                },  array_keys($params))
            )
        );

        $this->setQuery($query);

        parent::run($params);

        return $this->db()->id();
    }
}

