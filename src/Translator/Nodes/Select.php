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

namespace Datto\Cinnabari\Translator\Nodes;

use Datto\Cinnabari\Translator\Aliases;
use Datto\Cinnabari\Translator\Clauses\OrderBy;

class Select extends Node
{
    /** @var array */
    private $tables;

    /** @var array */
    private $values;

    /** @var array */
    private $clauses;

    /** @var Aliases */
    private $tableAliases;

    /** @var Aliases */
    private $valueAliases;

    public function __construct()
    {
        parent::__construct(Node::TYPE_SELECT);

        $this->tables = array();
        $this->values = array();
        $this->clauses = array();

        $this->tableAliases = new Aliases();
        $this->valueAliases = new Aliases();
    }

    public function add(&$context, Node $node)
    {
        $type = $node->getNodeType();

        switch ($type) {
            default: // Node::TYPE_SELECT:
                return null;

            case Node::TYPE_TABLE:
                /** @var Table $node */
                return $this->addTable($node);

            case Node::TYPE_JOIN:
                /** @var Join $node */
                return $this->addJoin($context, $node);

            case Node::TYPE_VALUE:
                /** @var Value $node */
                return $this->addValue($context, $node);
        }
    }

    private function addTable(Table $table)
    {
        return $this->addTableNode($table);
    }

    private function addJoin($context, Join $join)
    {
        $join->setContext($context);

        return $this->addTableNode($join);
    }

    private function addValue($context, Value $value)
    {
        $value->setContext($context);

        $this->addValueNode($value);

        return $context;
    }

    private function addTableNode(Node $node)
    {
        $state = $node->getState();
        $alias = $this->tableAliases->getAlias($state);

        $this->tables[$alias] = $node;
        return $alias;
    }

    private function addValueNode(Node $node)
    {
        $state = $node->getState();
        $alias = $this->valueAliases->getAlias($state);

        $this->values[$alias] = $node;
        return $alias;
    }

    public function orderBy($context, $expression, $isAscending)
    {
        $this->clauses[] = new OrderBy($context, $expression, $isAscending);
    }

    public function getTables()
    {
        return $this->tables;
    }

    public function getTable($alias)
    {
        $table = &$this->tables[$alias];

        return $table;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function getClauses()
    {
        return $this->clauses;
    }

    public function getState()
    {
        return null;
    }
}
