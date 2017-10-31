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

use SpencerMortensen\Parser\Core\Rules as CoreRules;
use Datto\Cinnabari\Translator\Parser\Rules\FunctionRule;
use Datto\Cinnabari\Translator\Parser\Rules\PropertyRule;

class Rules extends CoreRules
{
    protected function createRule($name, $type, $definition)
    {
        switch ($type) {
            case 'function':
                return $this->createFunctionRule($name, $definition);

            case 'property':
                return $this->createPropertyRule($name);

            default:
                return parent::createRule($name, $type, $definition);
        }
    }

    private function createFunctionRule($ruleName, $definition)
    {
        $parts = explode(' ', $definition);
        $function = array_shift($parts);
        $arguments = $this->getRules($parts);
        $callable = $this->getCallable($ruleName);

        return new FunctionRule($ruleName, $function, $arguments, $callable);
    }

    private function createPropertyRule($ruleName)
    {
        $callable = $this->getCallable($ruleName);

        return new PropertyRule($ruleName, $callable);
    }
}
