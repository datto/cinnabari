<?php

namespace Datto\Cinnabari;

use Datto\Cinnabari\Compiler\Mysql\Compiler as MysqlCompiler;
use Datto\Cinnabari\Parser\Language\Functions;
use Datto\Cinnabari\Parser\Language\Operators;
use Datto\Cinnabari\Parser\Language\Properties;
use Datto\Cinnabari\Parser\Language\Types;
use Datto\Cinnabari\Translator\Map;
use Datto\Cinnabari\Translator\Nodes\Table;
use Datto\Cinnabari\Translator\Nodes\Value;
use Datto\Cinnabari\Translator\Translator;

require __DIR__ . '/autoload.php';

$types = array(
	'Database' => array(
		'people' => array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'))
	),
	'Person' => array(
		'id' => Types::TYPE_INTEGER
	)
);

$map = array(
	'Database' => array(
		'people' => array(new Table('`People`', '`Id`', false), 'Person')
	),
	'Person' => array(
		'id' => array(new Value('`Id`'))
	)
);

$functions = new Functions();
$operators = new Operators();
$properties = new Properties($types);
$parser = new Parser($functions, $operators, $properties);
$map = new Map($map);
$translator = new Translator($map);
$compiler = new MysqlCompiler();

$query = 'count(people)';
$query = 'count(sort(people, id))';
$request = $parser->parse($query);
$translation = $translator->translate($request);
$mysql = $compiler->compile($translation);

echo $mysql, "\n";
