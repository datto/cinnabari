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

use Datto\Cinnabari\Result\SIL\Statements\AbstractStatement;

/**
 * Class SIL - The (my)Sql Intermediate Language
 *
 * @package Datto\Cinnabari\Result\SIL
 */
class SIL
{
    /** @var Parameter[] */
    private $parameters;

    /** @var AbstractStatement[] */
    private $statements;

    /** @var OutputItem[] */
    private $outputItems;

    public function __construct()
    {
        $this->parameters = array();
        $this->statements = array();
        $this->outputItems = array();
    }

    public function getMysqlStatements()
    {
        $mysqls = array();

        /** @var $statement Expression */
        foreach ($this->statements as $statement) {
            $mysqls[] = $statement->getMysql();
        }

        return $mysqls;
    }

    public function addParameter(Parameter $parameter)
    {
        $this->parameters[] = $parameter;
        return $parameter;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function addStatement($statement)
    {
        $this->statements[] = $statement;
        return $statement;
    }

    public function getStatements()
    {
        return $this->statements;
    }

    public function addOutputItem($outputItem)
    {
        $this->outputItems[] = $outputItem;
    }

    /**
     * @return OutputItem[]
     */
    public function getOutputItems()
    {
        return $this->outputItems;
    }
}
