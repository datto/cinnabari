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

namespace Datto\Cinnabari\Tests;

use \Mockery;
use Datto\Cinnabari\Schema;

/**
 * @coversDefaultClass \Datto\Cinnabari\Schema
 */
class SchemaTest extends \PHPUnit_Framework_TestCase
{

    /**
     * DataProvider for testGetProperty and testPropertyExists
     *
     * @return array
     */
    public function getPropertyProvider()
    {
        return array(
            'Simple path' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name')
                        )
                    )
                ),
                'propertyName' => 'clients.name',
                'expected' => array(Schema::TYPE_STRING, 'Name')
            ),
            'Simple path 2' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name')
                        )
                    )
                ),
                'propertyName' => 'clients.id',
                'expected' => array(Schema::TYPE_INTEGER, 'Id'),
            ),
            'Deep path' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name'),
                            'reseller' => array('Reseller', 'Reseller'),
                        ),
                        'Reseller' => array(
                            'name' => array(Schema::TYPE_STRING, 'Name')
                        )
                    )
                ),
                'propertyName' => 'clients.reseller.name',
                'expected' => array(Schema::TYPE_STRING, 'Name')
            ),
            'Unknown path' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name'),
                            'reseller' => array('Reseller', 'Reseller'),
                        ),
                    )
                ),
                'propertyName' => 'clients.description',
                'expected' => null
            ),
            'Unknown path, empty path' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name'),
                            'reseller' => array('Reseller', 'Reseller'),
                        ),
                    )
                ),
                'propertyName' => '',
                'expected' => null
            ),
            'Unknown path, too long' => array(
                'data' => array(
                    'classes' => array(
                        'Database' => array(
                            'clients' => array('Client', 'Clients')
                        ),
                        'Client' => array(
                            'id' => array(Schema::TYPE_INTEGER, 'Id'),
                            'name' => array(Schema::TYPE_STRING, 'Name'),
                            'reseller' => array('Reseller', 'Reseller'),
                        ),
                    )
                ),
                'propertyName' => 'clients.description.name',
                'expected' => null
            ),
        );
    }

    /**
     * @dataProvider getPropertyProvider
     *
     * @covers ::getProperty
     *
     * @param array $data
     * @param string $propertyName
     * @param mixed $expected
     */
    public function testGetProperty(array $data, $propertyName, $expected)
    {
        $schema = new Schema($data);
        $this->assertEquals($expected, $schema->getProperty($propertyName));
    }

    /**
     * @dataProvider getPropertyProvider
     *
     * @covers ::propertyExists
     *
     * @param array $data
     * @param string $propertyName
     * @param mixed $expected
     */
    public function testPropertyExists(array $data, $propertyName, $expected)
    {
        $schema = new Schema($data);
        $this->assertEquals(($expected !== null), $schema->propertyExists($propertyName));
    }

    /**
     * @covers ::__construct
     * @covers ::offsetExists
     * @covers ::offsetGet
     * @covers ::offsetSet
     * @covers ::offsetUnset
     */
    public function testArrayAccess()
    {
        $data = array(
            'test' => array(1, 2, 3),
            'value' => null,
            'one more' => 'five'
        );

        $schema = new Schema($data);

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $schema[$key]);
        }

        unset($schema['test']);
        unset($data['test']);

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $schema[$key]);
        }

        $schema['test 2'] = 'tested';
        $data['test 2'] = 'tested';
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $schema[$key]);
        }
    }

    /**
     * @covers ::__construct
     * @covers ::current
     * @covers ::next
     * @covers ::key
     * @covers ::valid
     * @covers ::rewind
     */
    public function testIterator()
    {
        $data = array(
            'test' => array(1, 2, 3),
            'value' => null,
            'one more' => 'five'
        );

        $schema = new Schema($data);

        foreach ($schema as $key => $value) {
            $this->assertEquals($value, $data[$key]);
        }
    }
}