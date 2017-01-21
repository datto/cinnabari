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

namespace Datto\Cinnabari\Mysql\Operators;

use Datto\Cinnabari\Mysql\Expression;

abstract class AbstractOperatorBinary extends Expression
{
    /** @var string */
    private $operator;

    /** @var Expression */
    private $left;

    /** @var Expression */
    private $right;

    public function __construct($operator, $left, $right)
    {
        $this->operator = $operator;
        $this->left = $left;
        $this->right = $right;
    }

    public function getMysql()
    {
        $leftMysql = $this->left->getMysql();
        $rightMysql = $this->right->getMysql();

        return "({$leftMysql} {$this->operator} {$rightMysql})";
    }

    public function getChildren()
    {
        return array($this->left, $this->right);
    }
}
