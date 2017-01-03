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
 * @author Spencer Mortensen <smortensen@datto.com>
 * @author Anthony Liu <aliu@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Compiler;

use Datto\Cinnabari\Exception\CompilerException;
use Datto\Cinnabari\Legacy\Parser;
use Datto\Cinnabari\Legacy\Translator;
use Datto\Cinnabari\Legacy\TypeInferer;
use Datto\Cinnabari\Mysql\Expression;
use Datto\Cinnabari\Mysql\Functions\Average;
use Datto\Cinnabari\Mysql\Functions\CharacterLength;
use Datto\Cinnabari\Mysql\Functions\Concatenate;
use Datto\Cinnabari\Mysql\Functions\Count;
use Datto\Cinnabari\Mysql\Functions\Lower;
use Datto\Cinnabari\Mysql\Functions\Max;
use Datto\Cinnabari\Mysql\Functions\Min;
use Datto\Cinnabari\Mysql\Functions\Substring;
use Datto\Cinnabari\Mysql\Functions\Sum;
use Datto\Cinnabari\Mysql\Functions\Upper;
use Datto\Cinnabari\Mysql\Identifier;
use Datto\Cinnabari\Mysql\Literals\TrueLiteral;
use Datto\Cinnabari\Mysql\Operators\AndOperator;
use Datto\Cinnabari\Mysql\Operators\Divides;
use Datto\Cinnabari\Mysql\Operators\Equal;
use Datto\Cinnabari\Mysql\Operators\Greater;
use Datto\Cinnabari\Mysql\Operators\GreaterEqual;
use Datto\Cinnabari\Mysql\Operators\Less;
use Datto\Cinnabari\Mysql\Operators\LessEqual;
use Datto\Cinnabari\Mysql\Operators\Minus;
use Datto\Cinnabari\Mysql\Operators\Not;
use Datto\Cinnabari\Mysql\Operators\OrOperator;
use Datto\Cinnabari\Mysql\Operators\Plus;
use Datto\Cinnabari\Mysql\Operators\RegexpBinary;
use Datto\Cinnabari\Mysql\Operators\Times;
use Datto\Cinnabari\Mysql\Parameter;
use Datto\Cinnabari\Mysql\Statements\Select;
use Datto\Cinnabari\Mysql\Table;
use Datto\Cinnabari\Php\Input;
use Datto\Cinnabari\Php\Output;

/**
 * Class GetCompiler
 * @package Datto\Cinnabari
 */
class GetCompiler
{
    const IS_OPTIONAL = true;
    const IS_REQUIRED = false;

    /** @var array */
    private $schema;

    /** @var array */
    private $signatures;

    /** @var Select */
    private $mysql;

    /** @var Input */
    private $input;

    /** @var string */
    private $phpOutput;

    /** @var array */
    private $request;

    /** @var array */
    private $table;

    /** @var int */
    private $context;

    /** @var Select */
    private $subquery;

    /** @var int */
    private $subqueryContext;

    /** @var array */
    private $contextJoin;

    /** @var array */
    private $rollbackPoint;

    public function __construct($schema, $signatures)
    {
        $this->schema = $schema;
        $this->signatures = $signatures;
    }

    public function compile($request)
    {
        $topLevelFunction = self::getTopLevelFunction($request);

        $translator = new Translator($this->schema);
        $translatedRequest = $translator->translateIgnoringObjects($request);
        $optimizedRequest = self::optimize($topLevelFunction, $translatedRequest);

        $this->mysql = new Select();
        $this->input = new Input();
        $this->phpOutput = null;
        $this->request = $optimizedRequest;
        $this->table = null;
        $this->context = null;
        $this->subquery = null;
        $this->subqueryContext = null;
        $this->contextJoin = null;
        $this->rollbackPoint = array();

        $this->enterTable();

        $id = $this->table['id'];
        $hasZero = $this->table['hasZero'];
        $this->getFunctionSequence($topLevelFunction, $id, $hasZero);

        $types = self::getTypes($this->signatures, $optimizedRequest);

        $mysql = $this->mysql->getMysql();
        $phpInput = $this->input->getPhp($types);
        $phpOutput = $this->phpOutput;

        return array($mysql, $phpInput, $phpOutput);
    }

