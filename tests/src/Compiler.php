<?php

namespace Datto\Cinnabari\Tests;

use Datto\Cinnabari\Exception\AbstractException;
use Datto\Cinnabari\Compiler;
use Datto\Cinnabari\Parser;
use Datto\Cinnabari\Lexer;
use Datto\Cinnabari\Format\Arguments;
use PHPUnit_Framework_TestCase;

/*
When joining from an origin table to a destination table:
 * Assume there is exactly one matching row in the destination table
 * If there is NO foreign key:
      Add the possibility of no matching rows in the destination table
 * If there is either (a) NO uniqueness constraint on the destination table, or (b) BOTH the origin and destination columns are nullable:
      Add the possibility of many matching rows
*/

class CompilerTest extends PHPUnit_Framework_TestCase
{
    private static function getPeopleScenario()
    {
        /*
        DROP DATABASE IF EXISTS `database`;
        CREATE DATABASE `database`;
        USE `database`;

        CREATE TABLE `People` (
            `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `Married` TINYINT UNSIGNED,
            `Age` TINYINT UNSIGNED,
            `Height` FLOAT,
            `Name` VARCHAR(256),
            `Email` VARCHAR(256)
        );

        INSERT INTO `People`
            (`Id`, `Married`, `Age`, `Height`, `Name`, `Email`) VALUES
            (1, 1, 21, 5.75, "Ann", "Ann@Example.Com"),
            (2, 0, 18, 5.5, "Becca", "becca@example.com"),
            (3, 1, 36, 5.9, "Carl", "carl@example.com"),
            (4, 0, 9, 4.25, "Dan", ""),
            (5, null, null, null, null, null);
        */

        return <<<'EOS'
{
    "classes": {
        "Database": {
            "people": ["Person", "People"]
        },
        "Person": {
            "id": [2, "Id"],
            "isMarried": [1, "Married"],
            "age": [2, "Age"],
            "height": [3, "Height"],
            "name": [4, "Name"],
            "email": [4, "Email"]
        }
    },
    "values": {
        "`People`": {
            "Id": ["`Id`", false],
            "Married": ["`Married`", true],
            "Age": ["`Age`", true],
            "Height": ["`Height`", true],
            "Name": ["`Name`", true],
            "Email": ["IF(`Email` <=> '', NULL, LOWER(`Email`))", true]
        }
    },
    "lists": {
        "People": ["`People`", "`Id`", false]
    }
}
EOS;
    }

