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

namespace Datto\Cinnabari\Result\SIL\Statements;

use Datto\Cinnabari\Result\SIL\Expression;
use Datto\Cinnabari\Result\SIL\Table;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\Join;

abstract class AbstractStatement implements Expression
{
    /**
     * @var null|Table
     */
    private $table;

    /**
     * @var Join[]
     */
    private $joins;

    public function __construct($createAliasForTable)
    {
        $this->table = null;
        $this->joins = array();
    }

    public function addJoin(Join $join)
    {
        $this->joins[] = $join;
        return $join;
    }

    public function getJoins()
    {
        return $this->joins;
    }
}
