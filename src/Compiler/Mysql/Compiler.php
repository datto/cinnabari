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

namespace Datto\Cinnabari\Compiler\Mysql;

use Datto\Cinnabari\Translator\Clauses\Clause;
use Datto\Cinnabari\Translator\Clauses\OrderBy;
use Datto\Cinnabari\Translator\Nodes\Join;
use Datto\Cinnabari\Translator\Nodes\Node;
use Datto\Cinnabari\Translator\Nodes\Select;
use Datto\Cinnabari\Translator\Nodes\Table;
use Datto\Cinnabari\Translator\Nodes\Value;
use Exception;

class Compiler
{
    public function compile(Select $select)
    {
        $valuesArray = $this->getValues($select);
        $valuesString = "\n\t" . implode(",\n\t", $valuesArray);

        $clausesArray = array_merge(
            $this->getTableClauses($select),
            $this->getRestrictionClauses($select)
        );

        $clausesString = "\n" . implode("\n", $clausesArray);

        return "SELECT{$valuesString}{$clausesString}";
    }

    private function getValues(Select $select)
    {
        $output = array();

        $values = $select->getValues();

        foreach ($values as $alias => $value) { /** @var Value $value */
            $output[] = $this->getValue($value, $alias);
        }

        return $output;
    }

    private function getValue(Value $value, $id)
    {
        $contextId = $value->getContext();
        $contextAlias = self::quote($contextId);

        $abstractExpression = $value->getExpression();
        $concreteExpression = $this->getConcreteExpression($abstractExpression, $contextAlias);

        $alias = self::quote($id);

        return "{$concreteExpression} AS {$alias}";
    }

    private function getTableClauses(Select $select)
    {
        $output = array();

        $tables = $select->getTables();

        foreach ($tables as $alias => $node) { /** @var Node $node */
            $type = $node->getNodeType();

            switch ($type) {
                case Node::TYPE_TABLE: /** @var Table $node */
                    $clause = $this->getFromClause($node, $alias);
                    break;

                case Node::TYPE_JOIN: /** @var Join $node */
                    $clause = $this->getJoinClause($node, $alias);
                    break;

                default:
                    throw new Exception('Unsupported node');
            }

            $output[] = $clause;
        }

        return $output;
    }

    private function getFromClause(Table $table, $id)
    {
        $name = $table->getName();
        $alias = self::quote($id);

        return "FROM {$name} AS {$alias}";
    }

    private function getJoinClause(Join $join, $bId)
    {
        $aId = $join->getContext();
        $aAlias = self::quote($aId);

        $bName = $join->getTable();
        $bAlias = self::quote($bId);

        $abstractCondition = $join->getCondition();
        $condition = $this->getConcreteCondition($abstractCondition, $aAlias, $bAlias);

        // TODO: determine join type (e.g. "INNER" vs "LEFT")
        return "LEFT JOIN {$bName} AS {$bAlias} ON {$condition}";
    }

    private function getRestrictionClauses(Select $select)
    {
        $output = array();

        $clauses = $select->getClauses();

        foreach ($clauses as $clause) { /** @var Clause $clause */
            $type = $clause->getNodeType();

            $text = null;

            switch ($type) {
                case Clause::TYPE_WHERE:
                    // Todo
                    break;

                case Clause::TYPE_GROUP_BY:
                    // Todo
                    break;

                case Clause::TYPE_HAVING:
                    // Todo
                    break;

                case Clause::TYPE_ORDER_BY: /** @var OrderBy $clause */
                    $text = $this->getOrderByClause($clause);
                    break;

                case Clause::TYPE_LIMIT:
                    // Todo
                    break;

                default:
                    throw new Exception('Unsupported clause');
            }

            $output[] = $text;
        }

        return $output;
    }

    private function getOrderByClause(OrderBy $clause)
    {
        $contextId = $clause->getContext();
        $contextAlias = self::quote($contextId);

        $abstractExpression = $clause->getExpression();
        $concreteExpression = $this->getConcreteExpression($abstractExpression, $contextAlias);

        $isAscending = $clause->isAscending();

        if ($isAscending) {
            $direction = 'ASC';
        } else {
            $direction = 'DESC';
        }

        return "ORDER BY {$concreteExpression} {$direction}";
    }

    private function getConcreteExpression($expression, $context)
    {
        return preg_replace('~`.*?`~', "{$context}.\$0", $expression);
    }

    private function getConcreteCondition($condition, $a, $b)
    {
        return str_replace(array('`0`', '`1`'), array($a, $b), $condition);
    }

    private static function quote($id)
    {
        return "`{$id}`";
    }
}
