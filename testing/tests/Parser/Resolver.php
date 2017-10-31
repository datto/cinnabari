<?php

namespace Datto\Cinnabari\Parser;

use Datto\Cinnabari\AbstractRequest\Nodes\FunctionNode;
use Datto\Cinnabari\AbstractRequest\Nodes\ParameterNode;
use Datto\Cinnabari\AbstractRequest\Nodes\PropertyNode;
use Datto\Cinnabari\AbstractRequest\Nodes\ObjectNode;
use Datto\Cinnabari\Exception;
use Datto\Cinnabari\Parser\Language\Functions;
use Datto\Cinnabari\Parser\Language\Properties;
use Datto\Cinnabari\Parser\Language\Types;

$functions = new Functions(); // Mock
$properties = new Properties(); // Mock


// Test
$resolver = new Resolver($functions, $properties);
$resolver->resolve($token);


// Input
$token = new ParameterNode('c');

// Output
throw Exception::unresolvableTypeConstraints($token);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_NULL;
$token->setDataType(Types::TYPE_NULL);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_BOOLEAN;
$token->setDataType(Types::TYPE_BOOLEAN);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_INTEGER;
$token->setDataType(Types::TYPE_INTEGER);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_FLOAT;
$token->setDataType(Types::TYPE_FLOAT);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_STRING;
$token->setDataType(Types::TYPE_STRING);


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return array(Types::TYPE_OBJECT, 'Person');
$token->setDataType(array(Types::TYPE_OBJECT, 'Person'));


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return array(Types::TYPE_ARRAY, Types::TYPE_INTEGER);
$token->setDataType(array(Types::TYPE_ARRAY, Types::TYPE_INTEGER));


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'));
$token->setDataType(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person')));


// Input
$token = new PropertyNode(array('x'));

// Output
$properties->getDataType('Database', 'x'); // return array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN);
$token->setDataType(array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN));


// Input
$token = new PropertyNode(array('unknown'));

// Output
$properties->getDataType('Database', 'unknown'); // throw Exception::unknownProperty('Database', 'unknown');
throw Exception::unknownProperty('Database', 'unknown');


// Input
$token = new PropertyNode(array('person', 'age'));

// Output
$properties->getDataType('Database', 'person'); // return array(Types::TYPE_OBJECT, 'Person');
$properties->getDataType('Person', 'age'); // return Types::TYPE_INTEGER;
$token->setDataType(Types::TYPE_INTEGER);


// Input
$token = new PropertyNode(array('people', 'age'));

