<?php

/**
 * Copyright (C) 2017 Datto, Inc.
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
 * @copyright 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\Result\SIL;

class Value implements Expression
{
    private $value;

    private $isNullable;

    private $isList;

    private $alias;

    public function __construct(Expression $value, $isNullable = false, $isList = false)
    {
        $this->value = $value;
        $this->isNullable = $isNullable;
        $this->alias = null;
        $this->isList = $isList;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getAlias()
    {
        return $this->alias === null ? null : "{$this->alias}";
    }

    public function getIsNullable()
    {
        return $this->isNullable;
    }

    public function getIsList()
    {
        return $this->isList;
    }

    public function getMysql($printValue = false, $printAlias = true)
    {
        $ret = "";

        if ($printValue) {
            $ret .= $this->value->getMysql();
        }

        if ($printAlias && $this->alias !== null) {
            if ($printValue) {
                $ret .= " AS ";
            }

            $ret .= $this->escape($this->alias);
        }

        return $ret;
    }

    protected static function escape($name)
    {
        return "`{$name}`";
    }
}
