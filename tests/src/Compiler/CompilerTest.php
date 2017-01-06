<?php

namespace Datto\Cinnabari\Tests\Compiler;

use Datto\Cinnabari\Cinnabari;
use PHPUnit_Framework_TestCase;

class CompilerTest extends PHPUnit_Framework_TestCase
{
    public function testGetValue()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    id
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetDivision()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    age / age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    (`0`.`Age` / `0`.`Age`) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = isset($row[1]) ? (float)$row[1] : null;
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetBasicObject()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "id": id,
        "married": isMarried,
        "age": age,
        "height": height,
        "name": name
    }
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetAdvancedObject()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "name": name,
        "contact": {
            "email": email
        }
    }
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    filter(people, age = :0),
    id
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` <=> :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('0', $input)) {
    throw new Exception('0', 1);
}

if (is_integer($input['0'])) {
    $output = array(
        ':0' => $input['0']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetFilterWithReusedParameter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    filter(people, (age < :0) or (age = :0)),
    id
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    WHERE ((`0`.`Age` < :0) OR (`0`.`Age` <=> :1))
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('0', $input)) {
    throw new Exception('0', 1);
}

if (is_integer($input['0'])) {
    $output = array(
        ':0' => $input['0'],
        ':1' => $input['0']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    /*
        public function testGetFilterBoolean()
        {
            $scenario = self::getPeopleScenario();

            $method = <<<'EOS'
    get(
        filter(people, isMarried and (age < :age)),
        id
    )
    EOS;

            $mysql = <<<'EOS'
    SELECT
        `0`.`Id` AS `0`
        FROM `People` AS `0`
        WHERE (`0`.`Married` AND (`0`.`Age` < :0))
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

            $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
        }
    */

    public function testGetAdvancedFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    filter(
        people,
        age = :null
        or (not :true or :ageA < age)
        and age <= :ageB
        and age != :ageC
        and age <= :ageD
        and age < :ageE
    ),
    id
)
EOS;

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
if (!array_key_exists('null', $input)) {
    throw new Exception('null', 1);
}

if (!array_key_exists('true', $input)) {
    throw new Exception('true', 1);
}

if (!array_key_exists('ageA', $input)) {
    throw new Exception('ageA', 1);
}

if (!array_key_exists('ageB', $input)) {
    throw new Exception('ageB', 1);
}

if (!array_key_exists('ageC', $input)) {
    throw new Exception('ageC', 1);
}

if (!array_key_exists('ageD', $input)) {
    throw new Exception('ageD', 1);
}

if (!array_key_exists('ageE', $input)) {
    throw new Exception('ageE', 1);
}

if (
    true && (
        is_bool($input['true']) && (
            (
                is_integer($input['ageA']) && (
                    (
                        is_integer($input['ageB']) && (
                            is_integer($input['ageC']) && (
                                (
                                    is_integer($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                ) || (
                                    is_float($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                )
                            )
                        )
                    ) || (
                        is_float($input['ageB']) && (
                            is_integer($input['ageC']) && (
                                (
                                    is_integer($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                ) || (
                                    is_float($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                )
                            )
                        )
                    )
                )
            ) || (
                is_float($input['ageA']) && (
                    (
                        is_integer($input['ageB']) && (
                            is_integer($input['ageC']) && (
                                (
                                    is_integer($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                ) || (
                                    is_float($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                )
                            )
                        )
                    ) || (
                        is_float($input['ageB']) && (
                            is_integer($input['ageC']) && (
                                (
                                    is_integer($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                ) || (
                                    is_float($input['ageD']) && (is_integer($input['ageE']) || is_float($input['ageE']))
                                )
                            )
                        )
                    )
                )
            )
        )
    )
) {
    $output = array(
        ':0' => $input['null'],
        ':1' => $input['true'],
        ':2' => $input['ageA'],
        ':3' => $input['ageB'],
        ':4' => $input['ageC'],
        ':5' => $input['ageD'],
        ':6' => $input['ageE']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function getSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    sort(people, age),
    id
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetAdvancedSort()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
get(
    sort(people, name.first),
    age
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetSliceSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    slice(sort(people, age), :start, :stop),
    id
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`
    FROM `People` AS `0`
    ORDER BY `0`.`Age` ASC
    LIMIT :0, :1
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[0];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGet()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
get(
    people,
    id
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetGet()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "id": id,
        "friends": get(
            friends,
            id
        )
    }
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetGetGet()
    {
        $scenario = self::getFriendsScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "id": id,
        "friends": get(
            friends,
            {
                "id": id,
                "friends": get(
                    friends,
                    id
                )
            }
        )
    }
)
EOS;

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

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetFilterMatch()
    {
        $scenario = self::getRelationshipsScenario();

        $method = <<<'EOS'
get(
    filter(people, match(name.first, :firstName)),
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Age` AS `1`
    FROM `People` AS `0`
    INNER JOIN `Names` AS `1` ON `0`.`Name` <=> `1`.`Id`
    WHERE (`1`.`First` REGEXP BINARY :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('firstName', $input)) {
    throw new Exception('firstName', 1);
}

if (is_string($input['firstName'])) {
    $output = array(
        ':0' => $input['firstName']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = (integer)$row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetLowercase()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    lowercase(name)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    LOWER(`0`.`Name`) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = $row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetUppercase()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    uppercase(name)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    UPPER(`0`.`Name`) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = $row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetLength()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    length(name)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    CHAR_LENGTH(`0`.`Name`) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = isset($row[1]) ? (integer)$row[1] : null;
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetPlus()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    name + name
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    CONCAT(`0`.`Name`, `0`.`Name`) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = $row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetSubstring()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
get(
    people,
    substring(name, :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    SUBSTRING(`0`.`Name` FROM :0 FOR :1) AS `1`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => 1 + max($input['start'], 0),
        ':1' => $input['stop'] - max($input['start'], 0)
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]] = $row[1];
}

$output = isset($output) ? array_values($output) : array();
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCount()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    people
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`Id`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    filter(people, age < :minimumAge)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`Id`) AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (is_integer($input['minimumAge']) || is_float($input['minimumAge'])) {
    $output = array(
        ':0' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    sort(people, age)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`Id`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    slice(people, :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSortFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    sort(filter(people, age < :minimumAge), age)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`Id`) AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (is_integer($input['minimumAge']) || is_float($input['minimumAge'])) {
    $output = array(
        ':0' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountFilterSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    filter(sort(people, age), age < :minimumAge)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`Id`) AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (is_integer($input['minimumAge']) || is_float($input['minimumAge'])) {
    $output = array(
        ':0' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSliceFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    slice(filter(people, age < :minimumAge), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            WHERE (`0`.`Age` < :0)
            ORDER BY `0`.`Id` ASC
            LIMIT :1, :2
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['minimumAge'],
        ':1' => max($input['start'], 0),
        ':2' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountFilterSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    filter(slice(people, :start, :stop), age < :minimumAge)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`1`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`,
            TRUE AS `1`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
    WHERE (`0`.`0` < :2)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (
    is_integer($input['stop']) && (
        is_integer($input['start']) && (is_integer($input['minimumAge']) || is_float($input['minimumAge']))
    )
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0,
        ':2' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSliceSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    slice(sort(people, age), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            ORDER BY `0`.`Age` ASC
            LIMIT :0, :1
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSortSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    sort(slice(people, :start, :stop), age)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSliceSortFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    slice(sort(filter(people, age < :minimumAge), age), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            WHERE (`0`.`Age` < :0)
            ORDER BY `0`.`Age` ASC
            LIMIT :1, :2
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['minimumAge'],
        ':1' => max($input['start'], 0),
        ':2' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSliceFilterSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    slice(filter(sort(people, age), age < :minimumAge), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            WHERE (`0`.`Age` < :0)
            ORDER BY `0`.`Age` ASC
            LIMIT :1, :2
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['minimumAge'],
        ':1' => max($input['start'], 0),
        ':2' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSortSliceFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    sort(slice(filter(people, age < :minimumAge), :start, :stop), age)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`0`) AS `0`
    FROM (
        SELECT
            TRUE AS `0`
            FROM `People` AS `0`
            WHERE (`0`.`Age` < :0)
            ORDER BY `0`.`Id` ASC
            LIMIT :1, :2
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['minimumAge'],
        ':1' => max($input['start'], 0),
        ':2' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountSortFilterSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    sort(filter(slice(people, :start, :stop), age < :minimumAge), age)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`1`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`,
            TRUE AS `1`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
    WHERE (`0`.`0` < :2)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (
    is_integer($input['stop']) && (
        is_integer($input['start']) && (is_integer($input['minimumAge']) || is_float($input['minimumAge']))
    )
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0,
        ':2' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountFilterSliceSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    filter(slice(sort(people, age), :start, :stop), age < :minimumAge)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`1`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`,
            TRUE AS `1`
            FROM `People` AS `0`
            ORDER BY `0`.`Age` ASC
            LIMIT :0, :1
    ) AS `0`
    WHERE (`0`.`0` < :2)
EOS;

    $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (
    is_integer($input['stop']) && (
        is_integer($input['start']) && (is_integer($input['minimumAge']) || is_float($input['minimumAge']))
    )
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0,
        ':2' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testCountFilterSortSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
count(
    filter(sort(slice(people, :start, :stop), age), age < :minimumAge)
)
EOS;

        $mysql = <<<'EOS'
SELECT
    COUNT(`0`.`1`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`,
            TRUE AS `1`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
    WHERE (`0`.`0` < :2)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (
    is_integer($input['stop']) && (
        is_integer($input['start']) && (is_integer($input['minimumAge']) || is_float($input['minimumAge']))
    )
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0,
        ':2' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = (integer)$row[0];
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSum()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
sum(
    people,
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    SUM(`0`.`Age`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSumSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
sum(
    sort(people, age),
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    SUM(`0`.`Age`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSumFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
sum(
    filter(people, age < :minimumAge),
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    SUM(`0`.`Age`) AS `0`
    FROM `People` AS `0`
    WHERE (`0`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (is_integer($input['minimumAge']) || is_float($input['minimumAge'])) {
    $output = array(
        ':0' => $input['minimumAge']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSumSlice()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
sum(
    slice(people, :start, :stop),
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    SUM(`0`.`0`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`
            FROM `People` AS `0`
            ORDER BY `0`.`Id` ASC
            LIMIT :0, :1
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => max($input['start'], 0),
        ':1' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSumSliceSortFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
sum(
    slice(sort(filter(people, age < :minimumAge), age), :start, :stop),
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    SUM(`0`.`0`) AS `0`
    FROM (
        SELECT
            `0`.`Age` AS `0`
            FROM `People` AS `0`
            WHERE (`0`.`Age` < :0)
            ORDER BY `0`.`Age` ASC
            LIMIT :1, :2
    ) AS `0`
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('minimumAge', $input)) {
    throw new Exception('minimumAge', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['minimumAge']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['minimumAge'],
        ':1' => max($input['start'], 0),
        ':2' => (max($input['start'], 0) < $input['stop']) ? ($input['stop'] - max($input['start'], 0)): 0
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testAverage()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
average(
    people,
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    AVG(`0`.`Age`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (float)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testMin()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
min(
    people,
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    MIN(`0`.`Age`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testMax()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
max(
    people,
    age
)
EOS;

        $mysql = <<<'EOS'
SELECT
    MAX(`0`.`Age`) AS `0`
    FROM `People` AS `0`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output = isset($row[0]) ? (integer)$row[0] : null;
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testGetChildren()
    {
        $scenario = self::getChildrenScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "name": name,
        "children": get(children, name)
    }
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Name` AS `1`,
    `2`.`Id` AS `2`,
    `2`.`Name` AS `3`
    FROM `People` AS `0`
    LEFT JOIN `Families` AS `1` ON `0`.`Id` <=> `1`.`Parent`
    LEFT JOIN `People` AS `2` ON `1`.`Child` <=> `2`.`Id`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]]['name'] = $row[1];

    if (isset($row[2])) {
        $output[$row[0]]['children'][$row[2]] = $row[3];
    }
}

$output = isset($output) ? array_values($output) : array();

foreach ($output as &$x0) {
    $x0['children'] = isset($x0['children']) ? array_values($x0['children']) : array();
}
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

/*
    public function testGetCountSum()
    {
        $scenario = self::getChildrenScenario();

        $method = <<<'EOS'
get(
    people,
    {
        "name": name,
        "count": count(children),
        "sum": sum(children, id)
    }
)
EOS;

        $mysql = <<<'EOS'
SELECT
    `0`.`Id` AS `0`,
    `0`.`Name` AS `1`,
    COUNT(`2`.`Id`) AS `2`,
    SUM(`2`.`Id`) AS `3`
    FROM `People` AS `0`
    LEFT JOIN `Families` AS `1` ON `0`.`Id` <=> `1`.`Parent`
    LEFT JOIN `People` AS `2` ON `1`.`Child` <=> `2`.`Id`
    GROUP BY `0`.`Id`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
foreach ($input as $row) {
    $output[$row[0]]['name'] = $row[1];
    $output[$row[0]]['count'] = (integer)$row[2];
    $output[$row[0]]['sum'] = isset($row[3]) ? (integer)$row[3] : null;
}

$output = isset($output) ? array_values($output) : array();
EOS;

        return $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }
*/

    public function testDelete()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
delete(
    people
)
EOS;

        $mysql = <<<'EOS'
DELETE
    FROM `People`
EOS;

        $phpInput = <<<'EOS'
$output = array();
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testDeleteFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
delete(
    filter(people, age < :age)
)
EOS;

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    WHERE (`People`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (is_integer($input['age']) || is_float($input['age'])) {
    $output = array(
        ':0' => $input['age']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
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
    public function testDeleteSliceSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
delete(
    slice(sort(people, age), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    ORDER BY `People`.`Age` ASC
    LIMIT :0
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    is_integer($input['stop']) && is_integer($input['start'])
) {
    $output = array(
        ':0' => $input['stop'] - $input['start']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testDeleteSliceSortFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
delete(
    slice(sort(filter(people, :age <= age), age), :start, :stop)
)
EOS;

        $mysql = <<<'EOS'
DELETE
    FROM `People`
    WHERE (:0 <= `People`.`Age`)
    ORDER BY `People`.`Age` ASC
    LIMIT :1
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (
    (
        is_integer($input['age']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    ) || (
        is_float($input['age']) && (
            is_integer($input['stop']) && is_integer($input['start'])
        )
    )
) {
    $output = array(
        ':0' => $input['age'],
        ':1' => $input['stop'] - $input['start']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testInsert()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
insert(
    people,
    {
        "name": :name,
        "email": :email,
        "age": :age,
        "height": :height,
        "isMarried": :isMarried
    }
)
EOS;

        $mysql = <<<'EOS'
INSERT
    INTO `People`
    SET
        `Name` = :0,
        `Email` = :1,
        `Age` = :2,
        `Height` = :3,
        `Married` = :4
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (!array_key_exists('email', $input)) {
    throw new Exception('email', 1);
}

if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (!array_key_exists('height', $input)) {
    throw new Exception('height', 1);
}

if (!array_key_exists('isMarried', $input)) {
    throw new Exception('isMarried', 1);
}

if (
    (
        is_null($input['name']) || is_string($input['name'])
    ) && (
        (
            is_null($input['email']) || is_string($input['email'])
        ) && (
            (
                is_null($input['age']) || is_integer($input['age'])
            ) && (
                (
                    is_null($input['height']) || is_float($input['height'])
                ) && (is_null($input['isMarried']) || is_bool($input['isMarried']))
            )
        )
    )
) {
    $output = array(
        ':0' => $input['name'],
        ':1' => $input['email'],
        ':2' => $input['age'],
        ':3' => $input['height'],
        ':4' => $input['isMarried']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSet()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
set(
    people,
    {
        "name": :name
    }
)
EOS;

        $mysql = <<<'EOS'
UPDATE
    `People` AS `0`
    SET
        `0`.`Name` = :0
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (is_null($input['name']) || is_string($input['name'])) {
    $output = array(
        ':0' => $input['name']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSetFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
set(
    filter(people, age < :age),
    {
        "name": :name,
        "age": :age
    }
)
EOS;

        $mysql = <<<'EOS'
UPDATE
    `People` AS `0`
    SET
        `0`.`Name` = :1,
        `0`.`Age` = :2
    WHERE (`0`.`Age` < :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (
    (
        is_null($input['age']) || is_integer($input['age'])
    ) && (is_null($input['name']) || is_string($input['name']))
) {
    $output = array(
        ':0' => $input['age'],
        ':1' => $input['name'],
        ':2' => $input['age']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSetFilterAdvanced()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
set(
    filter(people, name = :name),
    {
        "email": :email
    }
)
EOS;

        $mysql = <<<'EOS'
UPDATE
    `People` AS `0`
    SET
        `0`.`Email` = :1
    WHERE (`0`.`Name` <=> :0)
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (!array_key_exists('email', $input)) {
    throw new Exception('email', 1);
}

if (
    is_string($input['name']) && (is_null($input['email']) || is_string($input['email']))
) {
    $output = array(
        ':0' => $input['name'],
        ':1' => $input['email']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSetSliceSort()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
set(
    slice(sort(people, age), :start, :stop),
    {
        "name": :name,
        "age": :age
    }
)
EOS;

        $mysql = <<<'EOS'
UPDATE
    `People` AS `0`
    SET
        `0`.`Name` = :1,
        `0`.`Age` = :2
    ORDER BY `0`.`Age` ASC
    LIMIT :0
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('stop', $input)) {
    throw new Exception('stop', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (
    is_integer($input['stop']) && (
        is_integer($input['start']) && (
            (
                is_null($input['name']) || is_string($input['name'])
            ) && (is_null($input['age']) || is_integer($input['age']))
        )
    )
) {
    $output = array(
        ':0' => $input['stop'] - $input['start'],
        ':1' => $input['name'],
        ':2' => $input['age']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    public function testSetSliceSortFilter()
    {
        $scenario = self::getPeopleScenario();

        $method = <<<'EOS'
set(
    slice(sort(filter(people, age < :age), age), :start, :end),
    {
        "name": :name,
        "age": :age
    }
)
EOS;

        $mysql = <<<'EOS'
UPDATE
    `People` AS `0`
    SET
        `0`.`Name` = :2,
        `0`.`Age` = :3
    WHERE (`0`.`Age` < :0)
    ORDER BY `0`.`Age` ASC
    LIMIT :1
EOS;

        $phpInput = <<<'EOS'
if (!array_key_exists('age', $input)) {
    throw new Exception('age', 1);
}

if (!array_key_exists('end', $input)) {
    throw new Exception('end', 1);
}

if (!array_key_exists('start', $input)) {
    throw new Exception('start', 1);
}

if (!array_key_exists('name', $input)) {
    throw new Exception('name', 1);
}

if (
    (
        is_null($input['age']) || is_integer($input['age'])
    ) && (
        is_integer($input['end']) && (
            is_integer($input['start']) && (is_null($input['name']) || is_string($input['name']))
        )
    )
) {
    $output = array(
        ':0' => $input['age'],
        ':1' => $input['end'] - $input['start'],
        ':2' => $input['name'],
        ':3' => $input['age']
    );
} else {
    $output = null;
}
EOS;

        $phpOutput = <<<'EOS'
$output = true;
EOS;

        $this->verifyResult($scenario, $method, $mysql, $phpInput, $phpOutput);
    }

    private function verifyResult($scenarioJson, $method, $mysql, $phpInput, $phpOutput)
    {
        $scenario = json_decode($scenarioJson, true);
        $cinnabari = new Cinnabari($scenario);

        $expected = array($mysql, $phpInput, $phpOutput);        
        $actual = $cinnabari->translate($method);

        $this->assertSame(
            self::standardize($expected),
            self::standardize($actual)
        );
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

    private static function getChildrenScenario()
    {
        /*
        DROP DATABASE IF EXISTS `database`;
        CREATE DATABASE `database`;
        USE `database`;

        CREATE TABLE `People` (
            `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `Name` VARCHAR(256) NOT NULL
        );

        CREATE TABLE `Families` (
            `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `Parent` INT UNSIGNED NOT NULL,
            `Child` INT UNSIGNED NOT NULL,
            CONSTRAINT `uc_Families_Parent_Child` UNIQUE (`Parent`, `Child`),
            CONSTRAINT `fk_Families_Child__People_Id` FOREIGN KEY (`Child`) REFERENCES `People` (`Id`)
        );

        INSERT INTO `People`
            (`Id`, `Name`) VALUES
            (1, "Ann"),
            (2, "Becca"),
            (3, "Charlotte"),
            (4, "Dana"),
            (5, "Erica");

        INSERT INTO `Families`
            (`Id`, `Parent`, `Child`) VALUES
            (1, 1, 2),
            (2, 1, 3),
            (3, 4, 5);
        */

        return <<<'EOS'
        {
            "classes": {
                "Database": {
                    "people": ["Person", "People"]
                },
                "Person": {
                    "id": [2, "Id"],
                    "children": ["Person", "Children", "Child"],
                    "name": [4, "Name"]
                }
            },
            "values": {
                "`People`": {
                    "Id": ["`Id`", false],
                    "Name": ["`Name`", false]
                }
            },
            "lists": {
                "People": ["`People`", "`Id`", false]
            },
            "connections": {
                "`People`": {
                    "Children": ["`Families`", "`0`.`Id` <=> `1`.`Parent`", "`Id`", true, true]
                },
                "`Families`": {
                    "Child": ["`People`", "`0`.`Child` <=> `1`.`Id`", "`Id`", false, false]
                }
            }
        }
EOS;
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
            (`Id`, `Name`, `Age`) VALUES
            (1, 1, 21),
            (2, 2, 28),
            (3, 3, 18),
            (4, 4, 26);

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
            "Age": ["`Age`", false]
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
}
