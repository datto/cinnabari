<?php

namespace Datto\Cinnabari\Translator;

use Exception;

require __DIR__ . '/autoload.php';

$aliases = new Aliases();

$x = new Exception('Armageddon', 666);
$y = new Exception('Blizzard', -10);
$z = new Exception('Blizzard', -10);

$alias = $aliases->getAlias($x);
echo "alias: $alias\n";

$alias = $aliases->getAlias($y);
echo "alias: $alias\n";

$alias = $aliases->getAlias($z);
echo "alias: $alias\n";