    private static function getTopLevelFunction($request)
    {
        if (!isset($request) || (count($request) === 0)) {
            throw CompilerException::unknownRequestType($request);
        }

        $firstToken = reset($request);

        if (count($firstToken) < 3) {
            throw CompilerException::unknownRequestType($request);
        }

        list($tokenType, $functionName, ) = $firstToken;

        if ($tokenType !== Parser::TYPE_FUNCTION) {
            throw CompilerException::unknownRequestType($request);
        }

        return $functionName;
    }

    private function enterTable()
    {
        $firstElement = array_shift($this->request);
        list(, $token) = each($firstElement);

        $table = new Table($token['table']);
        $context = $this->mysql->setTable($table);

        $this->table = $token;
        $this->context = $context;
    }

    private function getFunctionSequence($topLevelFunction, $id, $hasZero)
    {
        $idAlias = null;

        if ($topLevelFunction === 'get') {
            $idAlias = $this->mysql->addValue($this->context, $id);
        }
            
        $this->getOptionalFilterFunction();
        $this->getOptionalSortFunction();
        $this->getOptionalSliceFunction();

        if (!isset($this->request) || count($this->request) === 0) {
            throw CompilerException::badGetArgument($this->request);
        }

        if ($this->readFork()) {
            return $this->getFunctionSequence($topLevelFunction, null, null);
        }

        $this->request = reset($this->request);

        if ($this->readGet()) {
            $this->phpOutput = Output::getList($idAlias, $hasZero, true, $this->phpOutput);
            return true;
        }

        if ($this->readCount()) {
            return true;
        }

        if ($this->readParameterizedAggregator($topLevelFunction)) {
            return true;
        }

        throw CompilerException::invalidMethodSequence($this->request);
    }

    private function readFork()
    {
        $token = reset($this->request);

        if (!self::scanFunction($token, $name, $arguments)) {
            return false;
        }

        if ($name !== 'fork') {
            return false;
        }

        array_shift($this->request);

        $this->subquery = $this->mysql;
        $this->mysql = new Select();
        $this->subqueryContext = $this->context;
        $this->context = $this->mysql->setTable($this->subquery);

        return true;
    }

    private function getSubtractiveParameters($nameA, $nameB, &$outputA, &$outputB)
    {
        $idA = $this->input->useSliceBeginArgument($nameA, self::IS_REQUIRED);
        $idB = $this->input->useSliceEndArgument($nameA, $nameB, self::IS_REQUIRED);

        if (($idA === null) || ($idB === null)) {
            return false;
        }

        $outputA = new Parameter($idA);
        $outputB = new Parameter($idB);
        return true;
    }

    private function readExpression()
    {
        if (!isset($this->request) || (count($this->request) < 1)) {
            return false;
        }

        $firstElement = reset($this->request);
        list($tokenType, $token) = each($firstElement);

        if (!isset($token)) {
            return false;
        }

        switch ($tokenType) {
            case Translator::TYPE_JOIN:
                $this->setRollbackPoint();
                $this->handleJoin($token);
                array_shift($this->request);

                return $this->rollback(
                    $this->readExpression()
                );

            case Translator::TYPE_VALUE:
                return $this->readProperty();

            case Translator::TYPE_OBJECT:
                return $this->readObject();

            case Translator::TYPE_FUNCTION:
                return $this->readFunction();

            default:
                return false;
        }
    }

    private function readProperty()
    {
        $firstElement = reset($this->request);
        list(, $token) = each($firstElement);

        $actualType = $token['type'];
        $column = $token['expression'];
        $hasZero = $token['hasZero'];

        $tableId = $this->context;

        $columnId = $this->mysql->addValue($tableId, $column);
        $this->phpOutput = Output::getValue($columnId, $hasZero, $actualType);

        return true;
    }

    private function readObject()
    {
        if (!self::scanObject($this->request, $object)) {
            return false;
        }

        $properties = array();

        $initialContext = $this->context;
        foreach ($object as $label => $this->request) {
            $this->context = $initialContext;
            if (!$this->readExpression()) {
                return false;
            }

            $properties[$label] = $this->phpOutput;
        }

        $this->phpOutput = Output::getObject($properties);
        return true;
    }

