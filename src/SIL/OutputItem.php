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

class OutputItem
{
    /** @var string */
    private $name;

    private $value;

    private $alias;

    //private $datatype;

    /** @var Value[] */
    private $outputPath;

    /** @var bool */
    private $isList;

    /** @var bool */
    public $isNullable;

    public function __construct($name, $outputPath, Value $value, /*$datatype,*/ $isList, $isNullable)
    {
        $this->name = $name;
        $this->value = $value;
        $this->alias = null;
        //$this->datatype = $datatype;
        $this->outputPath = $outputPath;
        $this->isList = $isList;
        $this->isNullable = $isNullable;
    }

    public function getName()
    {
        return $this->name;
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

    /*
    public function getDatatype()
    {
        return $this->datatype;
    }*/

    public function getOutputPath()
    {
        return $this->outputPath;
    }

    public function getIsList()
    {
        return $this->isList;
    }

    public function getIsNullable()
    {
        return $this->isNullable;
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
