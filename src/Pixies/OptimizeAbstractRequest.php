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
 * @author Mark Greeley mgreeley@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016, 2017 Datto, Inc.
 */

namespace Datto\Cinnabari\Pixies;

use Datto\Cinnabari\AbstractRequest\Node;
use Datto\Cinnabari\AbstractRequest\Nodes\FunctionNode;

/**
 * Class Optimize
 *
 * Perform target-independent optimizations on the Abstract Request
 * (Abstract Syntax Tree).
 *
 */
class OptimizeAbstractRequest
{
    /**
     * Make some general optimizations to the AST, applicable to all targets.
     *
     * @param Node $root
     * @return Node
     */
    public static function optimize(Node $root)
    {
        self::fixUselessSorts($root, false);
        $root = self::fixFilterOfSort($root);
        $root = self::fixInsert($root);
        return $root;
    }

    /**
     * filter(sort(a, b), c) => sort(filter(a, c), b).  (Also works for rsort.)
     *
     * This is a general optimization, assuming that the filter runs in linear time.
     * Also, if the eventual output is (My)SQL, the new ordering simplifies the
     * output by removing a subquery.
     * This sequence may match more than once in a single request.
     *
     * @param Node $topNode
     * @return Node
     */
    private static function fixFilterOfSort(Node $topNode)
    {
        //////// Perform initial checking
        if ($topNode->getNodeType() !== Node::TYPE_FUNCTION) {
            return $topNode;
        }

        /** @var FunctionNode $topNode */

        if ($topNode->getFunction() !== 'filter') {
            return $topNode;
        }

        //////// Process the child nodes of $topNode
        $filterArguments = array();

        foreach ($topNode->getArguments() as $kid) {
            if ($kid->getNodeType() === Node::TYPE_FUNCTION) {
                $filterArguments[] = self::fixFilterOfSort($kid);
            }
        }

        $topNode->setArguments($filterArguments);

        //////// Check if argument 0 of filter is a function call
        if ($filterArguments[0]->getNodeType() !== Node::TYPE_FUNCTION) {
            return $topNode;
        }

        /** @var FunctionNode $sortNode */
        $sortNode = $filterArguments[0];

        //////// filter(sort(a, b), c) => sort(filter(a, c), b)
        if (in_array($sortNode->getFunction(), array('rsort', 'sort'))) {
            $sortArguments = $sortNode->getArguments();
            $newFilterNode = new FunctionNode('filter', array($sortArguments[0], $filterArguments[1]));
            $newSortNode = new FunctionNode($sortNode->getFunction(), array($newFilterNode, $sortArguments[1]));
            $topNode = $newSortNode;
        }

        return $topNode;
    }


    /**
     * insert(f(a, b), c) => insert(a, c) where "f" is "(r)sort", "slice", or "filter."
     *
     * An optimization to collapse (r)sort, slice and filter functions when the
     * request method is "insert."
     * This rule may apply more than once during the simplification of a request.
     *
     * @param Node $topNode
     * @return Node
     */
    private static function fixInsert(Node $topNode)
    {
        //////// Perform initial checking
        if ($topNode->getNodeType() !== Node::TYPE_FUNCTION) {
            return $topNode;
        }

        /** @var FunctionNode $topNode */

        //////// Process the child nodes of $topNode
        $newArguments = $topNode->getArguments();

        foreach ($newArguments as $kid) {
            $newArguments[] = self::fixInsert($kid);
        }

        $useless = array('rsort', 'sort', 'slice', 'filter');

        //////// Perform more checking
        if ($topNode->getFunction() !== 'insert'
            || $newArguments[0]->getNodeType() !== Node::TYPE_FUNCTION) {
            return $topNode;
        }

        /** @var FunctionNode $bottomNode */
        $bottomNode = $newArguments[0];

        //////// If it's insert(useless(...)), make the change
        if (in_array($bottomNode->getFunction(), $useless)) {
            $bottomKids = $bottomNode->getArguments();
            $topNode->setArgument(0, $bottomKids[0]);
        }

        return $topNode;
    }


    /**
     * A sort or rsort below an aggregate or sort function is useless and
     * can be removed, unless there is a slice between them.
     *
     * @param Node $topNode   Node we are examining
     * @param bool $mode      If true, delete $nodeID if it's (r)sort
     * @param Node $parent    $root's parent
     * @param int  $indexWithinParent  $topNode is $parent's argument[$indexWithinParent]
     * TODO: We need a function under Language to determine if "xx" is an aggregate function.
     * TODO: We need a function under Language to determine if "xx" is a sort/rsort.
     */
    private static function fixUselessSorts(Node $topNode, $mode, Node $parent = null, $indexWithinParent = 0)
    {
        //////// Perform initial checking
        if ($topNode->getNodeType() !== Node::TYPE_FUNCTION) {
            return;
        }

        /** @var FunctionNode $topNode */

        //////// If conditions are true, make the change
        if ($mode
            && $parent !== null
            && in_array($topNode->getFunction(), array('sort', 'rsort'))) {
            /** @var FunctionNode $parent */
            $sortArguments = $topNode->getArguments();
            $parent->setArgument($indexWithinParent, $sortArguments[0]);  // orphanize the (r)sort
            $topNode = $parent;
        }

        $interestingFunctions = array(
            'average',
            'count',
            'delete',
            'max',
            'min',
            'rsort',
            'set',
            'sort',
            'sum'
        );

        //////// Set mode flag
        if (in_array($topNode->getFunction(), $interestingFunctions)) {
            $mode = true;   // if we subsequently see a sort below this, delete it
        } elseif ($topNode->getFunction() == 'slice') {
            $mode = false;  // A sort below a slice must not be removed
        }

        $topArguments = $topNode->getArguments();
        $count = count($topArguments);

        //////// Process the child nodes of $topNode
        for ($index = 0; $index < $count; $index++) {
            $kid = $topArguments[$index];
            self::fixUselessSorts($kid, $mode, $topNode, $index);
        }
    }
}