    private function getGet($request)
    {
        $this->request = $request;

        if (!isset($this->contextJoin)) {
            throw CompilerException::badGetArgument($this->request);
        }

        return $this->getFunctionSequence(
            'get',
            $this->contextJoin['id'],
            $this->contextJoin['hasZero']
        );
    }

    private function readGet()
    {
        if (!self::scanFunction($this->request, $name, $arguments)) {
            return false;
        }

        if ($name !== 'get') {
            return false;
        }

        if (!isset($arguments) || (count($arguments) !== 1)) {
            throw CompilerException::badGetArgument($this->request);
        }

        // at this point they definitely intend to use a get function
        $this->request = reset($arguments);

        if (!$this->readExpression()) {
            throw CompilerException::badGetArgument($this->request);
        }

        return true;
    }

    private function readCount()
    {
        if (!self::scanFunction($this->request, $name, $arguments)) {
            return false;
        }

        if ($name !== 'count') {
            return false;
        }

        if (!isset($arguments) || (count($arguments) > 0)) {
            throw CompilerException::badGetArgument($this->request);
        }

        $this->request = null;

        $countExpression = $this->getCountExpression();
        $columnId = $this->mysql->addExpression($countExpression);

        $this->phpOutput = Output::getValue($columnId, false, Output::TYPE_INTEGER);

        return true;
    }

    private function getCountExpression()
    {
        if (isset($this->subquery)) {
            $true = new TrueLiteral();

            $expressionId = $this->subquery->addExpression($true);

            $expressionInner = new Identifier($this->context, $expressionId);
        } else {
            $tableAlias = $this->context;
            $tableIdName = substr($this->table['id'], 1, -1);

            $expressionInner = new Identifier($tableAlias, $tableIdName);
        }

        return new Count($expressionInner);
    }

    private function readParameterizedAggregator($functionName)
    {
        if (!self::scanFunction($this->request, $name, $arguments)) {
            return false;
        }

        if (!isset($arguments) || (count($arguments) !== 1)) {
            throw CompilerException::badGetArgument($this->request);
        }

        // at this point they definitely intend to use a parameterized aggregator
        $this->request = reset($arguments);
        if (!isset($this->request) || (count($this->request) === 0)) {
            throw CompilerException::badGetArgument($this->request);
        }

        $this->request = $this->followJoins($this->request);
        if (!isset($this->request) || (count($this->request) === 0)) {
            throw CompilerException::badGetArgument($this->request);
        }

        $this->request = reset($this->request);
        if (!$this->scanProperty($this->request, $table, $name, $type, $hasZero)) {
            throw CompilerException::badGetArgument($this->request);
        }

        $column = new Identifier($this->context, substr($name, 1, -1));
        $expressionToAggregate = $column;

        if (isset($this->subquery)) {
            $expressionId = $this->subquery->addExpression($column);

            $expressionToAggregate = new Identifier($this->context, $expressionId);
        }

        switch ($functionName) {
            case 'average':
                $aggregator = new Average($expressionToAggregate);
                $type = Output::TYPE_FLOAT;
                break;

            case 'sum':
                $aggregator = new Sum($expressionToAggregate);
                break;

            case 'min':
                $aggregator = new Min($expressionToAggregate);
                break;

            case 'max':
                $aggregator = new Max($expressionToAggregate);
                break;

            default:
                throw CompilerException::unknownRequestType($functionName);
        }

        $columnId = $this->mysql->addExpression($aggregator);
        $this->phpOutput = Output::getValue($columnId, true, $type);

        return true;
    }

    private function readFunction()
    {
        if (!self::scanFunction(reset($this->request), $name, $arguments)) {
            return false;
        }

        switch ($name) {
            case 'get':
                return $this->getGet($this->request);

            case 'uppercase':
            case 'lowercase':
            case 'substring':
            case 'length':
            case 'plus':
            case 'minus':
            case 'times':
            case 'divides':
                if (!$this->getExpression(
                    $this->request,
                    self::IS_REQUIRED,
                    $expression,
                    $type
                )) {
                    return false;
                }

                /** @var Expression $expression */
                $columnId = $this->mysql->addExpression($expression);

                $isNullable = true; // TODO: assumption
                $this->phpOutput = Output::getValue(
                    $columnId,
                    $isNullable,
                    $type
                );

                return true;

            default:
                return false;
        }
    }

