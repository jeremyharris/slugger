<?php
class RouteTestFixture extends CakeTestFixture {
	var $name = 'RouteTest';

	var $fields = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
		'title' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 50),
		'name' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 50),
		'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1)),
		'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM')
	);

	var $records = array(
		array(
			'id' => 1,
			'title' => 'A page title',
			'name' => 'Page Title',
		),
		array(
			'id' => 2,
			'title' => 'Another title',
			'name' => 'Routing is fun!',
		),
		array(
			'id' => 3,
			'title' => 'I love CakePHP',
			'name' => 'Cake rocks',
		)
	);
}
?>