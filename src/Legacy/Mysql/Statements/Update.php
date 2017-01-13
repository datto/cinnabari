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
 * @author Anthony Liu <aliu@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Legacy\Mysql\Statements;

use Datto\Cinnabari\Exception\CompilerException;
use Datto\Cinnabari\Legacy\Mysql\Expression;
use Datto\Cinnabari\Legacy\Mysql\Identifier;

class Update extends AbstractValuedStatement
{
    public function getMysql()
    {
        if (!$this->isValid()) {
            // TODO:
            throw CompilerException::invalidUpdate();
        }

        $mysql = "UPDATE\n" .
            $this->getTables() .
            $this->getColumnValuePairs() .
            $this->getWhereClause() .
            $this->getOrderByClause() .
            $this->getLimitClause();

        return rtrim($mysql, "\n");
    }

    public function setLimit(Expression $length)
    {
        $count = $length->getMysql();
        $mysql = "{$count}";

        $this->limit = $mysql;
    }

    // TODO: convert $columnIdentifier to $columnName (standardize on the names, not the MySQL markup)
    public function setOrderBy($tableId, $columnIdentifier, $isAscending)
    {
        $columnName = substr($columnIdentifier, 1, -1);
        $columnIdentifier = new Identifier($tableId, $columnName);
        $columnMysql = $columnIdentifier->getMysql();

        $mysql = "ORDER BY {$columnMysql}";

        if ($isAscending) {
            $mysql .= " ASC";
        } else {
            $mysql .= " DESC";
        }

        $this->orderBy = $mysql;
    }

    public function addPropertyValuePair($tableId, Expression $column, Expression $expression)
    {
        $columnMysql = $column->getMysql();

        $table = self::getIdentifier($tableId);
        $name = self::getAbsoluteExpression($table, $columnMysql);

        $this->values[$name] = $expression->getMysql();
        return self::insert($this->columns, $name);
    }

    protected function getColumnValuePairs()
    {
        $pairs = array();

        foreach ($this->columns as $column => $id) {
            $pairs[] = $column . ' = ' . $this->values[$column];
        }

        return "\tSET\n\t\t" . implode(",\n\t\t", $pairs) . "\n";
    }

    protected function getTables()
    {
        list($id, $table) = each($this->tables);

        $mysql = "\t" . self::getAliasedName($table, $id) . "\n";

        $tables = array_slice($this->tables, 1);

        foreach ($tables as $id => $joinJson) {
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

            $mysql .= "\t{$mysqlJoin} {$tableBIdentifier} AS {$joinIdentifier} ON {$expression}\n";
        }

        return $mysql;
    }

    protected static function getAliasedName($name, $id)
    {
        $alias = self::getIdentifier($id);
        return "{$name} AS {$alias}";
    }
}