    private function getOptionalFilterFunction()
    {
        if (!self::scanFunction(reset($this->request), $name, $arguments)) {
            return false;
        }

        if ($name !== 'filter') {
            return false;
        }

        if (!isset($arguments) || (count($arguments) === 0)) {
            throw CompilerException::noFilterArguments($this->request);
        }

        if (!$this->getExpression($arguments[0], self::IS_REQUIRED, $where, $type)) {
            throw CompilerException::badFilterExpression(
                $this->context,
                $arguments[0]
            );
        }

        $this->mysql->setWhere($where);

        array_shift($this->request);

        return true;
    }

    private function getExpression($arrayToken, $hasZero, &$expression, &$type)
    {
        $firstElement = reset($arrayToken);
        list($tokenType, $token) = each($firstElement);

        $context = $this->context;
        $result = false;

        switch ($tokenType) {
            case Translator::TYPE_JOIN:
                $this->setRollbackPoint();
                $this->handleJoin($token);
                array_shift($arrayToken);
                $result = $this->rollback(
                    $this->getExpression($arrayToken, $hasZero, $expression, $type)
                );
                break;

            case Translator::TYPE_PARAMETER:
                $result = $this->getParameter($token, $hasZero, $expression);
                break;

            case Translator::TYPE_VALUE:
                $result = $this->getProperty($token, $expression, $type);
                if (isset($this->subquery) && $result) {
                    $subqueryValueId = $this->subquery->addValue(
                        $this->subqueryContext,
                        $token['expression']
                    );

                    $expression = new Identifier($this->context, $subqueryValueId);
                }
                break;

            case Translator::TYPE_FUNCTION:
                $name = $token['function'];
                $arguments = $token['arguments'];
                $result = $this->getFunction($name, $arguments, $hasZero, $expression, $type);
                break;

            default:
                // TODO
        }

        $this->context = $context;
        return $result;
    }

    private function getOptionalSortFunction()
    {
        if (!self::scanFunction(reset($this->request), $name, $arguments)) {
            return false;
        }

        if ($name !== 'sort') {
            return false;
        }

        if (!isset($arguments) || count($arguments) !== 1) {
            // TODO: add an explanation of the missing argument, or link to the documentation
            throw CompilerException::noSortArguments($this->request);
        }

        $state = array($this->request, $this->context);

        $this->request = $arguments[0];
        $this->request = $this->followJoins($this->request);

        if (!$this->scanProperty(reset($this->request), $table, $name, $type, $hasZero)) {
            return false;
        }

        if (isset($this->subquery)) {
            $name = $this->subquery->addValue($this->subqueryContext, $name);
        } else {
            $name = substr($name, 1, -1);
        }

        $columnIdentifier = new Identifier($this->context, $name);
        $this->mysql->setOrderBy($columnIdentifier, Select::ORDER_ASCENDING);

        list($this->request, $this->context) = $state;

        array_shift($this->request);

        return true;
    }

    private function getOptionalSliceFunction()
    {
        if (!self::scanFunction(reset($this->request), $name, $arguments)) {
            return false;
        }

        if ($name !== 'slice') {
            return false;
        }

        if (!isset($arguments) || count($arguments) !== 2) {
            throw CompilerException::badSliceArguments($this->request);
        }

        if (
            !$this->scanParameter($arguments[0], $nameA) ||
            !$this->scanParameter($arguments[1], $nameB)
        ) {
            return false;
        }

        if (!$this->getSubtractiveParameters($nameA, $nameB, $start, $end)) {
            return false;
        }

        $this->mysql->setLimit($start, $end);

        array_shift($this->request);

        return true;
    }

    private function getProperty($propertyToken, &$output, &$type)
    {
        $column = $propertyToken['expression'];
        $type = $propertyToken['type'];

        $output = new Identifier($this->context, substr($column, 1, -1));

        return true;
    }

    private static function scanObject($input, &$object)
    {
        // scan the next token of the supplied arrayToken
        $input = reset($input);
        list($tokenType, $token) = each($input);
        if ($tokenType !== Translator::TYPE_OBJECT) {
            return false;
        }

        $object = $token;
        return true;
    }

