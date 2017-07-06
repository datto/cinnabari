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
use Datto\Cinnabari\Result\SIL\Statements\Clauses\Limit;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\OrderBy;
use Datto\Cinnabari\Result\SIL\Statements\Clauses\Where;

class Delete extends AbstractStatement
{
    /** @var null|Expression */
    private $where;

    /** @var null|Expression */
    private $orderBy;

    /** @var null|Expression */
    private $limit;

    public function __construct()
    {
        $this->where = null;
        $this->orderBy = null;
        $this->limit = null;
        parent::__construct(false);
    }

    public function setWhere(Expression $where)
    {
        $this->where = new Where($where);
    }

    public function setOrderBy(Expression $orderBy)
    {
        $this->orderBy = new OrderBy($orderBy);
    }

    public function setLimit(Expression $begin, Expression $end)
    {
        $this->limit = new Limit($begin, $end);
    }

    public function getMysql()
    {
        if ($this->getTable() === null) {
            throw Exception::internalError("Delete: multiple froms");
        }

        $parts[] = $this->getTable()->getMysql();

        if ($this->where !== null) {
            $parts[] = $this->where->getMysql();
        }

        if ($this->orderBy !== null) {
            $parts[] = $this->orderBy->getMysql();
        }

        if ($this->limit !== null) {
            $parts[] = $this->limit->getMysql();
        }

        return "(DELETE " . implode(' ', $parts) . ")";
    }
}