// Output
$properties->getDataType('Database', 'people'); // return array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'));
throw Exception::invalidPropertyAccess(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person')), 'age');


/*
// Input
$token = new FunctionToken('f', array());

// Output
$functions->getSignatures('f'); // return array(array(Types::TYPE_BOOLEAN));
$token->setDataType(Types::TYPE_BOOLEAN);


// Input
$token = new FunctionToken('f', array(
	new PropertyToken(array('x'))
));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_BOOLEAN;
$functions->getSignatures('f'); // return array(array(Types::TYPE_BOOLEAN, Types::TYPE_BOOLEAN));
$token = new FunctionToken('f', array(
	new PropertyToken(array('x'), Types::TYPE_BOOLEAN)
), Types::TYPE_BOOLEAN);


// Input
$token = new FunctionToken('identity', array(
	new PropertyToken(array('x'))
));

// Output
$properties->getDataType('Database', 'x'); // return array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN);
$functions->getSignatures('identity'); // return array(array(Types::TYPE_NULL, Types::TYPE_NULL), array(Types::TYPE_BOOLEAN, Types::TYPE_BOOLEAN));
$token = new FunctionToken('identity', array(
	new PropertyToken(array('x'), array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN))
), array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN));


// Input
$token = new FunctionToken('get', array(
	new PropertyToken(array('people')),
	new PropertyToken(array('name'))
));

// Output
$properties->getDataType('Database', 'people'); // return array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'));
$properties->getDataType('Person', 'name'); // return Types::TYPE_STRING;
$functions->getSignatures('get'); // return array(array(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x')), '$y', array(Types::TYPE_ARRAY, '$y')));
$token = new FunctionToken('get', array(
	new PropertyToken(array('people'), array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'))),
	new PropertyToken(array('name'), Types::TYPE_STRING)
), array(Types::TYPE_ARRAY, Types::TYPE_STRING));


// Input
$token = new FunctionToken('get', array(
	new FunctionToken('filter', array(
		new PropertyToken(array('people')),
		new FunctionToken('equal', array(
			new PropertyToken(array('id')),
			new ParameterToken('id')
		))
	)),
	new PropertyToken(array('name'))
));

// Output
$properties->getDataType('Database', 'people'); // return array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'));
$properties->getDataType('Person', 'id'); // return Types::TYPE_INTEGER;
$functions->getSignatures('equal'); // return array(array(Types::TYPE_NULL, Types::TYPE_NULL, Types::TYPE_NULL), array(Types::TYPE_NULL, Types::TYPE_INTEGER, Types::TYPE_NULL), array(Types::TYPE_NULL, Types::TYPE_FLOAT, Types::TYPE_NULL), array(Types::TYPE_INTEGER, Types::TYPE_NULL, Types::TYPE_NULL), array(Types::TYPE_INTEGER, Types::TYPE_INTEGER, Types::TYPE_BOOLEAN), array(Types::TYPE_INTEGER, Types::TYPE_FLOAT, Types::TYPE_BOOLEAN), array(Types::TYPE_FLOAT, Types::TYPE_INTEGER, Types::TYPE_BOOLEAN), array(Types::TYPE_FLOAT, Types::TYPE_NULL, Types::TYPE_NULL), array(Types::TYPE_FLOAT, Types::TYPE_FLOAT, Types::TYPE_BOOLEAN), array(Types::TYPE_NULL, Types::TYPE_STRING, Types::TYPE_NULL), array(Types::TYPE_STRING, Types::TYPE_NULL, Types::TYPE_NULL), array(Types::TYPE_STRING, Types::TYPE_STRING, Types::TYPE_BOOLEAN));
$functions->getSignatures('filter'); // return array(array(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x')), Types::TYPE_NULL, array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x'))), array(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x')), Types::TYPE_BOOLEAN, array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x'))));
$properties->getDataType('Person', 'name'); // return Types::TYPE_STRING;
$functions->getSignatures('get'); // return array(array(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x')), '$y', array(Types::TYPE_ARRAY, '$y')));
$token = new FunctionToken('get', array(
	new FunctionToken('filter', array(
		new PropertyToken(array('people'), array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'))),
		new FunctionToken('equal', array(
			new PropertyToken(array('id'), Types::TYPE_INTEGER),
			new ParameterToken('id', array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_INTEGER, Types::TYPE_FLOAT))
		), array(Types::TYPE_OR, Types::TYPE_NULL, Types::TYPE_BOOLEAN))
	), array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, 'Person'))),
	new PropertyToken(array('name'), Types::TYPE_STRING)
), array(Types::TYPE_ARRAY, Types::TYPE_STRING));


// Input
$token = new FunctionToken('get', array(
	new PropertyToken(array('person')),
	new PropertyToken(array('person'))
));

// Output
$properties->getDataType('Database', 'person'); // return array(Types::TYPE_OBJECT, 'Person');
$properties->getDataType('Database', 'person'); // return array(Types::TYPE_OBJECT, 'Person');
$functions->getSignatures('get'); // return array(array(array(Types::TYPE_ARRAY, array(Types::TYPE_OBJECT, '$x')), '$y', array(Types::TYPE_ARRAY, '$y')));
throw Exception::unresolvableTypeConstraints($token);


// Input
$token = new ObjectToken(array(
	'X' => new PropertyToken(array('x')),
	'Y' => new PropertyToken(array('y'))
));

// Output
$properties->getDataType('Database', 'x'); // return Types::TYPE_INTEGER;
$properties->getDataType('Database', 'y'); // return Types::TYPE_STRING;
$token = new ObjectToken(array(
	'X' => new PropertyToken(array('x'), Types::TYPE_INTEGER),
	'Y' => new PropertyToken(array('y'), Types::TYPE_STRING)
), array(Types::TYPE_OBJECT, array('X' => Types::TYPE_INTEGER, 'Y' => Types::TYPE_STRING)));
*/
