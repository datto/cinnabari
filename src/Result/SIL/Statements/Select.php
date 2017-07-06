<?php

/**
 * Copyright (C) 2016, 2017 Datto, Inc.
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
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\Result\SIL\Statements;

use Datto\Cinnabari\Exception;
use Datto\Cinnabari\Result\SIL\Expression;
use Datto\Cinnabari\Result\SIL\Value;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\From;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\Limit;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\Where;

class Select extends AbstractStatement
{
    /** @var Expression */
    private $where;

    /** @var null|Expression */
    private $groupBy;

    /** @var null|Expression */
    private $having;

    /** @var null|Expression */
    private $orderBy;

    /** @var null|Expression */
    private $limit;

    /** @var array */
    private $valueNames;

    /** @var Value[] */
    private $values;

    /** @var null|From */
    private $from;

    public function __construct()
    {
        $this->where = null;
        $this->groupBy = null;
        $this->having = null;
        $this->orderBy = null;
        $this->limit = null;
        $this->values = array();
        $this->valueNames = array();
        $this->from = null;
        parent::__construct(true);
    }

    public function setFrom(From $from)
    {
        if ($this->from) {
            throw Exception::internalError("Select: multiple froms");
        }

        return $this->from = $from;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function setWhere(Where $where)
    {
        return $this->where = $where;
    }

    public function setGroupBy(Expression $groupBy)
    {
        if ($this->groupBy) {
            throw Exception::internalError("Select: multiple groups");
        }
        $this->groupBy = $groupBy;
    }

    public function setHaving(Expression $having)
    {
        if ($this->having) {
            throw Exception::internalError("Select: multiple havings");
        }
        $this->having = $having;
    }

    public function setOrderBy(Expression $orderBy)
    {
        if ($this->orderBy) {
            throw Exception::internalError("Select: multiple orders");
        }
        return $this->orderBy = $orderBy;
    }

    public function setLimit(Limit $limit)
    {
        if ($this->limit) {
            throw Exception::internalError("Select: multiple limits");
        }
        return $this->limit = $limit;
    }

    public function addValue(Value $value)
    {
        return $this->values[] = $value;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function getMysql()
    {
        if ($this->getFrom() === null) {
            throw Exception::internalError("Select: no from");
        }

        if (count($this->values) === 0) {
            throw Exception::internalError("Select: no values");
        }

        $parts = array();

        /** @var Value $value */
        foreach ($this->values as $value) {
            if (!$value->getIsList()) {
                $parts[] = $value->getMysql(true, true);
            }
        }

        $parts[] = $this->getFrom()->getMysql();

        if (count($this->getJoins()) > 0) {
            $parts[] = implode("\n\t", array_map('self::getExpressionMysql', $this->getJoins()));
        }

        if ($this->where !== null) {
            $parts[] = $this->where->getMysql();
        }

        if ($this->groupBy !== null) {
            $parts[] = $this->groupBy->getMysql();
        }

        if ($this->having !== null) {
            $parts[] = $this->having->getMysql();
        }

        if ($this->orderBy !== null) {
            $parts[] = $this->orderBy->getMysql();
        }

        if ($this->limit !== null) {
            $parts[] = $this->limit->getMysql();
        }

        return "SELECT\n\t" . implode("\n\t", $parts);
    }

    protected static function getExpressionMysql(Expression $expression)
    {
        return $expression->getMysql();
    }

    protected static function getValueMysql(Value $value)
    {
        return $value->getMysql(true, true);
    }
}
