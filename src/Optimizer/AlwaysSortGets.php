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
 * @author Christopher Hoult <choult@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari\Optimizer;

use Datto\Cinnabari\Parser;
use Datto\Cinnabari\Schema;

/**
 * An optimizer to collapse sort, slice and filter functions when the request method is "insert"
 *
 * Rule: "insert(f(a, b), c) => insert(a, c)" when "f" is the "sort", "slice", or "filter" function
 *   Note: this rule may apply more than once during the simplification of a request
 *   Note: if this rule doesn't apply, then the request should be left unchanged
*/
class AlwaysSortGets
{
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function optimize($request, $context = false)
    {
        switch ($request[0]) {
            case Parser::TYPE_FUNCTION: {
                // Optimize children
                if (count($request) > 2) {
                    for ($i = 2; $i < count($request); $i++) {
                        $request[$i] = $this->optimizeChildren($request[$i]);
                    }
                }
                // Optimize the get
                if ($request[1] == 'get') {
                    $request = $this->processGet($request);
                }
                break;
            }
            case Parser::TYPE_OBJECT: {
                if (count($request) > 1) {
                    for ($i = 1; $i < count($request); $i++) {
                        $request[$i] = $this->optimizeChildren($request[$i]);
                    }
                }
            }
        }

        return $request;
    }

    private function optimizeChildren(array $tokens)
    {
        foreach ($tokens as $idx => $subToken) {
            $tokens[$idx] = $this->optimize($subToken);
        }

        return $tokens;
    }

    private function processGet($token)
    {
        // Is there a sort before the next get?
        $functionList = $this->getFunctionNamesUntil('get', $token[2][0]);

        if (\in_array('sort', $functionList)) {
            return $token;
        }

        $primaryKey = $this->schema->getPrimaryKey($this->getListName($token));
        if (!\in_array('slice', $functionList)) {
            return $this->insertSort($token, $primaryKey);
        }

        return $this->insertSortAfter('slice', $token, $primaryKey);
    }

    private function insertSort($token, $primaryKey)
    {
        $lhs = $token[2];
        $token[2] = array(
            array(
                Parser::TYPE_FUNCTION,
                'sort',
                $lhs,
                array(array(Parser::TYPE_PROPERTY, $primaryKey))
            )
        );
        return $token;
    }

    private function insertSortAfter($functionName, $token, $primaryKey)
    {
        if ($token[0] == Parser::TYPE_FUNCTION && $token[1] == $functionName) {
            return $this->insertSort($token, $primaryKey);
        }

        $token[2][0] = $this->insertSortAfter($functionName, $token[2][0], $primaryKey);
        return $token;
    }

    private function getFunctionNamesUntil($until, $token)
    {
        if ($token[0] !== Parser::TYPE_FUNCTION || $token[1] == $until) {
            return array();
        }

        $ret = array();
        if (isset($token[2][0])) {
            $ret = $this->getFunctionNamesUntil($until, $token[2][0]);
        }
        \array_unshift($ret, $token[1]);
        return $ret;
    }

    private function getListName($token)
    {
        return '';
    }
}
