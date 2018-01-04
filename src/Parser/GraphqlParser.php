<?php

/**
 * Copyright (C) 2017-2018 Datto, Inc.
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
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\Parser;

use Datto\Cinnabari\AbstractRequest\Nodes\FunctionNode;
use Datto\Cinnabari\AbstractRequest\Nodes\ObjectNode;
use Datto\Cinnabari\AbstractRequest\Nodes\PropertyNode;
use Datto\Cinnabari\Exception;
use Datto\Cinnabari\Parser\Language\Properties;
use Datto\Cinnabari\Parser\Language\Types;
use GraphQL\Executor\Executor;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;

/**
 * Class GraphqlQueryParser
 *
 * This is proof-of-concept quality code to translate GraphQL queries into
 * the Cinnabari intermediate form (class AbstractRequest), and introspective
 * queries into strings. Some features remain unimplemented, others may be
 * incomplete.
 *
 * Note that ApiConfigurationFileToGraphql, in conjunction with this class,
 * adds some extensions to standard GraphQL:
 *
 *     - For each top-level schema class (e.g., "devices"), a query is created
 *       which supports the following optional arguments:
 *           - filter
 *           - sort
 *           - slice
 *           - group
 *
 *       These can use arbitrary Cinnabari-style expressions. For example:
 *              clients(sort:"name", slice:[":a", ":b"])
 *
 *     - Each top-level schema class also has the following properties, which
 *       can use Cinnabari-style expressions:
 *           - _exprBoolean(value: String!): Boolean
 *           - _exprInt(value: String!): Int
 *           - _exprFloat(value: String!): Float
 *           - _exprString(value: String!): String
 *
 * Usage:
 *   try {
 *     $propertiesText = file_get_contents('api.json');
 *     $properties = new Properties(json_decode($propertiesText, true));
 *     $queryProcessor = new GraphqlParser($cinnabariParser, 'g.schema', $properties);
 *     $data = $queryProcessor->translate($query, $operationName);
 *   }
 *   catch (Exception $exception) {
 *     $errors = $exception->getMessage();
 *   }
 *
 * Use utility class Datto\Api\Definitions\Api\ApiConfigurationFileToGraphql
 * to create the required GraphQL schema file.
 *
 * Use utility class Datto\Api\Definitions\Api\ApiConfigurationFileToJson.php
 * to create the required API JSON file.
 *
 * @package Datto\Cinnabari\GraphqlParser
 */
class GraphqlParser
{
    /** @var Parser */
    private $cinnabariParser;

    /** @var array */
    private $fragments;

    /** @var string */
    private $schemaFile;

    private $properties;

    /**
     * GraphqlParser constructor.
     *
     * @param Parser      $cinnabariParser
     * @param string      $schemaFile
     * @param Properties  $properties
     */
    public function __construct($cinnabariParser, $schemaFile, $properties)
    {
        $this->fragments = array();
        $this->cinnabariParser = $cinnabariParser;
        $this->schemaFile = $schemaFile;
        $this->properties = $properties;
    }

