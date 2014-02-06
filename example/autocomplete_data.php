<?php
	// some contrived autocomplete data

	$term = $_GET['term'];

	$possibilities = array(
		'ape',
		'bison',
		'bear',
		'camel',
		'cat',
		'dog',
		'donkey',
		'euchidna',
		'fire ant',
		'girrafe',
		'hippo',
		'iguana',
		'jackal',
		'kiwi bird',
		'kitten',
		'leopard',
		'lion',
		'llama',
		'mongoose',
		'musk ox',
		'nightowl',
		'orangutan',
		'pirhana',
		'possum',
		'quail',
		'rhino',
		'scorpion',
		'turtle',
		'unicorn',
		'viper',
		'wombat',
		'xenarthra',
		'yak',
		'zebra',
	);

	$matches = array();
	foreach ($possibilities as $p) {
		if (preg_match('/^' . $term . '/i', $p)) {
			$matches[] = $p;
		}
	}

	echo json_encode($matches);
?>