    public function testMapValue()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.map(id)
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }
    
    public function testMapBasicObject()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.map({
    "id": id,
    "married": isMarried,
    "age": age,
    "height": height,
    "name": name
})
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Married` AS `1`,
    `0`.`Age` AS `2`,
    `0`.`Height` AS `3`,
    `0`.`Name` AS `4`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]]['id'] = (integer)$row[0];
    $output[$row[0]]['married'] = isset($row[1]) ? (boolean)$row[1] : null;
    $output[$row[0]]['age'] = isset($row[2]) ? (integer)$row[2] : null;
    $output[$row[0]]['height'] = isset($row[3]) ? (float)$row[3] : null;
    $output[$row[0]]['name'] = $row[4];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testMapAdvancedObject()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.map({
    "name": name,
    "contact": {
        "email": email
    }
})
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Name` AS `1`,
    IF(`0`.`Email` <=> '', NULL, LOWER(`0`.`Email`)) AS `2`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]]['name'] = $row[1];
    $output[$row[0]]['contact']['email'] = $row[2];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = 'people.filter(age = :0).map(id)';

        $arguments = array(
            21
        );

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` <=> :0)
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['0']
);
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testAdvancedFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = 'people.filter(
            age = :null
            or (not :true or :ageA < age)
            and age <= :ageB
            and age != :ageC
            and age <= :ageD
            and age < :ageE
        ).map(id)';

        $arguments = array(
            'null' => null,
            'true' => true,
            'ageA' => 20,
            'ageB' => 21,
            'ageC' => 22,
            'ageD' => 23,
            'ageE' => 24
        );

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    WHERE
        (
            (`0`.`Age` <=> :0)
            OR (
                (
                    (
                        (
                            (
                                (NOT :1) OR (:2 < `0`.`Age`)
                            ) AND (`0`.`Age` <= :3)
                        ) AND (NOT (`0`.`Age` <=> :4))
                    ) AND (`0`.`Age` <= :5)
                ) AND (`0`.`Age` < :6)
            )
        )
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['null'],
    $input['true'],
    $input['ageA'],
    $input['ageB'],
    $input['ageC'],
    $input['ageD'],
    $input['ageE']
);
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testSort()
    {
        $scenario = self::getPeopleScenario();

        $method = 'people.sort(age).map(id)';

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    ORDER BY `0`.`Age` ASC
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testAdvancedSort()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
people.sort(name.first).map(age)
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Age` AS `1`
    FROM `People` AS `0`
    INNER JOIN `Names` AS `1` ON `0`.`Name` <=> `1`.`Id`
    ORDER BY `1`.`First` ASC
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.sort(age).slice(:start, :stop).map(id)
EOS;

        $arguments = array(
            'start' => 0,
            'stop' => 10
        );

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    ORDER BY `0`.`Age` ASC
    LIMIT :0, :1
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['start'],
    $input['stop'] - $input['start']
);
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    private static function getFriendsScenario()
    {
        /*
        DROP DATABASE IF EXISTS `database`;
        CREATE DATABASE `database`;
        USE `database`;

        CREATE TABLE `Friends` (
            `Person` INT UNSIGNED,
            `Friend` INT UNSIGNED
        );

        INSERT INTO `Friends`
            (`Person`, `Friend`) VALUES
            (0, 1),
            (1, 0),
            (1, 2),
            (2, null),
            (null, null);
        */

        return <<<'EOS'
{
    "classes": {
        "Database": {
            "people": ["Person", "Friends"]
        },
        "Person": {
            "id": [2, "Person"],
            "friends": ["Person", "Friends"]
        }
    },
    "values": {
        "`Friends`": {
            "Person": ["`Person`", true]
        }
    },
    "lists": {
        "Friends": ["`Friends`", "`Person`", true]
    },
    "connections": {
        "`Friends`": {
            "Friends": ["`Friends`", "`0`.`Friend` <=> `1`.`Person`", "`Person`", true, true]
        }
    }
}
EOS;
    }

    public function testMapDepthZero()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
people.map(id)
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Person` AS `0`
    FROM `Friends` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    if (isset($row[0])) {
        $output[$row[0]] = isset($row[0]) ? (integer)$row[0] : null;
    }
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testMapDepthOne()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
people.map({
    "id": id,
    "friends": friends.map(id)
})
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Person` AS `0`,
    `1`.`Person` AS `1`
    FROM `Friends` AS `0`
    LEFT JOIN `Friends` AS `1` ON `0`.`Friend` <=> `1`.`Person`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    if (isset($row[0])) {
        $output[$row[0]]['id'] = isset($row[0]) ? (integer)$row[0] : null;

        if (isset($row[1])) {
            $output[$row[0]]['friends'][$row[1]] = isset($row[1]) ? (integer)$row[1] : null;
        }
    }
}

$output = isset($output) ? array_values($output) : array();

