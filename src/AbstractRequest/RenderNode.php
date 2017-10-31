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
 * @author Griffin Bishop <gbishop@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\AbstractRequest;

use Datto\Cinnabari\AbstractRequest\Nodes\FunctionNode;
use Datto\Cinnabari\AbstractRequest\Nodes\ObjectNode;
use Datto\Cinnabari\AbstractRequest\Nodes\OperatorNode;
use Datto\Cinnabari\AbstractRequest\Nodes\ParameterNode;
use Datto\Cinnabari\AbstractRequest\Nodes\PropertyNode;

class RenderNode
{
    /**
     * Produces a string representation of the given node
     *
     * @param Node $node
     * @return string
     */
    public static function render(Node $node)
    {
        $type = $node->getNodeType();

        switch ($type) {
            case Node::TYPE_PARAMETER:
                /** @var ParameterNode $node */
                return self::renderParameter($node);

            case Node::TYPE_PROPERTY:
                /** @var PropertyNode $node */
                return self::renderProperty($node);

            case Node::TYPE_FUNCTION:
                /** @var FunctionNode $node */
                return self::renderFunction($node);

            case Node::TYPE_OBJECT:
                /** @var ObjectNode $node */
                return self::renderObject($node);

            default: // Node::TYPE_OPERATOR:
                /** @var OperatorNode $node */
                return self::renderOperator($node);
        }
    }

    private static function renderParameter(ParameterNode $node)
    {
        $name = $node->getName();

        return ":{$name}";
    }

    private static function renderProperty(PropertyNode $node)
    {
        $path = $node->getPath();

        return implode('.', $path);
    }

    private static function renderFunction(FunctionNode $node)
    {
        $name = $node->getFunction();
        $arguments = $node->getArguments();

        $renderedArguments = array_map('self::render', $arguments);
        $argumentList = implode(', ', $renderedArguments);

        return "{$name}({$argumentList})";
    }

    /**
     * Produces a string representation of the given object node
     *
     * @param ObjectNode $node
     * @return string
     */
    private static function renderObject(ObjectNode $node)
    {
        $output = array();

        $properties = $node->getProperties();

        foreach ($properties as $key => $value) {
            $renderedValue = self::render($value);

            $output[] = "\"{$key}\": {$renderedValue}";
        }

        return '{' . implode(', ', $output) . '}';
    }

    private static function renderOperator(OperatorNode $node)
    {
        return $node->getLexeme();
    }
}
