<?php

/**
 * Copyright (C) 2016 Datto, Inc.
 *
 * This file is part of Cinnabari.
 *
 * Cinnabari is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * Cinnabari is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Cinnabari. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <smortensen@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Mysql\Statements;

use Datto\Cinnabari\Exception\CompilerException;
use Datto\Cinnabari\Mysql\Expression;
use Datto\Cinnabari\Mysql\Table;

class Select extends Expression
{
    const ORDER_ASCENDING = 1;
    const ORDER_DESCENDING = 2;

    const JOIN_INNER = 'inner';
    const JOIN_LEFT = 'left';

    /** @var string[] */
    private $columns;

    /** @var Expression[]|Expression[] */
    private $tables;

    /** @var Expression */
    private $where;

    /** @var string */
    private $orderBy;

    /** @var string */
    private $limit;

    public function __construct()
    {
        $this->columns = array();
        $this->tables = array();
        $this->where = null;
        $this->orderBy = null;
        $this->limit = null;
    }

    /**
     * @param Expression|Expression $expression
     * Mysql abstract expression (e.g. new Table("`People`"))
     * Mysql abstract mysql (e.g. new Select())
     *
     * @return int
     * Numeric table identifier (e.g. 0)
     */
    public function setTable($expression)
    {
        $countTables = count($this->tables);

        if (0 < $countTables) {
            // TODO: use exceptions
            return null;
        }

        return self::appendOrFind($this->tables, $expression);
    }

    public function getTable($id)
    {
        $name = array_search($id, $this->tables, true);

        if (!is_string($name)) {
            throw CompilerException::badTableId($id);
        }

        if (0 < $id) {
            list(, $name) = json_decode($name);
        }

        return $name;
    }

    public function addExpression(Expression $expression)
    {
        $sql = $expression->getMysql();

        return self::insert($this->columns, $sql);
    }

    public function addValue($tableId, $column)
    {
        $table = self::getIdentifier($tableId);
        $name = self::getAbsoluteExpression($table, $column);

        return self::insert($this->columns, $name);
    }

    public function addJoin($tableAId, $tableBIdentifier, $mysqlExpression, $hasZero, $hasMany)
    {
        $tableAIdentifier = self::getIdentifier($tableAId);
        $joinType = ($hasZero || $hasMany) ? self::JOIN_LEFT : self::JOIN_INNER;
        $join = new Table(json_encode(array($tableAIdentifier, $tableBIdentifier, $mysqlExpression, $joinType)));
        return self::appendOrFind($this->tables, $join);
    }

    public function setWhere(Expression $expression)
    {
        $this->where = $expression;
    }

    public function setOrderBy(Expression $expression, $order)
    {
        $expressionMysql = $expression->getMysql();

        if ($order === self::ORDER_DESCENDING) {
            $direction = 'DESC';
        } else {
            $direction = 'ASC';
        }

        $this->orderBy = "ORDER BY {$expressionMysql} {$direction}";
    }

    public function setLimit(Expression $start, Expression $length)
    {
        $offset = $start->getMysql();
        $count = $length->getMysql();
        $mysql = "{$offset}, {$count}";

        $this->limit = $mysql;
    }

    public function getMysql()
    {
        if (!$this->isValid()) {
            // TODO:
            throw CompilerException::invalidSelect();
        }

        $mysql = "SELECT"
            . $this->getColumns()
            . $this->getTables()
            . $this->getWhereClause()
            . $this->getOrderByClause()
            . $this->getLimitClause();

        return rtrim($mysql, "\n");
    }

    private function isValid()
    {
        return ((0 < count($this->tables)) || isset($this->subquery)) && (0 < count($this->columns));
    }

    private function getColumns()
    {
        $columnNames = $this->getColumnNames();

        return "\n\t" . implode(",\n\t", $columnNames);
    }

    private function getColumnNames()
    {
        $columns = array();

        foreach ($this->columns as $name => $id) {
            $columns[] = self::getAliasedName($name, $id);
        }

        return $columns;
    }

    private function getTables()
    {
        $id = 0;
        $table = $this->tables[$id];

        $tableMysql = self::indentIfNeeded($table->getMysql());
        $mysql = "\n\tFROM " . self::getAliasedName($tableMysql, $id);

        for ($id = 1; $id < count($this->tables); $id++) {
            $joinJson = $this->tables[$id]->getMysql();
            list($tableAIdentifier, $tableBIdentifier, $expression, $type) = json_decode($joinJson, true);

            $joinIdentifier = self::getIdentifier($id);

            $splitExpression = explode(' ', $expression);
            $newExpression = array();
            $from = array('`0`', '`1`');
            $to = array($tableAIdentifier, $joinIdentifier);

            foreach ($splitExpression as $key => $token) {
                for ($i = 0; $i < count($from); $i++) {
                    $token = str_replace($from[$i], $to[$i], $token, $count);
                    if ($count > 0) {
                        break;
                    }
                }
                $newExpression[] = $token;
            }
            $expression = implode(' ', $newExpression);

            if ($type === self::JOIN_INNER) {
                $mysqlJoin = 'INNER JOIN';
            } else {
                $mysqlJoin = 'LEFT JOIN';
            }

            $mysql .= "\n\t{$mysqlJoin} {$tableBIdentifier} AS {$joinIdentifier} ON {$expression}";
        }

        return $mysql;
    }

    private static function indentIfNeeded($input)
    {
        if (strpos($input, "\n") !== false) {
            return "(\n" . self::indent(self::indent($input)) . "\n\t)";
        } else {
            return $input;
        }
    }

    private static function getAliasedName($name, $id)
    {
        $alias = self::getIdentifier($id);
        return "{$name} AS {$alias}";
    }

    public static function getAbsoluteExpression($context, $expression)
    {
        return preg_replace('~`.*?`~', "{$context}.\$0", $expression);
    }

    private static function getIdentifier($name)
    {
        return "`{$name}`";
    }

    private static function insert(&$array, $key)
    {
        $id = &$array[$key];

        if (!isset($id)) {
            $id = count($array) - 1;
        }

        return $id;
    }

    private static function appendOrFind(&$array, $value)
    {
        $index = array_search($value, $array);
        if ($index === false) {
            $index = count($array);
            $array[] = $value;
        }
        return $index;
    }

    private function getWhereClause()
    {
        if ($this->where === null) {
            return null;
        }

        $where = $this->where->getMysql();
        return "\tWHERE {$where}\n";
    }

    private function getOrderByClause()
    {
        if ($this->orderBy === null) {
            return null;
        }

        return "\t{$this->orderBy}\n";
    }

    private function getLimitClause()
    {
        if ($this->limit === null) {
            return null;
        }

        return "\tLIMIT {$this->limit}\n";
    }

    private static function indent($string)
    {
        return "\t" . preg_replace('~\n(?!\n)~', "\n\t", $string);
    }
}
