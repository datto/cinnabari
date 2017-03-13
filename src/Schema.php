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

namespace Datto\Cinnabari;

use \ArrayAccess;
use \Iterator;

/**
 * A class to encapsulate the Cinnabari schema, and provide methods to process it
 */
class Schema implements ArrayAccess, Iterator
{

    const TYPE_BOOLEAN = 1;
    const TYPE_INTEGER = 2;
    const TYPE_STRING = 4;

    /**
     * @var array The raw schema data
     */
    private $data;

    /**
     * @var string The root class for the class tree
     */
    private $classRoot;

    /**
     * Constructs a new Schema object
     *
     * @param array $data       The raw schema as loaded from a schema file
     * @param string $classRoot The root class for the class tree; defaults to 'Database'
     */
    public function __construct(array $data, $classRoot = 'Database')
    {
        $this->data = $data;
        $this->classRoot = $classRoot;
    }

    /**
     * Determines whether the passed property name (in dot-separated notation) exists
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function propertyExists($propertyName)
    {
        return ($this->getProperty($propertyName) !== null);
    }

    /**
     * Gets the description for the passed property path (in dot-separated notation)
     *
     * @param string $path
     * @param boolean $first    If set to true, the class root will be prepended to $path
     *
     * @return array|null
     */
    public function getProperty($path, $first = true)
    {
        $parts = \explode('.', $path);

        if ($first) {
            \array_unshift($parts, $this->classRoot);
        }

        // We always need a class name and a property name
        if (count($parts) < 2) {
            return null;
        }

        $className = $parts[0];
        $propertyName = $parts[1];
        $remainder = \array_slice($parts, 2);

        if (!isset($this->data['classes'][$className], $this->data['classes'][$className][$propertyName])) {
            return null;
        }

        $property = $this->data['classes'][$className][$propertyName];
        $currentType = $property[0];
        if (count($remainder)) {
            if (\in_array($currentType, array(self::TYPE_BOOLEAN, self::TYPE_INTEGER, self::TYPE_STRING))) {
                return null;
            }

            // Attach the referenced class to the front of the remaining path and recurse
            \array_unshift($remainder, $currentType);
            return $this->getProperty(\implode('.', $remainder), false);
        }

        return $property;
    }

    /**
     * Gets the primary key for the passed list
     *
     * @param string $list
     *
     * @return string
     */
    public function getPrimaryKey($list)
    {
        return 'id';
    }

    /**
     * Implementing methods for the various accessor interfaces
     */

    /**
     * @inheritDoc
     */
    public function current()
    {
        return \current($this->data);
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        return \next($this->data);
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return \key($this->data);
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        $keys = \array_keys($this->data);
        $current = \key($this->data);
        $last = end($keys);
        return ($current !== end($keys));
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        \reset($this->data);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return (\array_key_exists($offset, $this->data));
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return ($this->offsetExists($offset)) ? $this->data[$offset] : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }


}