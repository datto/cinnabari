<?php

call_user_func(function () {
	$classes = array(
		'Datto\\Cinnabari\\' => dirname(__DIR__) . '/src',
		'SpencerMortensen\\Parser' => dirname(__DIR__) . '/vendor/spencer-mortensen/parser/src'
	);

	foreach ($classes as $namespacePrefix => $libraryPath) {
		$namespacePrefix .= '\\';
		$namespacePrefixLength = strlen($namespacePrefix);

		$autoloader = function ($class) use ($namespacePrefix, $namespacePrefixLength, $libraryPath) {
			if (strncmp($class, $namespacePrefix, $namespacePrefixLength) !== 0) {
				return;
			}

			$relativeClassName = substr($class, $namespacePrefixLength);
			$relativeFilePath = strtr($relativeClassName, '\\', '/') . '.php';
			$absoluteFilePath = "{$libraryPath}/{$relativeFilePath}";

			if (is_file($absoluteFilePath)) {
				include $absoluteFilePath;
			}
		};

		spl_autoload_register($autoloader);
	}
});



spl_autoload_register(
	function ($class) {
		$namespacePrefix = 'Datto\\Cinnabari\\';
		$namespacePrefixLength = strlen($namespacePrefix);

		if (strncmp($class, $namespacePrefix, $namespacePrefixLength) !== 0) {
			return;
		}

		$relativeClassName = substr($class, $namespacePrefixLength);
		$filePath = dirname(__DIR__) . '/src/' . strtr($relativeClassName, '\\', '/') . '.php';

		if (is_file($filePath)) {
			include $filePath;
		}
	}
);
