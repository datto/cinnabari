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
 * @author Mark Greeley mgreeley@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\Result\SIL\Statements;

use Datto\Cinnabari\Exception;
use Datto\Cinnabari\Result\SIL\Expression;

class Update extends AbstractStatement
{
    /** @var null|Expression */
    private $table;

    /** @var array */
    private $values;

    /** @var array */
    private $joins;

    public function __construct()
    {
        $this->table = null;
        $this->values = array();
        $this->joins = array();
        parent::__construct(false);
    }

    /**
     * @param string     $key
     * @param Expression $value
     */
    public function addKeyValue($key, Expression $value)
    {
        $this->values[$key] = $value;
    }

    public function getMysql()
    {
        if ($this->table === null) {
            throw Exception::internalError("Update: no table");
        }

        if (count($this->values) === 0) {
            throw Exception::internalError("Update: no values");
        }

        $parts = array();
        $parts[] = $this->table->getMysql();

        $needComma = false;
        foreach ($this->values as $key => $val) {
            $parts[] = ($needComma ? ', ' : '') . $key . '=' . self::getExpressionMysql($val);
            $needComma = true;
        }

        return "(UPDATE " . implode(' ', $parts) . ")";
    }

    protected static function getExpressionMysql(Expression $expression)
    {
        return $expression->getMysql();
    }
}