    private static function getTypes($signatures, $translatedRequest)
    {
        $typeInferer = new TypeInferer($signatures);

        self::extractExpression($translatedRequest, $expressions);

        return $typeInferer->infer($expressions);
    }

    private static function extractExpression($requestArray, &$expressions)
    {
        if (!isset($expressions)) {
            $expressions = array();
        }

        $localExpressions = array();

        foreach ($requestArray as $request) {
            list($tokenType, $token) = each($request);

            switch ($tokenType) {
                case Translator::TYPE_FUNCTION:
                    $arguments = array();
                    foreach ($token['arguments'] as $argument) {
                        $argumentExpressions = self::extractExpression($argument, $expressions);
                        if (count($argumentExpressions) > 0) {
                            $expression = self::extractExpression($argument, $expressions);
                            $arguments[] = end($expression);
                        }
                    }
                    if (count($arguments) > 0) {
                        $expressions[] = array(
                            'name' => $token['function'],
                            'type' => 'function',
                            'arguments' => $arguments
                        );
                    }
                    break;

                case Translator::TYPE_PARAMETER:
                    $localExpressions[] = array(
                        'name' => $token,
                        'type' => 'parameter'
                    );
                    break;

                case Translator::TYPE_VALUE:
                    $localExpressions[] = array(
                        'name' => $token['type'],
                        'type' => 'primitive'
                    );
                    break;

                case Translator::TYPE_LIST:
                    foreach ($token as $pair) {
                        $left = self::extractExpression($pair['property'], $expressions);
                        $right = self::extractExpression($pair['value'], $expressions);
                        if ((count($left) > 0) && (count($right) > 0)) {
                            $expressions[] = array(
                                'name' => 'assign',
                                'type' => 'function',
                                'arguments' => array($left[0], $right[0])
                            );
                        }
                    }
                    break;
            }
        }

        return $localExpressions;
    }

    private function optimize($topLevelFunction, $request)
    {
        $method = self::analyze($topLevelFunction, $request);

        // Rule: remove unnecessary sort functions
        if (
            $method['is']['count'] ||
            $method['is']['aggregator'] ||
            $method['is']['set'] ||
            $method['is']['delete']
        ) {
            if (
                $method['before']['sorts']['slices'] || (
                    $method['sorts'] && !$method['slices']
                )
            ) {
                $request = self::removeFunction('sort', $request, $sort);
                $method['sorts'] = false;
                $method['before']['sorts']['filters'] = false;
                $method['before']['sorts']['slices'] = false;
                $method['before']['filters']['sorts'] = false;
                $method['before']['slices']['sorts'] = false;
            }
        }

        // Rule: slices imply a sort
        if (
            self::scanTable($request, $table, $id, $hasZero) && (
                !$method['before']['slices']['sorts'] || (
                    $method['slices'] && !$method['sorts']
                )
            )
        ) {
            // TODO: get the type of the table's id; don't assume int
            $type = Output::TYPE_INTEGER;
            $valueToken = array(
                Translator::TYPE_VALUE => array(
                    'table' => $table,
                    'expression' => $id,
                    'type' => $type,
                    'hasZero' => $hasZero
                )
            );
            $sortFunction = array(
                Translator::TYPE_FUNCTION => array(
                    'function' => 'sort',
                    'arguments' => array(array($valueToken))
                )
            );
            $request = self::insertFunctionBefore($sortFunction, 'slice', $request);
        }

        // Rule: slices in countsaggregators require subqueries
        if ($method['is']['count'] || $method['is']['aggregator']) {
            if ($method['slices']) {
                $forkFunction = array(
                    Translator::TYPE_FUNCTION => array(
                        'function' => 'fork',
                        'arguments' => array()
                    )
                );

                $request = self::insertFunctionAfter($forkFunction, 'slice', $request);
            }
        }

        // Rule: when filters and sorts are adjacent, force the filter to appear before the sort
        if (
            $method['before']['filters']['sorts'] && (
                !$method['slices'] || (
                    // the slice cannot be between the filter and the sort
                    $method['before']['filters']['slices'] === $method['before']['sorts']['slices']
                )
            )
        ) {
            $request = self::removeFunction('sort', $request, $removedFunction);
            $request = self::insertFunctionAfter($removedFunction, 'filter', $request);
            $method['before']['filters']['sorts'] = false;
            $method['before']['sorts']['filters'] = true;
        }

        return $request;
    }

