<?php

namespace queasy\db\query;

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
    public function run(array $params = array(), array $options = array())
    {
        // Workaround for empty $params (for example when a record is inserted just to get an id and other columns can be NULL)
        if (empty($params)) {
            $query = new SingleInsertQuery($this->db(), $this->tableName());

            return $query->run(array(), $options);
        }

        $sql = sprintf('
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

        $this->setSql($sql);

        return parent::run($params, $options);
    }
}

