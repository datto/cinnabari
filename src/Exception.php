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

namespace Datto\Cinnabari;

class Exception extends \Exception
{
    const QUERY_INTERNAL_ERROR = 0;
    const QUERY_INVALID_TYPE = 1;
    const QUERY_INVALID_SYNTAX = 2;
    const QUERY_INVALID_PROPERTY_ACCESS = 3;
    const QUERY_UNKNOWN_PROPERTY = 4;
    const QUERY_UNKNOWN_FUNCTION = 5;
    const QUERY_UNRESOLVABLE_TYPE_CONSTRAINTS = 6;
    const QUERY_INVALID_GRAPHQL_QUERY = 7;

    /** @var mixed */
    private $data;

    /**
     * @param int $code
     * @param mixed $data
     * @param string|null $message
     */
    public function __construct($code, $data = null, $message = null)
    {
        parent::__construct($message, $code);

        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public static function internalError($text = "")
    {
        $code = self::QUERY_INTERNAL_ERROR;

        $message = "Internal error";

        if (strlen($text) > 0) {
            $message .= " ($text)";
        }

        $message .= ".";

        return new self($code, null, $message);
    }

    public static function invalidType($input)
    {
        $code = self::QUERY_INVALID_TYPE;

        $data = array(
            'statement' => $input
        );

        $description = self::getValueDescription($input);

        $message = "Expected a string query,"
            . " but received {$description} instead.";

        return new self($code, $data, $message);
    }

    public static function invalidSyntax($expected, $input, $position, $lastParsed = null)
    {
        $code = self::QUERY_INVALID_SYNTAX;

        list($line, $character) = self::getLineCharacter($input, $position);

        $data = array(
            'statement'=> $input,
            'line' => $line,
            'character' => $character,
            'expected' => $expected
        );

        $tail = self::getTail($input, $position);
        $message = self::getExpectedMessage($expected, $tail, $lastParsed);
        $message .= ' on line ' . $line . ', character ' . $character;

        return new self($code, $data, $message);
    }

    /**
     * @param string  $message
     * @param int     $line
     * @param int     $column
     */
    public static function invalidGraphqlQuery($message, $line, $column)
    {
        $code = self::QUERY_INVALID_GRAPHQL_QUERY;

        $data = array(
            'statement'=> '',
            'line' => $line,
            'character' => $column,
        );

        return new self($code, $data, $message);
    }

    /**
     * Produces an exception message that reflects
     * what the parser was expecting, and what it actually got.
     *
     * @param string $syntax
     * A string reflecting what the parser expected
     *
     * @param string $tail
     * The part of the raw input that was not what the parser expected.
     *
     * @param string $lastParsed
     * The last part of the input to be parsed
     *
     * @return string
     */
    private static function getExpectedMessage($syntax, $tail, $lastParsed)
    {
        if ($lastParsed == null) {
            $lastParsed = '';
        } else {
            $lastParsed = self::showString($lastParsed);
            $lastParsed = " after {$lastParsed}";
        }

        $tail = self::showString($tail);

        switch ($syntax) {
            case 'expression':
                return "Expected expression{$lastParsed}, found {$tail} instead";

            case 'group-expression':
                return "Expected expression in group, found {$tail} instead";

            case 'object-element':
                return "Expected key/value pair (e.g. \"name\": property)"
                    . "{$lastParsed}, found {$tail} instead";

            case 'argument':
                return "Expected function argument{$lastParsed}, found {$tail} instead";

            case 'pair-colon':
                return "Expected ':'{$lastParsed}, found {$tail} instead";

            case 'pair-property':
                return "Expected property expression{$lastParsed}, found {$tail} instead";

            case 'property':
                return "Expected property identifier after '.', found {$tail} instead";

            case 'parameter':
                return "Expected parameter identifier after ':', found {$tail} instead";

            case 'end':
                return "Expected end of input{$lastParsed}, found {$tail} instead";

            case 'function-comma':
                return "Expected ', ' or ')'{$lastParsed}, found {$tail} instead";

            case 'object-comma':
                return "Expected ', ' or '}'{$lastParsed}, found {$tail} instead";

            case 'unary-expression':
                return "Expected unary-expression{$lastParsed} operator, found {$tail} instead";

            default:
                return " (INTERNAL) No error found matching: $syntax";
        }
    }

    public static function invalidPropertyAccess($type, $property)
    {
        $code = self::QUERY_INVALID_PROPERTY_ACCESS;

        $data = array(
            'type' => $type,
            'property' => $property
        );

        // TODO: make this more helpful
        $message = "Unable to access this property from the current context.";

        return new self($code, $data, $message);
    }

    public static function unknownProperty($class, $property)
    {
        $code = self::QUERY_UNKNOWN_PROPERTY;

        $data = array(
            'class' => $class,
            'property' => $property
        );

        $className = json_encode($class);
        $propertyName = json_encode($property);

        $message = "The {$className} class has no {$propertyName} property.";

        return new self($code, $data, $message);
    }

    public static function unknownFunction($function)
    {
        $code = self::QUERY_UNKNOWN_FUNCTION;

        $data = array(
            'function' => $function
        );

        $functionName = json_encode($function);

        $message = "There is no {$functionName} function.";

        return new self($code, $data, $message);
    }

    public static function unresolvableTypeConstraints($request)
    {
        $code = self::QUERY_UNRESOLVABLE_TYPE_CONSTRAINTS;

        $data = array(
            'request' => $request
        );

        // TODO: provide more help than this:
        $message = "This request has unresolvable type constraints.";

        return new self($code, $data, $message);
    }

    private static function getValueDescription($value)
    {
        $type = gettype($value);

        switch ($type) {
            case 'NULL':
                return 'a null value';

            case 'boolean':
                $valueJson = json_encode($value);
                return "a boolean ({$valueJson})";

            case 'integer':
                $valueJson = json_encode($value);
                return "an integer ({$valueJson})";

            case 'double':
                $valueJson = json_encode($value);
                return "a float ({$valueJson})";

            case 'string':
                $valueJson = json_encode($value);
                return "a string ({$valueJson})";

            case 'array':
                $valueJson = json_encode($value);
                return "an array ({$valueJson})";

            case 'object':
                return 'an object';

            case 'resource':
                return 'a resource';

            default:
                return 'an unknown value';
        }
    }

    private static function getTail($input, $position)
    {
        $tail = ltrim(substr($input, $position));
        $newlinePosition = strpos($tail, "\n");

        if ($newlinePosition !== false) {
            $tail = substr($tail, 0, $newlinePosition);
        }

        $tail = rtrim($tail);

        $maximumLength = 72;

        if ($maximumLength < strlen($tail)) {
            $tail = substr($tail, 0, $maximumLength) . '...';
        }

        return $tail;
    }

    private static function getLineCharacter($input, $errorPosition)
    {
        $iLine = 0;
        $iCharacter = 0;

        $lines = preg_split('~\r?\n~', $input, null, PREG_SPLIT_OFFSET_CAPTURE);

        foreach ($lines as $line) {
            list($lineText, $linePosition) = $line;

            $iCharacter = $errorPosition - $linePosition;

            if ($iCharacter <= strlen($lineText)) {
                break;
            }

            ++$iLine;
        }

        return array($iLine + 1, $iCharacter + 1);
    }

    /**
     * Convert the text input to a PHP string expression. Use single quotes
     * by default (e.g. 'single-quoted text'), but switch to double-quotes when
     * the input contains special characters (e.g. "double-quoted text\n")
     * that would be more readable as escape sequences.
     *
     * @param string $string
     * @return string
     */
    private static function showString($string)
    {
        $decidingCharacters = "'\n\r\t\v\e\f\\\$";

        if (strcspn($string, $decidingCharacters) === strlen($string)) {
            return var_export($string, true);
        }

        return '"' . addcslashes($string, "\n\r\t\v\e\f\\\$\"") . '"';
    }
}