foreach ($output as &$x0) {
    $x0['friends'] = isset($x0['friends']) ? array_values($x0['friends']) : array();
}
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testMapDepthTwo()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
people.map({
    "id": id,
    "friends": friends.map({
        "id": id,
        "friends": friends.map(id)
    })
})
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
SELECT
    `0`.`Person` AS `0`,
    `1`.`Person` AS `1`,
    `2`.`Person` AS `2`
    FROM `Friends` AS `0`
    LEFT JOIN `Friends` AS `1` ON `0`.`Friend` <=> `1`.`Person`
    LEFT JOIN `Friends` AS `2` ON `1`.`Friend` <=> `2`.`Person`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    if (isset($row[0])) {
        $output[$row[0]]['id'] = isset($row[0]) ? (integer)$row[0] : null;

        if (isset($row[1])) {
            $output[$row[0]]['friends'][$row[1]]['id'] = isset($row[1]) ? (integer)$row[1] : null;

            if (isset($row[2])) {
                $output[$row[0]]['friends'][$row[1]]['friends'][$row[2]] = isset($row[2]) ? (integer)$row[2] : null;
            }
        }
    }
}

$output = isset($output) ? array_values($output) : array();

foreach ($output as &$x1) {
    $x1['friends'] = isset($x1['friends']) ? array_values($x1['friends']) : array();

    foreach ($x1['friends'] as &$x0) {
        $x0['friends'] = isset($x0['friends']) ? array_values($x0['friends']) : array();
    }
}
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    private static function getRelationshipsScenario()
    {
        /*
        DROP DATABASE IF EXISTS `database`;
        CREATE DATABASE `database`;
        USE `database`;

        CREATE TABLE `Names` (
            `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `First` VARCHAR(256) NOT NULL,
            `Last` VARCHAR(256) NOT NULL
        );

        CREATE TABLE `PhoneNumbers` (
            `Person` INT UNSIGNED NOT NULL,
            `PhoneNumber` BIGINT UNSIGNED NOT NULL,
            INDEX (`Person`)
        );

        CREATE TABLE `People` (
            `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `Name` INT UNSIGNED NOT NULL,
            `Age` TINYINT UNSIGNED NOT NULL,
            `City` VARCHAR(256) NOT NULL,
            CONSTRAINT `fk_People_Name__Names_Id` FOREIGN KEY (`Name`) REFERENCES `Names` (`Id`),
            CONSTRAINT `fk_People_Id__PhoneNumbers_Person` FOREIGN KEY (`Id`) REFERENCES `PhoneNumbers` (`Person`)
        );

        CREATE TABLE `Spouses` (
            `Person` INT UNSIGNED NOT NULL,
            `Spouse` INT UNSIGNED NOT NULL,
            CONSTRAINT `uc_Spouses_Person` UNIQUE (`Person`),
            CONSTRAINT `fk_Spouses_Spouse__People_Id` FOREIGN KEY (`Spouse`) REFERENCES `People` (`Id`)
        );

        CREATE TABLE `Friends` (
            `Person` INT UNSIGNED NOT NULL,
            `Friend` INT UNSIGNED NOT NULL
        );

        INSERT INTO `Names`
            (`Id`, `First`, `Last`) VALUES
            (1, 'Ann', 'Adams'),
            (2, 'Bob', 'Baker'),
            (3, 'Carl', 'Clay'),
            (4, 'Mary', 'May');

        INSERT INTO `PhoneNumbers`
            (`Person`, `PhoneNumber`) VALUES
            (1, 12025550164),
            (1, 12025550182),
            (2, 12025550110),
            (3, 12025550194),
            (4, 12025550180);

        INSERT INTO `People`
            (`Id`, `Name`, `Age`, `City`) VALUES
            (1, 1, 21, 'San Francisco'),
            (2, 2, 28, 'Boston'),
            (3, 3, 18, 'Baltimore'),
            (4, 4, 26, 'San Antonio');

        INSERT INTO `Spouses`
            (`Person`, `Spouse`) VALUES
            (2, 4),
            (4, 2);

        INSERT INTO `Friends`
            (`Person`, `Friend`) VALUES
            (1, 2),
            (1, 3),
            (3, 1);
        */

        return <<<'EOS'
{
    "classes": {
        "Database": {
            "people": ["Person", "People"]
        },
        "Person": {
            "name": ["Name", "Name"],
            "age": [2, "Age"],
            "phones": [2, "Phones", "Number"],
            "spouse": ["Person", "Spouse", "Person"],
            "friends": ["Friend", "Friends"]
        },
        "Name": {
            "first": [4, "First"],
            "last": [4, "Last"]
        },
        "Friend": {
            "id": [2, "Id"]
        }
    },
    "values": {
        "`People`": {
            "Age": ["`Age`", false],
            "City": ["`City`", false]
        },
        "`Names`": {
            "First": ["`First`", false],
            "Last": ["`Last`", false]
        },
        "`PhoneNumbers`": {
            "Number": ["`PhoneNumber`", false]
        },
        "`Friends`": {
            "Id": ["`Friend`", false]
        }
    },
    "lists": {
        "People": ["`People`", "`Id`", false]
    },
    "connections": {
        "`People`": {
            "Name": ["`Names`", "`0`.`Name` <=> `1`.`Id`", "`Id`", false, false],
            "Phones": ["`PhoneNumbers`", "`0`.`Id` <=> `1`.`Person`", "`Person`", false, true],
            "Spouse": ["`Spouses`", "`0`.`Id` <=> `1`.`Person`", "`Person`", true, false],
            "Friends": ["`Friends`", "`0`.`Id` <=> `1`.`Person`", "`Person`", true, true]
        },
        "`Spouses`": {
            "Person": ["`People`", "`0`.`Spouse` <=> `1`.`Id`", "`Id`", true, true]
        }
    }
}
EOS;
    }

    public function testMapMatch()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