    /**
     * Given a GraphQL document, return the response:
     *     - null if there was an error
     *     - a string if the operation is an introspective query
     *     - an Cinnabari AbstractRequest tree otherwise
     *
     * @param string $documentText
     * @param string $operationName //optional: the name of the query or mutation
     *
     * @throws \Datto\Cinnabari\Exception
     * @return null | string | \Datto\Cinnabari\AbstractRequest\Node
     */
    public function translate($documentText, $operationName = null)
    {
        $document = $this->parseDocumentToGraphqlAst($documentText);
        $isMutation = AST::getOperation($document, $operationName)
            === 'mutation';
        $this->fragments = $this->collectFragmentDefinitions($document);
        $definitions = $document->definitions;
        $dataResult = '';

        /** @var DefinitionNode $definition */
        foreach ($definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode
                && ($operationName === null
                    || $operationName === $definition->name->value)) {
                $selectionSet = $definition->selectionSet;
                list($fields, $introspectiveSeen, $nonIntrospectiveSeen)
                    = $this->collectSelectionSetFields($selectionSet);

                if ($introspectiveSeen && $nonIntrospectiveSeen) {
                    throw self::createRestrictionError(
                        'Mixture of introspective and non-introspective fields not supported',
                        0,
                        0
                    );
                }

                if ($introspectiveSeen) {
                    $schemaString = file_get_contents($this->schemaFile);
                    $schema = BuildSchema::build($schemaString);
                    $dataResult = Executor::execute(
                        $schema,
                        $document,
                        null,
                        null,
                        null,
                        $operationName
                    );
                    $dataResult = json_encode($dataResult->data, JSON_PRETTY_PRINT);

                } else {
                    if ($isMutation) {
                        $dataResult = $this->processMutationFields($fields);
                    } else {
                        $dataResult = $this->processQueryFields($fields, null);
                    }
                }
            }
        }

