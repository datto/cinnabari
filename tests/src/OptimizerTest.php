<?php

namespace Datto\Cinnabari\Tests;

use Mockery;
use Datto\Cinnabari\Optimizer;
use Datto\Cinnabari\Parser;
use PHPUnit_Framework_TestCase;

class OptimizerTest extends PHPUnit_Framework_TestCase
{
    public function testAverageSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'average', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'average', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $this->verify($input, $output);
    }

    public function testCountSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_PROPERTY, 'people')
        ));

        $this->verify($input, $output);
    }

    public function testDeleteSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'delete', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'delete', array(
            array(Parser::TYPE_PROPERTY, 'people')
        ));

        $this->verify($input, $output);
    }

    public function testMaxSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'max', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'max', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $this->verify($input, $output);
    }

    public function testMinSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'min', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'min', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $this->verify($input, $output);
    }

    public function testSetSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'set', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'set', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testSortSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'sort', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'sort', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $this->verify($input, $output);
    }

    public function testSumSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'sum', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'sum', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_PROPERTY, 'age')
        ));

        $this->verify($input, $output);
    }

    public function testCountSliceSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'slice', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PARAMETER, 'begin'),
                array(Parser::TYPE_PARAMETER, 'end')
            ))
        ));

        $output = $input;

        $this->verify($input, $output);
    }

    public function testCountFilterSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                ))
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                ))
            ))
        ));

        $this->verify($input, $output);
    }

    public function testCountSortFilter()
    {
        $input = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_FUNCTION, 'filter', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_FUNCTION, 'equal', array(
                        array(Parser::TYPE_PROPERTY, 'id'),
                        array(Parser::TYPE_PARAMETER, 'id')
                    ))
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                ))
            ))
        ));

        $this->verify($input, $output);
    }

    public function testObjectMaxSortMinSort()
    {
        $input = array(Parser::TYPE_OBJECT, array(
            'min' => array(Parser::TYPE_FUNCTION, 'min', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            'max' => array(Parser::TYPE_FUNCTION, 'max', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_OBJECT, array(
            'min' => array(Parser::TYPE_FUNCTION, 'min', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            'max' => array(Parser::TYPE_FUNCTION, 'max', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testCountNonmatchingSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'count', array(
            array(Parser::TYPE_FUNCTION, 'sort', array())
        ));

        $output = $input;

        $this->verify($input, $output);
    }

    public function testInsert()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = $input;

        $this->verify($input, $output);
    }

    public function testInsertSlice()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'slice', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PARAMETER, 'begin'),
                array(Parser::TYPE_PARAMETER, 'end')
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testInsertSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testInsertSortProperty()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'filter'),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'filter'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testInsertFilter()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_FUNCTION, 'equal',
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                )
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testInsertSliceSortFilter()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'slice', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_FUNCTION, 'filter', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_FUNCTION, 'equal', array(
                            array(Parser::TYPE_PROPERTY, 'id'),
                            array(Parser::TYPE_PARAMETER, 'id')
                        ))
                    )),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PARAMETER, 'begin'),
                array(Parser::TYPE_PARAMETER, 'end')
            )),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_PROPERTY, 'people'),
            array(Parser::TYPE_OBJECT, array(
                'id' => array(Parser::TYPE_PARAMETER, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testObjectInsertSlice()
    {
        $input = array(Parser::TYPE_OBJECT, array(
            'isInserted' => array(Parser::TYPE_FUNCTION, 'insert', array(
                array(Parser::TYPE_FUNCTION, 'slice', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PARAMETER, 'begin'),
                    array(Parser::TYPE_PARAMETER, 'end')
                )),
                array(Parser::TYPE_OBJECT, array(
                    'id' => array(Parser::TYPE_PARAMETER, 'id')
                ))
            ))
        ));

        $output = array(Parser::TYPE_OBJECT, array(
            'isInserted' => array(Parser::TYPE_FUNCTION, 'insert', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_OBJECT, array(
                    'id' => array(Parser::TYPE_PARAMETER, 'id')
                ))
            ))
        ));

        $this->verify($input, $output);
    }

    public function testNonmatchingInsertSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_PROPERTY, 'people'),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testNonmatchingInsertFilter()
    {
        $input = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_FUNCTION, 'slice', array()),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                ))
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'insert', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_FUNCTION, 'slice', array()),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'id'),
                    array(Parser::TYPE_PARAMETER, 'id')
                ))
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $this->verify($input, $output);
    }

    public function testGetFilterSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'get', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'age'),
                    array(Parser::TYPE_PARAMETER, 'age')
                ))
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'get', array(
            array(Parser::TYPE_FUNCTION, 'sort', array(
                array(Parser::TYPE_FUNCTION, 'filter', array(
                    array(Parser::TYPE_PROPERTY, 'people'),
                    array(Parser::TYPE_FUNCTION, 'equal', array(
                        array(Parser::TYPE_PROPERTY, 'age'),
                        array(Parser::TYPE_PARAMETER, 'age')
                    ))
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $this->verify($input, $output);
    }

    public function testGetSliceFilterSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'get', array(
            array(Parser::TYPE_FUNCTION, 'slice', array(
                array(Parser::TYPE_FUNCTION, 'filter', array(
                    array(Parser::TYPE_FUNCTION, 'sort', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_PROPERTY, 'id')
                    )),
                    array(Parser::TYPE_FUNCTION, 'equal', array(
                        array(Parser::TYPE_PROPERTY, 'age'),
                        array(Parser::TYPE_PARAMETER, 'age')
                    ))
                )),
                array(Parser::TYPE_PARAMETER, 'begin'),
                array(Parser::TYPE_PARAMETER, 'end')
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $output = array(Parser::TYPE_FUNCTION, 'get', array(
            array(Parser::TYPE_FUNCTION, 'slice', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_FUNCTION, 'filter', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_FUNCTION, 'equal', array(
                            array(Parser::TYPE_PROPERTY, 'age'),
                            array(Parser::TYPE_PARAMETER, 'age')
                        ))
                    )),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PARAMETER, 'begin'),
                array(Parser::TYPE_PARAMETER, 'end')
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $this->verify($input, $output);
    }

    public function testObjectGetFilterSort()
    {
        $input = array(Parser::TYPE_OBJECT, array(
            'minors' => array(Parser::TYPE_FUNCTION, 'get', array(
                array(Parser::TYPE_FUNCTION, 'filter', array(
                    array(Parser::TYPE_FUNCTION, 'sort', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_PROPERTY, 'id')
                    )),
                    array(Parser::TYPE_FUNCTION, 'less', array(
                        array(Parser::TYPE_PROPERTY, 'age'),
                        array(Parser::TYPE_PARAMETER, 'ageOfMajority')
                    ))
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            'adults' => array(Parser::TYPE_FUNCTION, 'get', array(
                array(Parser::TYPE_FUNCTION, 'filter', array(
                    array(Parser::TYPE_FUNCTION, 'sort', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_PROPERTY, 'id')
                    )),
                    array(Parser::TYPE_FUNCTION, 'greaterEqual', array(
                        array(Parser::TYPE_PROPERTY, 'age'),
                        array(Parser::TYPE_PARAMETER, 'ageOfMajority')
                    ))
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $output = array(Parser::TYPE_OBJECT, array(
            'minors' => array(Parser::TYPE_FUNCTION, 'get', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_FUNCTION, 'filter', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_FUNCTION, 'less', array(
                            array(Parser::TYPE_PROPERTY, 'age'),
                            array(Parser::TYPE_PARAMETER, 'ageOfMajority')
                        ))
                    )),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            )),
            'adults' => array(Parser::TYPE_FUNCTION, 'get', array(
                array(Parser::TYPE_FUNCTION, 'sort', array(
                    array(Parser::TYPE_FUNCTION, 'filter', array(
                        array(Parser::TYPE_PROPERTY, 'people'),
                        array(Parser::TYPE_FUNCTION, 'greaterEqual', array(
                            array(Parser::TYPE_PROPERTY, 'age'),
                            array(Parser::TYPE_PARAMETER, 'ageOfMajority')
                        ))
                    )),
                    array(Parser::TYPE_PROPERTY, 'id')
                )),
                array(Parser::TYPE_PROPERTY, 'id')
            ))
        ));

        $this->verify($input, $output);
    }

    public function testGetFilterNonmatchingSort()
    {
        $input = array(Parser::TYPE_FUNCTION, 'get', array(
            array(Parser::TYPE_FUNCTION, 'filter', array(
                array(Parser::TYPE_FUNCTION, 'sort', array()),
                array(Parser::TYPE_FUNCTION, 'equal', array(
                    array(Parser::TYPE_PROPERTY, 'age'),
                    array(Parser::TYPE_PARAMETER, 'age')
                ))
            )),
            array(Parser::TYPE_PROPERTY, 'id')
        ));

        $output = $input;

        $this->verify($input, $output);
    }

    private function verify($input, $expectedOutput)
    {
        $schema = \Mockery::mock('\Datto\Cinnabari\Schema');
        $schema->shouldReceive('getPrimaryKey')->andReturn('id');
        $optimizer = new Optimizer($schema);
        $actualOutput = $optimizer->optimize($input);

        $this->assertSame($expectedOutput, $actualOutput);
    }
}
