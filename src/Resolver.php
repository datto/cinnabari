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
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari;

class Resolver
{
    const VALUE_NULL = 0;
    const VALUE_BOOLEAN = 1;
    const VALUE_INTEGER = 2;
    const VALUE_FLOAT = 3;
    const VALUE_STRING = 4;
    // array
    // object
    // function

    /** @var array */
    private $schema;

    public function __construct($schema)
    {
        $this->schema = $schema;
    }

    public function resolve($request)
    {
        return $request;
    }
}

/*
tokens:
    PARAMETER
    PROPERTY
    FUNCTION
    TYPE_OBJECT

types:
    array(TYPE_VALUE, null) # null, boolean, integer, float, string
    array(TYPE_ARRAY, <type>)
    array(TYPE_OBJECT, array('key' => <type>, ...))
    array(TYPE_FUNCTION, array(<type>, ...), <type>)
    array(TYPE_OR, array(<type>, ...))
*/