        return $dataResult;
    }

    /**
     * Given a GraphQL "document" (one or more queries and or mutations),
     * return the result of the parse of the document in GraphQL Abstract
     * Syntax Tree form.
     *
     * @param string $documentText
     *
     * @return DocumentNode
     * @throws Exception
     */
    private function parseDocumentToGraphqlAst($documentText)
    {
        try {
            $document = \GraphQL\Language\Parser::parse($documentText);
        } catch (\Exception $exception) {
            throw self::createError(
                addslashes(strtr($exception->getMessage(), "\"%\n", '`_ ')),
                0,
                0
            );
        }

        return $document;
    }

    /**
     * // TODO - this function is incomplete
     * @param FieldNode[] $fields
     *
     * @throws Exception
     * @return FunctionNode
     */
    private function processMutationFields($fields)
    {
        foreach ($fields as $field) {
            $mutationName = $field->name->value;

            if (preg_match('/^delete.+$/', $mutationName)) {
                return $this->processDelete($field);
            }

            throw self::createRestrictionError(
                'Cannot handle some mutations yet!',
                0,
                0
            );
        }

        return null;
    }

    /**
     * @param FieldNode $field
     *
     * @throws Exception
     * @return FunctionNode
     */
    private function processDelete($field)
    {
        $fieldName = $field->name->value;
        $line = $field->loc->startToken->line;
        $column = $field->loc->startToken->column;

        if (count($field->arguments) !== 1) {
            throw self::createError(
                $fieldName . ' requires exactly one argument',
                $line,
                $column
            );
        }

        $argument = $field->arguments[0];
        $argumentName = $argument->name->value;         // presumably 'filter'
        $argumentValue = $argument->value;
        $arrayName = lcfirst(substr($fieldName, 6));         // e.g., "Clients"

        if ($argumentName !== 'filter'
            || !$argumentValue instanceof StringValueNode) {
            throw self::createError(
                $fieldName . ' requires a String "filter" argument',
                $line,
                $column
            );
        }

        $filter = $this->processFilter(
            $argumentValue->value,
            $arrayName,
            $field->loc->startToken->line
        );

        return new FunctionNode('delete', array($filter));
    }


    /**
     * Given a GraphQL value node, append its value (in string form) to array $strings.
     * This function is recursive to support ListValueNode.
     *
     * @param string    $argumentName
     * @param ValueNode $node
     * @param string[]  $strings
     *
     * @throws \Datto\Cinnabari\Exception
     */
    private function valueNodeToArrayOfStrings($argumentName, $node, &$strings)
    {
        if ($node instanceof StringValueNode) {
            $strings[] = $node->value;
        } elseif ($node instanceof VariableNode) {
            $strings[] = ':' . $node->name->value;
        } elseif ($node instanceof ListValueNode) {
            /** @var ValueNode $element */
            foreach ($node->values as $element) {
                $this->valueNodeToArrayOfStrings($argumentName, $element, $strings);
            }
        } else {
            /** @var Node $node */
            throw self::createValidationError(
                'Argument ' . $argumentName . ' has an invalid value',
                $node->loc->startToken->line,
                $node->loc->startToken->column
            );
        }
    }

    /**
     * Given a GraphQL field node (e.g., representing "devices"),
     * collect its arguments (i.e., filter, sort, slice). Check for argument errors.
     *
     * @param FieldNode $field
     *
     * @return array
     * @throws \Datto\Cinnabari\Exception
     */
    private function collectFieldArguments(FieldNode $field)
    {
        $arguments = array();

        foreach ($field->arguments as $argument) {
            $name = $argument->name->value;
            $values = array();
            $this->valueNodeToArrayOfStrings($argument->name->value, $argument->value, $values);

            if (isset($arguments[$name])) {
                throw self::createValidationError(
                    'There can be only one argument named ' . $name,
                    $argument->loc->startToken->line,
                    0
                );
            }

            $arguments[$name] = $values;

            if (($name === 'filter' || $name === 'sort')
                && !$argument->value instanceof StringValueNode) {
                throw self::createError(
                    'Bad argument for ' . $name . '. Expected string',
                    $argument->loc->startToken->line,
                    $argument->loc->startToken->column
                );
            }

            if (($name === 'filter' || $name === 'sort')
                && count($arguments[$name]) !== 1) {
                throw self::createError(
                    'Bad argument for ' . $name . '. Expected scalar',
                    $argument->loc->startToken->line,
                    $argument->loc->startToken->column
                );
            }

            if ($name === 'slice' && count($arguments[$name]) !== 2) {
                throw self::createError(
                    'Bad argument count for ' . $name
                    . '. Expected a list with two members',
                    $argument->loc->startToken->line,
                    $argument->loc->startToken->column
                );
            }
        }

        return $arguments;
    }

    /**
     * @param $filter
     * @param $operandName
     * @param $line
     *
     * @return FunctionNode
     * @throws Exception
     */
    private function processFilter($filter, $operandName, $line)
    {
        $result = new PropertyNode(array($operandName));
        $argument = str_replace('$', ':', $filter);

        try {
            $argumentTree = $this->cinnabariParser->parse($argument);
        } catch (Exception $exception) {
            throw self::createError(
                'Cinnabari expression error: ' . $exception->getMessage(),
                $line,
                0
            );
        }

        return new FunctionNode('filter', array($result, $argumentTree));
    }

    /**
     * Given a list of query fields forming a selection set (e.g., for query
     * "{person {id, name}}", id and name form the selection set for person),
     * recursively expand any fragment spreads (e.g., "... someFragment")
     * into their component fields, then generate Cinnabari AST for each field
     * and surround the whole lot with a Cinnabari Object node.
     *
     * A selection set at level 0 is a bit different....
     *
     * @param FieldNode[] $fields
     * @param string      $owningClass
     *
     * @throws \Datto\Cinnabari\Exception
     * @return ObjectNode
     */
    private function processQueryFields($fields, $owningClass)
    {
        $properties = array();

        foreach ($fields as $field) {
            $fieldName = $field->name->value;
            $name = $field->alias ? $field->alias->value : $fieldName;
            $properties[$name] = $this->processQueryField($field, $owningClass);
        }

        return new ObjectNode($properties);
    }

    /**
     * @param array $directives
     * @param int   $line
     *
     * @throws Exception
     */
    private function handleDirectives($directives, $line)
    {
        if (count($directives) !== 0) {
            $directive = $directives[0];
            throw self::createError('Directive is ignored: @'
                . $directive->name->value, $line, 0);
        }
    }

    /**
     * Given a node representing a field in a GraphQL query, return
     * its translation into Cinnabari AST form.
     * TODO: identify bogus directives
     * TODO: error on @include, @skip
     *
     * @param FieldNode $field
     * @param string    $owningClass
     *
     * @throws \Datto\Cinnabari\Exception
     * @return FunctionNode|PropertyNode|ObjectNode
     */
    private function processQueryField(FieldNode $field, $owningClass)
    {
        $this->handleDirectives($field->directives, $field->loc->startToken->line);
        $owningClass = ($owningClass !== null) ? $owningClass : 'Database';
        $arguments = $this->collectFieldArguments($field);
        $argumentsCopy = $arguments;
        $fieldName = $field->name->value;
        list(, $isFieldAnArray,, $fieldClass) = $this->characterizeProperty($owningClass, $fieldName);
        $hasSelectionSet = $field->selectionSet !== null;
        $result = null;
        $line = $field->loc->startToken->line;
        $column = $field->loc->startToken->column;

        if (null === $fieldClass) {
            if ($owningClass === '') {
                throw self::createValidationError(
                    'Unknown type: ' . $fieldName,
                    $line,
                    $column
                );
            }
            if ($fieldName !== '_expr') {
                throw self::createValidationError(
                    'Cannot query field: ' . $fieldName
                    . ' on type: ' . $owningClass,
                    $line,
                    $column
                );
            }
        }

        if ($hasSelectionSet && $fieldClass === '') {
            throw self::createValidationError(
                'Field ' . $fieldName
                . ' must not have a selection since its type has no subfields',
                $line,
                $column
            );
        }

        if ($isFieldAnArray) {

            if ($hasSelectionSet) {

                $result = new PropertyNode(array($fieldName));

                if (isset($arguments['filter'])) {
                    unset($argumentsCopy['filter']);
                    $result = $this->processFilter(
                        $arguments['filter'][0],
                        $fieldName,
                        $line
                    );
                }

                if (isset($arguments['sort'])) {
                    unset($argumentsCopy['sort']);
                    $result = new FunctionNode(
                        'sort',
                        array(
                            $result,
                            new PropertyNode(array($arguments['sort'][0]))
                        )
                    );
                }

                if (isset($arguments['slice'])) {
                    unset($argumentsCopy['slice']);
                    $argument0 = str_replace('$', ':', $arguments['slice'][0]);
                    $argument1 = str_replace('$', ':', $arguments['slice'][1]);
                    try {
                        $argumentTree0
                            = $this->cinnabariParser->parse($argument0);
                        $argumentTree1
                            = $this->cinnabariParser->parse($argument1);
                    } catch (Exception $exception) {
                        throw self::createError(
                            'Error in Cinnabari expression: '
                            . $exception->getMessage(),
                            $line,
                            0
                        );
                    }
                    $result = new FunctionNode(
                        'slice',
                        array($result, $argumentTree0, $argumentTree1)
                    );
                }

                if (count($argumentsCopy) > 0) {
                    $problems = implode(', ', array_keys($argumentsCopy));
                    throw self::createValidationError(
                        'Unknown argument(s) on field '
                        . $fieldName . ': ' . $problems,
                        $line,
                        0
                    );
                }

                list($fields, , ) = $this->collectSelectionSetFields($field->selectionSet);
                $selectionSet = $this->processQueryFields($fields, $fieldClass);
                $result = new FunctionNode(
                    'map',
                    array($result, $selectionSet)
                );
            } else {
                throw self::createValidationError(
                    'Field ' . $fieldName . ' of type ' . $fieldClass
                    . ' must have a selection of subfields',
                    $line,
                    0
                );  //TODO
            }
        } else {
            if (count($arguments) > 0 && $fieldName !== '_expr') {
                throw self::createError(
                    'Field ' . $fieldName . ' cannot have arguments',
                    $line,
                    $column
                );
            }

            if ($hasSelectionSet) {
                $result = $this->processQueryFields(
                    $field->selectionSet->selections,
                    $fieldClass
                );
            } elseif ($fieldName === '_expr') {
                $argument = str_replace('$', ':', $arguments['value'][0]);
                $result = $this->cinnabariParser->parse($argument);
            } else {
                $result = new PropertyNode(array($fieldName));
            }
        }

        return $result;
    }


    /**
     * Identify all the fragment definitions
     *
     * @param DocumentNode $document
     *
     * @throws \Datto\Cinnabari\Exception
     * @return array
     */
    private function collectFragmentDefinitions(DocumentNode $document)
    {
        $fragments = array();

        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragmentName = $definition->name->value;
                $this->handleDirectives(
                    $definition->directives,
                    $definition->loc->startToken->line
                );
                if (isset($fragments[$fragmentName])) {
                    throw self::createValidationError(
                        'There can be only one fragment named ' . $fragmentName,
                        $definition->loc->startToken->line,
                        0
                    );
                }
                $fragments[$fragmentName] = $definition;
            }
        }

        return $fragments;
    }


    /**
     * Given a list of fields forming a selection set (e.g., for query
     * "{person {id, name}}" id and name form the selection set for person),
     * return that list and booleans indicating whether the fields contained
     * some introspective, and some non-introspective fields, respectively.
     * (Some entries may be "fragment spreads"
     * (e.g., "... someFragment"); these are replaced by their component
     * fields.)
     *
     * @param SelectionSetNode $selectionSet
     * @param array            $activeFragments
     *
     * @throws \Datto\Cinnabari\Exception
     * @return array(FieldNode[], Bool, Bool)
     */
    private function collectSelectionSetFields(
        $selectionSet,
        array $activeFragments = array()
    ) {
        $fields = array();
        $level0IntrospectiveFields = array('__schema', '__type');
        $introspectiveSeen = false;
        $nonIntrospectiveSeen = false;

        // Collect the fields (some may be hidden in fragment spreads)
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fields[] = $selection;
                if (in_array($selection->name->value, $level0IntrospectiveFields, true)) {
                    $introspectiveSeen = true;
                } else {
                    $nonIntrospectiveSeen = true;
                }
            } elseif ($selection instanceof FragmentSpreadNode) {
                $this->handleDirectives(
                    $selection->directives,
                    $selection->loc->startToken->line
                );
                $fragmentName = $selection->name->value;
                if (isset($this->fragments[$fragmentName])) {
                    $fragmentSet = $this->fragments[$fragmentName]->selectionSet;
                    if (isset($activeFragments[$fragmentName])) {
                        throw self::createValidationError(
                            'Cannot spread fragment ' . $fragmentName
                            . ' within itself',
                            $selection->loc->startToken->line,
                            0
                        );
                    }
                    $activeFragments[$fragmentName] = true;
                    list($subfields, $introspective, $nonIntrospective)
                        = $this->collectSelectionSetFields(
                            $fragmentSet,
                            $activeFragments
                        );
                    unset($activeFragments[$fragmentName]);
                    $fields = array_merge($fields, $subfields);
                    $introspectiveSeen |= $introspective;
                    $nonIntrospectiveSeen |= $nonIntrospective;
                } else {
                    throw self::createValidationError(
                        'Unknown fragment ' . $fragmentName,
                        $selection->loc->startToken->line,
                        0
                    );
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $this->handleDirectives(
                    $selection->directives,
                    $selection->loc->startToken->line
                );
                list($subfields, $introspective, $nonIntrospective)
                    = $this->collectSelectionSetFields($selection->selectionSet);
                $fields = array_merge($fields, $subfields);
                $introspectiveSeen |= $introspective;
                $nonIntrospectiveSeen |= $nonIntrospective;
            }
        }

        return array($fields, $introspectiveSeen, $nonIntrospectiveSeen);
    }

    /**
     * Create and return an invalidGraphQlQuery exception.
     *
     * @param string $message
     * @param int    $line
     * @param int    $column
     *
     * @return Exception
     */
    public static function createError($message, $line, $column)
    {
        $error = '{"errors": [';

        $error .= '{';
        $error .= '"message": "' . $message . '"';

        if ($line > 0) {
            $error .= ', "locations": [{"line": ' . $line
                . ', "column": ' . $column . '} ]';
        }

        $error .= '}';
        $error .= ']}';
        $error = strtr($error, array("'" => ' ', '\\' => ' '));

        return Exception::invalidGraphqlQuery($error, $line, $column);
    }

    public static function createValidationError($message, $line, $column)
    {
        return self::createError('VALIDATION: ' . $message, $line, $column);
    }

    public static function createRestrictionError($message, $line, $column)
    {
        return self::createError('RESTRICTION: ' . $message, $line, $column);
    }

    /**
     * Examine a Cinnabari type data structure and return information:
     *    $isNullable, $isArray, $isObject, $underlyingType.
     *
     * Cinnabari types can be arbitrarily complex, GraphQL types cannot. This function
     * throws an Exception if the type is too complex for GraphQL.
     *
     * @param string $class
     * @param string $property
     *
     * @throws Exception
     * @return array
     */
    private function characterizeProperty($class, $property)
    {
        $type = $this->properties->getDataType($class, $property);
        $isNullable = false;
        $isArray = false;
        $isObject = false;
        $underlyingType = null;

        if ($type === null) {
            return array(null, null, null, null);
        } elseif ($this->isTypeBasic($type)) {
            $underlyingType = self::translateBasicTypeToGraphql($type);
        } elseif ($this->isTypeObject($type)) {
            $isObject = true;
            $underlyingType = $type[1];
        } elseif (is_array($type) && (count($type) === 2)
            && ($type[0] === Types::TYPE_ARRAY)) {
            $isArray = true;
            $isObject = $this->isTypeObject($type[1]);
            $underlyingType = $isObject ? $type[1][1]
                : self::translateBasicTypeToGraphql($type[1]);
        } elseif (is_array($type) && (count($type) === 3)
            && ($type[0] === Types::TYPE_OR)
            && in_array(Types::TYPE_NULL, $type, true)) {
            $isNullable = true;
            $nullableType = ($type[1] === Types::TYPE_NULL) ? $type[2]
                : $type[1];

            if ($this->isTypeBasic($nullableType)) {
                $underlyingType
                    = self::translateBasicTypeToGraphql($nullableType);
            } elseif ($this->isTypeObject($nullableType)) {
                $isObject = true;
                $underlyingType = $nullableType[1];
            } elseif (is_array($nullableType)
                && (count($nullableType) === 2)
                && ($nullableType[0] === Types::TYPE_ARRAY)) {
                $isArray = true;
                $isObject = $this->isTypeObject($nullableType[1]);
                $underlyingType = $isObject ? $nullableType[1]
                    : self::translateBasicTypeToGraphql($nullableType[1]);
            }
        } else {
            throw new Exception('Type too complicated for GraphQL');
        }

        return array($isNullable, $isArray, $isObject, $underlyingType);
    }


    /**
     * Is $type an object (i.e., of the form array(Types::TYPE_OBJECT, ...))?
     * @param int|array $type
     *
     * @return bool
     */
    private function isTypeObject($type)
    {
        return (is_array($type)
            && (count($type) === 2)
            && ($type[0] === Types::TYPE_OBJECT));
    }


    /**
     * Is $type a basic type (boolean, int, etc.)?
     *
     * @param int|array $type
     *
     * @return bool
     */
    private function isTypeBasic($type)
    {
        $basicTypes = array(
            Types::TYPE_BOOLEAN,
            Types::TYPE_INTEGER,
            Types::TYPE_FLOAT,
            Types::TYPE_STRING
        );

        return is_int($type) && in_array($type, $basicTypes, true);
    }


    /**
     * @param $type
     *
     * @return string
     * @throws Exception
     */
    private static function translateBasicTypeToGraphql($type)
    {
        switch ($type) {
            case Types::TYPE_BOOLEAN:
                return 'Boolean';

            case Types::TYPE_INTEGER:
                return 'Int';

            case Types::TYPE_FLOAT:
                return 'Float';

            case Types::TYPE_STRING:
                return 'String';
            default:
                throw new Exception('Bad type');
        }
    }
}
