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

namespace Datto\Cinnabari\AbstractRequest\Nodes;

use Datto\Cinnabari\AbstractRequest\Node;

class ParameterNode extends Node
{
    /** @var string */
    private $name;

    /**
     * @param string $function
     * @param mixed $dataType
     */
    public function __construct($function, $dataType = null)
    {
        parent::__construct(self::TYPE_PARAMETER, $dataType);

        $this->name = $function;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