people.filter(match(name.first, :firstName)).map(age)
EOS;

        $arguments = array(
            'firstName' => '^[A-Z]a..$'
        );

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Age` AS `1`
    FROM `People` AS `0`
    INNER JOIN `Names` AS `1` ON `0`.`Name` <=> `1`.`Id`
    WHERE (`1`.`First` REGEXP BINARY :0)
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['firstName']
);
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }
    
    public function testMapMatchPropertyPath()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
people.filter(match(name.first, :regex)).map(age)
EOS;

        $arguments = array(
            'regex' => '^'
        );

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Age` AS `1`
    FROM `People` AS `0`
    INNER JOIN `Names` AS `1` ON `0`.`Name` <=> `1`.`Id`
    WHERE (`1`.`First` REGEXP BINARY :0)
EOS;
        
        $phpInput = <<<'EOS'
$output = array(
    $input['regex']
);
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }
    
    public function testMapParameterPath()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
people.filter(match(name.:a, :regex)).map(age)
EOS;
        $arguments = array(
            'regex' => '^',
            'a' => 'foo'
        );

        $this->verifyException(
            $scenario,
            $method,
            $arguments,
            Arguments::ERROR_WRONG_INPUT_TYPE,
            array('name' => 'a', 'userType' => 'string', 'neededType' => 'integer')
        );
    }
    
    public function testMapParameterPropertyPath()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
people.filter(match(name.:a.first, :regex)).map(age)
EOS;

        $arguments = array(
            'regex' => '^',
            'a' => 'foo'
        );

        $pathInformation = array(
            5,
            array(2, 'name'),
            array(1, 'a'),
            array(2, 'first')
        );

        $matchFunction = array(
            3,
            'match',
            $pathInformation,
            array(1, 'regex')
        );

        $this->verifyException(
            $scenario,
            $method,
            $arguments,
            Compiler::ERROR_BAD_FILTER_EXPRESSION,
            array(
                'class' => 'Person',
                'table' => 0,
                'arguments' => $matchFunction
            )
        );
    }

    public function testDelete()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.delete()
EOS;

        $arguments = array();

        $mysql = <<<'EOS'
DELETE
    FROM `People`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
$output = null;
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testDeleteFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.filter(age < :age).delete()
EOS;

        $arguments = array(
            'age' => 21
        );

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    WHERE `People`.`Age` < :0
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['age']
);
EOS;

        $phpOutput = <<<'EOS'