    private static function removeFunction($functionName, $request, &$removedFunction)
    {
        return array_filter(
            $request,
            function ($wrappedToken) use ($functionName, &$removedFunction) {
                list($tokenType, $token) = each($wrappedToken);

                $include = ($tokenType !== Translator::TYPE_FUNCTION) ||
                    $token['function'] !== $functionName;

                if (!$include) {
                    $removedFunction = $wrappedToken;
                }

                return $include;
            }
        );
    }

    private static function insertFunctionBefore($function, $target, $request)
    {
        return self::insertFunctionRelativeTo(true, $function, $target, $request);
    }

    private static function insertFunctionAfter($function, $target, $request)
    {
        return self::insertFunctionRelativeTo(false, $function, $target, $request);
    }

    private static function insertFunctionRelativeTo($insertBefore, $function, $target, $request)
    {
        return array_reduce(
            $request,
            function ($carry, $wrappedToken) use ($insertBefore, $function, $target) {
                list($type, $token) = each($wrappedToken);
                $tokensToAdd = array($wrappedToken);
                if ($type === Translator::TYPE_FUNCTION && $token['function'] === $target) {
                    if ($insertBefore) {
                        array_unshift($tokensToAdd, $function);
                    } else {
                        $tokensToAdd[] =  $function;
                    }
                }
                return array_merge($carry, $tokensToAdd);
            },
            array()
        );
    }

    private function analyze($topLevelFunction, $translatedRequest)
    {
        // is a get, delete, set, insert, count, aggregator
        $method = array();
        $method['is'] = array();
        $method['is']['get'] = false;
        $method['is']['delete'] = false;
        $method['is']['set'] = false;
        $method['is']['insert'] = false;
        $method['is']['count'] = false;
        $method['is']['aggregator'] = false;

        if (array_key_exists($topLevelFunction, $method['is'])) {
            $method['is'][$topLevelFunction] = true;
        } else {
            $method['is']['aggregator'] = true;
        }

        // order of the list functions
        $functions = array();
        foreach ($translatedRequest as $wrappedToken) {
            list($tokenType, $token) = each($wrappedToken);
            if ($tokenType === Translator::TYPE_FUNCTION) {
                $functions[] = $token['function'];
            }
        }

        $method['before'] = array(
            'filters' => array('sorts' => false, 'slices' => false),
            'sorts' => array('filters' => false, 'slices' => false),
            'slices' => array('filters' => false, 'sorts' => false)
        );
        $filterIndex = array_search('filter', $functions, true);
        $sortIndex = array_search('sort', $functions, true);
        $sliceIndex = array_search('slice', $functions, true);
        $method['filters'] = $filterIndex !== false;
        $method['sorts'] = $sortIndex !== false;
        $method['slices'] = $sliceIndex !== false;
        if ($method['filters'] && $method['sorts']) {
            $method['before']['filters']['sorts'] = $filterIndex > $sortIndex;
            $method['before']['sorts']['filters'] = $sortIndex > $filterIndex;
        }
        if ($method['filters'] && $method['slices']) {
            $method['before']['filters']['slices'] = $filterIndex > $sliceIndex;
            $method['before']['slices']['filters'] = $sliceIndex > $filterIndex;
        }
        if ($method['sorts'] && $method['slices']) {
            $method['before']['sorts']['slices'] = $sortIndex > $sliceIndex;
            $method['before']['slices']['sorts'] = $sliceIndex > $sortIndex;
        }

        return $method;
    }

    private function handleJoin($token)
    {
        if ($token['isContextual']) {
            $this->contextJoin = $token;
        }

        if (isset($this->subquery)) {
            $this->subqueryContext = $this->subquery->addJoin(
                $this->subqueryContext,
                $token['tableB'],
                $token['expression'],
                $token['hasZero'],
                $token['hasMany']
            );
        } else {
            $this->context = $this->mysql->addJoin(
                $this->context,
                $token['tableB'],
                $token['expression'],
                $token['hasZero'],
                $token['hasMany']
            );
        }
    }

