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
 * @author Anthony Liu <aliu@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Legacy\Compiler;

use Datto\Cinnabari\Legacy\Translator;
use Datto\Cinnabari\Legacy\Mysql\Identifier;
use Datto\Cinnabari\Legacy\Mysql\Statements\AbstractValuedStatement;

/**
 * Class AbstractValuedCompiler
 * @package Datto\Cinnabari
 */
abstract class AbstractValuedCompiler extends AbstractCompiler
{
    /** @var AbstractValuedStatement */
    protected $mysql;

    protected function enterTable()
    {
        $firstElement = array_shift($this->request);
        list(, $token) = each($firstElement);

        $this->context = $this->mysql->setTable($token['table']);

        return true;
    }

    protected function readList()
    {
        if (!self::scanList($this->request, $list)) {
            return false;
        }

        $initialContext = $this->context;
        foreach ($list as $index => $pair) {
            $this->context = $initialContext;
            $property = $pair['property'];
            $this->getColumnFromPropertyPath($property, $column, $columnType, $hasZero);

            $this->context = $initialContext;
            $value = $pair['value'];
            $this->getExpression($value, $hasZero, $expression, $expressionType);
            // TODO: require this to be a parameter

            $this->mysql->addPropertyValuePair($this->context, $column, $expression);
        }

        return true;
    }

    private function getColumnFromPropertyPath($arrayToken, &$column, &$type, &$hasZero)
    {
        $arrayToken = $this->followJoins($arrayToken);
        $propertyToken = reset($arrayToken);
        list(, $property) = each($propertyToken);

        $columnName = self::getFirstMysqlName($property['expression']);
        $column = new Identifier($columnName);

        $type = $property['type'];
        $hasZero = $property['hasZero'];

        return true;
    }

    private static function getFirstMysqlName($mysql)
    {
        if (preg_match('~`([a-zA-Z_0-9]+)`~', $mysql, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function scanList($input, &$object)
    {
        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_LIST) {
            return false;
        }

        $object = $token;
        return true;
    }
}