$output = null;
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    /**
     * Note: MySQL requires ":start = 0". No other value is possible in MySQL!
     * When a user supplies a non-zero start value, Cinnabari should simply
     * reject the request and provide an explanation.
     *
     * Note: MySQL behavior is unpredictable when a "LIMIT" clause is used
     * without an "ORDER BY" clause. That's why the "sort" method and the
     * "slice" method are tested together here.
     *
     * Because of this unpredictable behavior, Cinnabari should--at some point
     * in the future--insert an implicit "sort" function (using the identifier
     * expression) when a user-supplied query lacks an explicit "sort" function.
     *
     * The following unit test, however, is valid and will always be valid:
     */
    public function testDeleteSortSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.sort(age).slice(:start, :stop).delete()
EOS;

        $arguments = array(
            'start' => 0,
            'stop' => 2
        );

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    ORDER BY `People`.`Age` ASC
    LIMIT :0
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['stop'] - $input['start']
);
EOS;

        $phpOutput = <<<'EOS'
$output = null;
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    public function testDeleteFilterSortSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
people.filter(:age <= age).sort(age).slice(:start, :stop).delete()
EOS;

        $arguments = array(
            'age' => 18,
            'start' => 0,
            'stop' => 2
        );

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    WHERE :0 <= `People`.`Age`
    ORDER BY `People`.`Age` ASC
    LIMIT :1
EOS;

        $phpInput = <<<'EOS'
$output = array(
    $input['age'],
    $input['stop'] - $input['start']
);
EOS;

        $phpOutput = <<<'EOS'
$output = null;
EOS;

        $this->verifyResult($scenario, $method, $arguments, $mysql, $phpInput, $phpOutput);
    }

    private function verifyResult($scenarioJson, $method, $arguments, $mysql, $phpInput, $phpOutput)
    {
        $actual = self::translate($scenarioJson, $method, $arguments);
        $expected = array($mysql, $phpInput, $phpOutput);

        $this->assertSame(
            self::standardize($expected),
            self::standardize($actual)
        );
    }

    private function verifyException($scenarioJson, $method, $arguments, $code, $data)
    {
        $expected = array(
            'code' => $code,
            'data' => $data
        );

        try {
            self::translate($scenarioJson, $method, $arguments);
            $actual = null;
        } catch (AbstractException $exception) {
            $actual = array(
                'code' => $exception->getCode(),
                'data' => $exception->getData()
            );
        }

        $this->assertSame($expected, $actual);
    }

    private static function translate($scenarioJson, $method, $arguments)
    {
        $scenario = json_decode($scenarioJson, true);

        $lexer = new Lexer();
        $parser = new Parser();
        $schema = new Schema($scenario);
        $compiler = new Compiler($schema);

        $tokens = $lexer->tokenize($method);
        $request = $parser->parse($tokens);
        return $compiler->compile($request, $arguments);
    }

    private static function standardize($artifact)
    {
        list($mysql, $phpInput, $phpOutput) = $artifact;

        return array(
            self::standardizeMysql($mysql),
            self::standardizePhp($phpInput),
            self::standardizePhp($phpOutput)
        );
    }

    private static function standardizePhp($php)
    {
        return preg_replace('~\t~', '    ', $php);
    }

    private static function standardizeMysql($mysql)
    {
        $mysql = preg_replace('~\s+~', ' ', $mysql);

        // Remove any unnecessary whitespace after an opening parenthesis
        // Example: "( `" => "(`"
        // Example: "( (" => "(("
        // Example: "( :" => "(:"
        $mysql = preg_replace('~\( (?=`|\(|:)~', '(', $mysql);

        // Remove any unnecessary whitespace before a closing parenthesis
        // Example: "` )" => "`)"
        // Example: ") )" => "))"
        $mysql = preg_replace('~(?<=`|\)) \)~', ')', $mysql);

        return $mysql;
    }
}
