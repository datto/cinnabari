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

class Join extends Node
{
    /** @var integer */
    private $context;

    /** @var string */
    private $table;

    /** @var integer */
    private $id;

    /** @var string */
    private $condition;

    /** @var boolean */
    private $hasZero;

    /** @var boolean */
    private $hasMany;

    public function __construct($table, $id, $condition, $hasZero, $hasMany)
    {
        parent::__construct(Node::TYPE_JOIN);

        $this->table = $table;
        $this->id = $id;
        $this->condition = $condition;
        $this->hasZero = $hasZero;
        $this->hasMany = $hasMany;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function setContext($context)
    {
        $this->context = $context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function hasZero()
    {
        return $this->hasZero;
    }

    public function hasMany()
    {
        return $this->hasMany;
    }

    public function getState()
    {
        return array(
            $this->context,
            $this->table,
            $this->id,
            $this->condition
        );
    }
}