    private function followJoins($arrayToken)
    {
        while ($this->scanJoin(reset($arrayToken), $joinToken)) {
            $this->handleJoin($joinToken);
            array_shift($arrayToken);
        }

        return $arrayToken;
    }

    private function getFunction($name, $arguments, $hasZero, &$output, &$type)
    {
        $countArguments = count($arguments);

        if ($countArguments === 1) {
            $argument = current($arguments);
            return $this->getUnaryFunction($name, $argument, $hasZero, $output, $type);
        }

        if ($countArguments === 2) {
            list($argumentA, $argumentB) = $arguments;
            return $this->getBinaryFunction($name, $argumentA, $argumentB, $hasZero, $output, $type);
        }

        if ($countArguments === 3) {
            list($argumentA, $argumentB, $argumentC) = $arguments;
            return $this->getTernaryFunction($name, $argumentA, $argumentB, $argumentC, $hasZero, $output, $type);
        }

        return false;
    }

    private function getUnaryFunction($name, $argument, $hasZero, &$expression, &$type)
    {
        if ($name === 'length') {
            return $this->getLengthFunction($argument, $hasZero, $expression, $type);
        }

        if (!$this->getExpression($argument, $hasZero, $childExpression, $argumentType)) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentType, false, false);

        switch ($name) {
            case 'uppercase':
                $expression = new Upper($childExpression);
                return true;

            case 'lowercase':
                $expression = new Lower($childExpression);
                return true;

            case 'not':
                $expression = new Not($childExpression);
                return true;

            default:
                $type = null;
                return false;
        }
    }

    private function getLengthFunction($argument, $hasZero, &$expression, &$type)
    {
        if (!$this->getExpression($argument, self::IS_REQUIRED, $childExpression, $argumentType)) {
            return false;
        }

        $type = Output::TYPE_INTEGER;
        $expression = new CharacterLength($childExpression);
        return true;
    }

    private function getBinaryFunction($name, $argumentA, $argumentB, $hasZero, &$expression, &$type)
    {
        if (
            !$this->getExpression($argumentA, $hasZero, $expressionA, $argumentTypeOne) ||
            !$this->getExpression($argumentB, $hasZero, $expressionB, $argumentTypeTwo)
        ) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentTypeOne, $argumentTypeTwo, false);

        switch ($name) {
            case 'plus':
                if ($argumentTypeOne === Output::TYPE_STRING) {
                    $expression = new Concatenate($expressionA, $expressionB);
                } else {
                    $expression = new Plus($expressionA, $expressionB);
                }
                return true;

            case 'minus':
                $expression = new Minus($expressionA, $expressionB);
                return true;

            case 'times':
                $expression = new Times($expressionA, $expressionB);
                return true;

            case 'divides':
                $expression = new Divides($expressionA, $expressionB);
                return true;

            case 'equal':
                $expression = new Equal($expressionA, $expressionB);
                return true;

            case 'and':
                $expression = new AndOperator($expressionA, $expressionB);
                return true;

            case 'or':
                $expression = new OrOperator($expressionA, $expressionB);
                return true;

            case 'notEqual':
                $equalExpression = new Equal($expressionA, $expressionB);
                $expression = new Not($equalExpression);
                return true;

            case 'less':
                $expression = new Less($expressionA, $expressionB);
                return true;

            case 'lessEqual':
                $expression = new LessEqual($expressionA, $expressionB);
                return true;

            case 'greater':
                $expression = new Greater($expressionA, $expressionB);
                return true;

            case 'greaterEqual':
                $expression = new GreaterEqual($expressionA, $expressionB);
                return true;

            case 'match':
                $expression = new RegexpBinary($expressionA, $expressionB);
                return true;

            default:
                $type = null;
                return false;
        }
    }

    private function getTernaryFunction($name, $argumentA, $argumentB, $argumentC, $hasZero, &$expression, &$type)
    {
        if ($name === 'substring') {
            return $this->getSubstringFunction($argumentA, $argumentB, $argumentC, $hasZero, $expression, $type);
        }

        if (
            !$this->getExpression($argumentA, $hasZero, $expressionA, $argumentTypeOne) ||
            !$this->getExpression($argumentB, $hasZero, $expressionB, $argumentTypeTwo) ||
            !$this->getExpression($argumentC, $hasZero, $expressionC, $argumentTypeThree)
        ) {
            return false;
        }

        $type = $this->getReturnTypeFromFunctionName($name, $argumentTypeOne, $argumentTypeTwo, $argumentTypeThree);

        switch ($name) {
            default:
                $type = null;
                return false;
        }
    }

    private function getSubstringFunction($stringExpression, $beginParameter, $endParameter, $hasZero, &$expression, &$type)
    {
        if (!$this->getExpression($stringExpression, self::IS_REQUIRED, $stringMysql, $typeA)) {
            return false;
        }

        if (
            !$this->scanParameter($beginParameter, $beginName) ||
            !$this->scanParameter($endParameter, $endName)
        ) {
            return false;
        }

        $beginId = $this->input->useSubstringBeginArgument($beginName, self::IS_REQUIRED);
        $endId = $this->input->useSubstringEndArgument($beginName, $endName, self::IS_REQUIRED);

        $beginMysql = new Parameter($beginId);
        $endMysql = new Parameter($endId);

        $expression = new Substring($stringMysql, $beginMysql, $endMysql);
        $type = Output::TYPE_STRING;

        return true;
    }

    private function getReturnTypeFromFunctionName($name, $typeOne, $typeTwo, $typeThree)
    {
        if (array_key_exists($name, $this->signatures)) {
            $signatures = $this->signatures[$name];

            foreach ($signatures as $signature) {
                if (self::signatureMatchesArguments($signature, $typeOne,
                    $typeTwo, $typeThree)
                ) {
                    return $signature['return'];
                }
            }

            return $signatures[0]['return'];
        } else {
            return false;
        }
    }

    private static function signatureMatchesArguments($signature, $typeOne, $typeTwo, $typeThree)
    {
        if ($signature['arguments'][0] !== $typeOne) {
            return false;
        }

        // TODO: assumes functions take at most 3 arguments for simplicity
        if (count($signature['arguments']) >= 2) {
            if ($signature['arguments'][1] !== $typeTwo) {
                return false;
            }

            if (count($signature['arguments']) >= 3) {
                return $signature['arguments'][2] === $typeThree;
            }

            return true;
        }

        return true;
    }

    private function getParameter($name, $hasZero, &$output)
    {
        $id = $this->input->useArgument($name, $hasZero);

        if ($id === null) {
            return false;
        }

        $output = new Parameter($id);
        return true;
    }

    private static function scanTable($input, &$table, &$id, &$hasZero)
    {
        // scan the next token of the supplied arrayToken
        $input = reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_TABLE) {
            return false;
        }

        $table = $token['table'];
        $id = $token['id'];
        $hasZero = $token['hasZero'];

        return true;
    }

    private static function scanParameter($input, &$name)
    {
        // scan the next token of the supplied arrayToken
        $input = reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_PARAMETER) {
            return false;
        }

        $name = $token;
        return true;
    }

    private static function scanProperty($input, &$table, &$name, &$type, &$hasZero)
    {
        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_VALUE) {
            return false;
        }

        $table = $token['table'];
        $name = $token['expression'];
        $type = $token['type'];
        $hasZero = $token['hasZero'];
        return true;
    }

    private static function scanFunction($input, &$name, &$arguments)
    {
        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_FUNCTION) {
            return false;
        }

        $name = $token['function'];
        $arguments = $token['arguments'];
        return true;
    }

    private static function scanJoin($input, &$object)
    {
        reset($input);

        list($tokenType, $token) = each($input);

        if ($tokenType !== Translator::TYPE_JOIN) {
            return false;
        }

        $object = $token;
        return true;
    }

    private function setRollbackPoint()
    {
        $this->rollbackPoint[] = array(
            $this->context,
            $this->contextJoin,
            $this->input,
            $this->mysql
        );
    }

    private function rollback($success)
    {
        if ($success) {
            array_pop($this->rollbackPoint);
        } else {
            $state = array_pop($this->rollbackPoint);

            $this->context = $state[0];
            $this->contextJoin = $state[1];
            $this->input = $state[2];
            $this->mysql = $state[3];
        }

        return $success;
    }
}
