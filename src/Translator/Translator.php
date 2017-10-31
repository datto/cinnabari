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

namespace Datto\Cinnabari\Translator;

use Datto\Cinnabari\AbstractRequest\Node;
use Datto\Cinnabari\Translator\Nodes\Node as DatabaseNode;
use Datto\Cinnabari\Translator\Nodes\Value;
use Datto\Cinnabari\Translator\Parser\Parser;
use Datto\Cinnabari\Translator\Parser\Rules;

/**
 * expression = read
 * read = average | count | map | max | min | sum
 * average = <array>
 * count = <array>
 * map = <array, MAP-EXPRESSION>
 * max = <array>
 * min = <array>
 * sum = <array>
 * length = <array, string>
 * match = <string, string>
 * substring = <string, numeric, numeric>
 * lowercase = <string>
 * uppercase = <string>
 * times = <numeric, numeric>
 * divides = <numeric, numeric>
 * plus = <numeric, numeric> | <string, string>
 * minus = <numeric, numeric>
 *
 * numeric = times | divides | plus | minus | length | property | :parameter
 * boolean = less | lessEqual | equal | notEqual | greaterEqual | greater | match | not | and | or | boolean-property | :parameter
 * string = lowercase | uppercase | substring | plus | property | :parameter
 * array = filter | sort | slice | property
 * filter = <array, boolean>
 * sort = <array, boolean | numeric | string>
 * slice = <array, numeric, numeric>
 */
/**
 * delete = <array>
 * insert = <array, object>
 * set = <array, object>
 */
class Translator extends Parser
{
    public function __construct(Map $map)
    {
        $grammar = <<<'EOS'
count: FUNCTION count array
array: OR arrayProperty sort
arrayProperty: PROPERTY
sort: FUNCTION sort array column
column: PROPERTY
EOS;

        $rules = new Rules($this, $grammar);
        $rule = $rules->getRule('count');

        parent::__construct($rule, $map);
    }

    public function translate(Node $node)
    {
        return $this->parse($node);
    }

    public function getCount(DatabaseNode $context)
    {
        $id = $context->getId();
        $value = new Value("COUNT({$id})");

        $this->select->add($this->context, $value);
    }

    public function getArrayProperty(array $databaseNodes)
    {
        $databaseNode = null;

        foreach ($databaseNodes as $databaseNode) {
            $this->context = $this->select->add($this->context, $databaseNode);
        }

        return $databaseNode;
    }

    public function getSort(DatabaseNode $context, Value $value)
    {
        $expression = $value->getExpression();

        $this->select->orderBy($this->context, $expression, true);

        return $context;
    }

    public function getColumn(array $databaseNodes)
    {
        foreach ($databaseNodes as $databaseNode) { /** @var DatabaseNode $databaseNode */
            $type = $databaseNode->getNodeType();

            if ($type === DatabaseNode::TYPE_VALUE) {
                return $databaseNode;
            }

            $this->context = $this->select->add($this->context, $databaseNode);
        }

        return null;
    }
}
