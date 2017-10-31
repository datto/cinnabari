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

namespace Datto\Cinnabari\Translator\Parser;

use Datto\Cinnabari\AbstractRequest\Node;
use Datto\Cinnabari\AbstractRequest\Nodes\FunctionNode;
use Datto\Cinnabari\AbstractRequest\Nodes\PropertyNode;
use Datto\Cinnabari\Translator\Map;
use Datto\Cinnabari\Translator\Nodes\Select;
use Datto\Cinnabari\Translator\Parser\Rules\FunctionRule;
use Datto\Cinnabari\Translator\Parser\Rules\PropertyRule;
use SpencerMortensen\Parser\Core\Parser as CoreParser;
use SpencerMortensen\Parser\ParserException;
use SpencerMortensen\Parser\Rule;

class Parser extends CoreParser
{
    /** @var Rule */
    private $rule;

    /** @var Map */
    private $map;

    /** @var integer|null */
    protected $context;

    /** @var Node */
    private $node;

    /** @var Select */
    protected $select;

    public function __construct(Rule $rule, Map $map)
    {
        $this->rule = $rule;
        $this->map = $map;
    }

    protected function parse(Node $node)
    {
        $this->context = null;
        $this->node = $node;
        $this->select = new Select();

        if (!$this->runRule($this->rule, $output)) {
            throw $this->parserException();
        }

        return $this->select;
    }

    protected function runRule(Rule $rule, &$output = null)
    {
        if ($rule instanceof FunctionRule) { /** @var FunctionRule $rule */
            return $this->runFunctionRule($rule, $output);
        } elseif ($rule instanceof PropertyRule) { /** PropertyRule $rule */
            return $this->runPropertyRule($rule, $output);
        } else {
            return parent::runRule($rule, $output);
        }
    }

    private function runFunctionRule(FunctionRule $rule, &$output)
    {
        $type = $this->node->getNodeType();

        if ($type !== Node::TYPE_FUNCTION) {
            return false;
        }

        /** @var FunctionNode $node */
        $node = $this->node;

        if ($node->getFunction() !== $rule->getFunction()) {
            return false;
        }

        $nodes = $node->getArguments();
        $rules = $rule->getArguments();

        if (count($nodes) !== count($rules)) {
            return false;
        }

        $input = array();

        $state = $this->getState();

        foreach ($nodes as $key => $node) {
            $this->node = $node;

            if (!$this->runRule($rules[$key], $input[$key])) {
                $this->setState($state);
                return false;
            }
        }

        $this->node = null;
        $output = $this->formatOutput($rule, $input);
        return true;
    }

    private function runPropertyRule(Rule $rule, &$output)
    {
        $type = $this->node->getNodeType();

        if ($type !== Node::TYPE_PROPERTY) {
            return false;
        }

        /** @var PropertyNode $apiNode */
        $apiNode = $this->node;
        $propertyNames = $apiNode->getPath();

        $databaseNodes = array();

        foreach ($propertyNames as $propertyName) {
            $databaseNodes = array_merge($databaseNodes, $this->map->map($propertyName));
        }

        $input = array($databaseNodes);
        $output = $this->formatOutput($rule, $input);
        return true;
    }

    private function formatOutput(Rule $rule, array $input)
    {
        $callable = $rule->getCallable();

        if ($callable === null) {
            return $input;
        }

        return call_user_func_array($callable, $input);
    }

    private function parserException()
    {
        $ruleName = null;
        $state = null;

        return new ParserException($ruleName, $state);
    }

    protected function getState()
    {
        return array($this->context, $this->node, clone $this->select);
    }

    protected function setState($state)
    {
        list($this->context, $this->node, $this->select) = $state;
    }

    protected function setExpectation(Rule $rule = null)
    {
        // TODO
    }
}
