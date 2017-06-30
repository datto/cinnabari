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

namespace Datto\Cinnabari\Result\SIL\Statements\Clauses;

use Datto\Cinnabari\Result\SIL\Constant;
use Datto\Cinnabari\Result\SIL\Table;

class Join extends AbstractClause
{
    /** @var Table */
    private $table;

    private $alias;

    private $criterion;

    private $joinType;

    public function __construct(Table $table, $isInner = false)
    {
        $this->table = $table;
        $this->criterion = null;
        $this->joinType = ($isInner ? "INNER JOIN" : "LEFT JOIN");
        parent::__construct("", new Constant(''));
    }

    public function getMysql()
    {
        $criterionMysql = $this->criterion->getMysql();
        $alias = $this->getAlias();

        return $this->joinType
            . " " . $this->table->getMysql()
            . ($alias ? (" AS " . self::escape($alias)) : "")
            . " ON {$criterionMysql}";
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getAlias()
    {
        return $this->alias === null ? null : "{$this->alias}";
    }

    public function setCriterion($criterion)
    {
        $this->criterion = $criterion;
    }

    public function getCriterion()
    {
        return $this->criterion;
    }

    private static function escape($name)
    {
        return "`{$name}`";
    }
}
