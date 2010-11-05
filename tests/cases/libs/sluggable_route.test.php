<?php

App::import('Lib', array('Slugger.routes/SluggableRoute'));

class SluggableRouteTestCase extends CakeTestCase {

	var $fixtures = array('plugin.slugger.route_test', 'plugin.slugger.route_two_test');

	function startTest() {
		$router = Router::getInstance();
		$this->_oldRoutes = $router->routes;
		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'SluggableRoute',
				'models' => array('RouteTest')
			)
		);
		$this->RouteTest = ClassRegistry::init('RouteTest');
	}

	function endTest() {
		Router::reload();
		$router = Router::getInstance();
		$router->routes = $this->_oldRoutes;
		unset($this->RouteTest);
		ClassRegistry::flush();
	}

	function testEmptyTable() {
		$this->RouteTest->deleteAll(array(
			'id >' => 0
		));
		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/RouteTest:1';
		$this->assertEqual($result, $expected);
	}

	function testPrependPk() {
		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'SluggableRoute',
				'models' => array('RouteTest'),
				'prependPk' => true
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/1-a-page-title';
		$this->assertEqual($result, $expected);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 2
		));
		$expected = '/route_tests/view/2-another-title';
		$this->assertEqual($result, $expected);
	}

	function testGetSlugs() {
		$SluggableRoute = new SluggableRoute(null, null, null);

		$results = $SluggableRoute->getSlugs($this->RouteTest);
		$expected = array(
			1 => 'a-page-title',
			2 => 'another-title',
			3 => 'i-love-cakephp',
		);
		$this->assertEqual($results, $expected);

		$results = $SluggableRoute->getSlugs($this->RouteTest, 'name');
		$expected = array(
			1 => 'page-title',
			2 => 'routing-is-fun',
			3 => 'cake-rocks',
		);
		$this->assertEqual($results, $expected);
	}

	function testSlug() {
		$SluggableRoute = new SluggableRoute(null, null, null);
		$set = array(
			1 => 'page-title',
			2 => 'routing-is-fun',
			3 => 'cake-rocks',
		);

		$result = $SluggableRoute->slug(1, $set);
		$expected = 'page-title';
		$this->assertEqual($result, $expected);

		$result = $SluggableRoute->slug(2, $set);
		$expected = 'routing-is-fun';
		$this->assertEqual($result, $expected);

		// check for duplicates
		$set = array(
			1 => 'page-title',
			2 => 'routing-is-fun',
			3 => 'cake-rocks',
			4 => 'page-title',
		);
		$result = $SluggableRoute->slug(1, $set);
		$expected = '1-page-title';
		$this->assertEqual($result, $expected);
	}

	function testMatch() {
		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/a-page-title';
		$this->assertEqual($result, $expected);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 2
		));
		$expected = '/route_tests/view/another-title';
		$this->assertEqual($result, $expected);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 2,
			'passedVar'
		));
		$expected = '/route_tests/view/passedVar/another-title';
		$this->assertEqual($result, $expected);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 5
		));
		$expected = '/route_tests/view/RouteTest:5';
		$this->assertEqual($result, $expected);
	}

	function testParse() {
		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/missing-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => array(),
			'pass' => array('missing-title')
		);
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/passedVar/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => array(),
			'pass' => array('passedVar')
		);
		$this->assertEqual($result, $expected);
	}

	function testDuplicateSlug() {
		$this->RouteTest->save(array(
			'title' => 'A page title',
			'name' => 'Page Title',
		));

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/1-a-page-title';
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => array(),
			'pass' => array('a-page-title')
		);
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/1-a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 1
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);

		$id = $this->RouteTest->id;
		$result = Router::parse('/route_tests/view/'.$id.'-a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => $id
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);
	}

	function testSlugField() {
		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'SluggableRoute',
				'models' => array('RouteTest' => 'name')
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 3
		));
		$expected = '/route_tests/view/cake-rocks';
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/routing-is-fun');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);
	}

	function testMultipleModels() {
		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'SluggableRoute',
				'models' => array('RouteTest', 'RouteTwoTest')
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);

		$result = Router::parse('/route_tests/view/my-blog-post');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTwoTest' => 1
			),
			'plugin' => array(),
			'pass' => array()
		);
		$this->assertEqual($result, $expected);
	}

	function testMissingModel() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'SluggableRoute',
				'models' => array('UndefinedModel')
			)
		);

		$result = Router::parse('/route_tests/view/my-slug');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => array(),
			'pass' => array('my-slug')
		);
		$this->assertEqual($result, $expected);
	}

}

?>