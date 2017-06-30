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

class Parameter implements Expression
{
    /** @var string */
    private $name;

    private $value;

    private $alias;

    private $isSynthetic;

    public function __construct($name, $isSynthetic = false)
    {
        $this->name = $name;
        $this->value = $name;
        $this->alias = $name;
        $this->isSynthetic = $isSynthetic;
    }

    public function setPhpValue($expression)
    {
        $this->value = $expression;
    }

    public function getPhpValue()
    {
        return $this->value;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getAlias()
    {
        return ":{$this->alias}";
    }

    public function getMysql()
    {
        return ":{$this->alias}";
    }

    public function getIsSynthetic()
    {
        return $this->isSynthetic;
    }

    public function getName()
    {
        return $this->name;
    }
}
