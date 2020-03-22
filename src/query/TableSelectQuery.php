<?php

namespace queasy\db\query;

use PDO;

class TableSelectQuery extends TableQuery
{
    public function __construct(PDO $db, $tableName, $fieldName)
    {
        parent::__construct($db, $tableName, sprintf('
            SELECT  *
            FROM    `%s`
            WHERE   `%s` = :%s', $tableName, $fieldName, $fieldName));
    }

    /**
     * Execute SQL query and return selected row or null.
     *
     * @param array $params Query parameters
     *
     * @return array|null Row or null if row does not exist
     *
     * @throws DbException On error
     */
    public function run(array $params = array())
    {
        return parent::run($params)->fetchAll();
    }
}
