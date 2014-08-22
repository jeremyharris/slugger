<?php

App::uses('SluggableRoute', 'Slugger.Routing/Route');
App::uses('SlugCache', 'Slugger.Cache');
App::uses('Router', 'Routing');
App::uses('Model', 'Model');

class RouteTest extends Model {

}
class RouteTwoTest extends Model {

}

class SluggableRouteTestCase extends CakeTestCase {

	var $fixtures = array('plugin.slugger.route_test', 'plugin.slugger.route_two_test');

	public function startTest($method) {
		$this->slugCache = SlugCache::config();
		SlugCache::config('SluggerTest');
		$this->disabled = Configure::read('Cache.disable');
		Configure::write('Cache.disable', false);
		Router::reload();
		$this->RouteTest = ClassRegistry::init('RouteTest');
	}

	public function endTest($method) {
		SlugCache::clear();
		SlugCache::config($this->slugCache);
		Router::reload();
		unset($this->RouteTest);
	}

	public function tearDown() {
		SlugCache::clear();
		Configure::write('Cache.disable', $this->disabled);
	}

	public function testCustomSlugFunction() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest'),
				'slugFunction' => array('Inflector', 'slug')
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			1
		));
		$expected = '/route_tests/view/A_page_title';
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/A_page_title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(1)
		);
		$this->assertEquals($expected, $result);

		SlugCache::invalidate('RouteTest');

		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest'),
				'slugFunction' => create_function('$str', 'return str_replace(" ", "", strtoupper($str));')
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			3
		));
		$expected = '/route_tests/view/ILOVECAKEPHP';
		$this->assertEquals($expected, $result);
	}

	public function testGroupingCaseSensitivity() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest')
			)
		);

		$this->RouteTest->save(array(
			'RouteTest' => array(
				'title' => 'i love cakephp',
				'name' => 'case sensitive grouping, please!',
			)
		));

		$results = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			4
		));
		$expected = '/route_tests/view/4-i-love-cakephp';
		$this->assertEquals($expected, $results);

		$results = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			3
		));
		$expected = '/route_tests/view/3-i-love-cakephp';
		$this->assertEquals($expected, $results);
	}

	public function testEmptyTable() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest')
			)
		);

		$this->RouteTest->deleteAll(array(
			'id >' => 0
		));
		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/RouteTest:1';
		$this->assertEquals($expected, $result);
	}

	public function testPrependPk() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest'),
				'prependPk' => true
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			1
		));
		$expected = '/route_tests/view/1-a-page-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			2
		));
		$expected = '/route_tests/view/2-another-title';
		$this->assertEquals($expected, $result);
	}

	public function testGetSlugs() {
		$Sluggable = new SluggableRoute(null, null, null);
		$Sluggable->options['models'] = array(
			'RouteTest' => array()
		);
		$Sluggable->config();

		$results = $Sluggable->getSlugs($this->RouteTest->alias);
		$expected = array(
			1 => 'a-page-title',
			2 => 'another-title',
			3 => 'i-love-cakephp',
		);
		$this->assertEquals($expected, $results);

		SlugCache::invalidate('RouteTest');

		$Sluggable->options['models'] = array(
			'RouteTest' => array(
				'slugField' => 'name'
			)
		);
		$Sluggable->config();

		$results = $Sluggable->getSlugs($this->RouteTest->alias);
		$expected = array(
			1 => 'page-title',
			2 => 'routing-is-fun',
			3 => 'cake-rocks',
		);
		$this->assertEquals($expected, $results);
	}

	public function testSlug() {
		$SluggableRoute = new SluggableRoute(null, null, null);

		$slug = array(
			'_pk' => 1,
			'_field' => 'Page Title',
			'_count' => 1,
		);
		$result = $SluggableRoute->slug($slug);
		$expected = 'page-title';
		$this->assertEquals($expected, $result);

		$slug = array(
			'_pk' => 1,
			'_field' => 'Routing is fun!',
			'_count' => 1,
		);
		$result = $SluggableRoute->slug($slug);
		$expected = 'routing-is-fun';
		$this->assertEquals($expected, $result);

		// check for duplicates
		$slug = array(
			'_pk' => 1,
			'_field' => 'Page Title',
			'_count' => 3,
		);
		$result = $SluggableRoute->slug($slug);
		$expected = '1-page-title';
		$this->assertEquals($expected, $result);

		// check non-ascii chars
		$slug = array(
			'_pk' => 1,
			'_field' => 'ñice Pagé!',
			'_count' => 3,
		);
		$result = $SluggableRoute->slug($slug);
		$expected = '1-nice-page';
		$this->assertEquals($expected, $result);
	}

	public function testMatch() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array(
					'RouteTest' => array(
						'param' => 'RouteTest'
					)
				)
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 1
		));
		$expected = '/route_tests/view/a-page-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 2
		));
		$expected = '/route_tests/view/another-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 2,
			'passedVar'
		));
		$expected = '/route_tests/view/passedVar/another-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'RouteTest' => 5
		));
		$expected = '/route_tests/view/RouteTest:5';
		$this->assertEquals($expected, $result);

		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest')
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			1
		));
		$expected = '/route_tests/view/a-page-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			2
		));
		$expected = '/route_tests/view/another-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			2,
			'passedVar'
		));
		$expected = '/route_tests/view/another-title/passedVar';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			5
		));
		$expected = '/route_tests/view/5';
		$this->assertEquals($expected, $result);

		Router::reload();
		Router::connect('/:controller/:action/:post_id/*',
			array(),
			array(
				'pass' => array(
					'post_id'
				),
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array(
					'RouteTest' => array(
						'param' => ':post_id'
					)
				)
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'post_id' => 1
		));
		$expected = '/route_tests/view/a-page-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'post_id' => 2
		));
		$expected = '/route_tests/view/another-title';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'post_id' => 2,
			'passedVar'
		));
		$expected = '/route_tests/view/another-title/passedVar';
		$this->assertEquals($expected, $result);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			'post_id' => 5
		));
		$expected = '/route_tests/view/5';
		$this->assertEquals($expected, $result);
	}

	public function testParse() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array(
					'RouteTest' => array(
						'param' => 'RouteTest'
					)
				)
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => null,
			'pass' => array()
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/missing-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array('missing-title')
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/passedVar/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'RouteTest' => 2
			),
			'plugin' => null,
			'pass' => array('passedVar')
		);
		$this->assertEquals($expected, $result);

		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest')
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(2)
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/missing-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array('missing-title')
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/another-title/passedVar');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(2, 'passedVar')
		);
		$this->assertEquals($expected, $result);

		Router::reload();
		Router::connect('/:controller/:action/:id/*',
			array(),
			array(
				'pass' => array('id'),
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array(
					'RouteTest' => array(
						'param' => ':id'
					)
				)
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'id' => 2,
			'pass' => array(2)
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/missing-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'id' => 'missing-title',
			'named' => array(),
			'plugin' => null,
			'pass' => array('missing-title')
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/another-title/passedVar');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'id' => 2,
			'pass' => array(2, 'passedVar')
		);
		$this->assertEquals($expected, $result);
	}

	public function testDuplicateSlug() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest')
			)
		);

		$this->RouteTest->create();
		$this->RouteTest->save(array(
			'title' => 'A page title',
			'name' => 'Page Title',
		));

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			1
		));
		$expected = '/route_tests/view/1-a-page-title';
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array('a-page-title')
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/1-a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(1)
		);
		$this->assertEquals($expected, $result);

		$id = $this->RouteTest->id;
		$result = Router::parse('/route_tests/view/'.$id.'-a-page-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array($id)
		);
		$this->assertEquals($expected, $result);
	}

	public function testSlugField() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest' => array('slugField' => 'name'))
			)
		);

		$result = Router::url(array(
			'controller' => 'route_tests',
			'action' => 'view',
			3
		));
		$expected = '/route_tests/view/cake-rocks';
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/routing-is-fun');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(2)
		);
		$this->assertEquals($expected, $result);
	}

	public function testMultipleModels() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('RouteTest', 'RouteTwoTest')
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(2)
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/my-blog-post');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(1)
		);
		$this->assertEquals($expected, $result);

		Router::reload();
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array(
					'RouteTest',
					'RouteTwoTest' => array(
						'param' => 'MyNamedParam'
					)
				)
			)
		);

		$result = Router::parse('/route_tests/view/another-title');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array(2)
		);
		$this->assertEquals($expected, $result);

		$result = Router::parse('/route_tests/view/my-blog-post');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(
				'MyNamedParam' => 1
			),
			'plugin' => null,
			'pass' => array()
		);
		$this->assertEquals($expected, $result);
	}

	public function testMissingModel() {
		Router::connect('/:controller/:action/*',
			array(),
			array(
				'routeClass' => 'Slugger.SluggableRoute',
				'models' => array('UndefinedModel')
			)
		);

		$result = Router::parse('/route_tests/view/my-slug');
		$expected = array(
			'controller' => 'route_tests',
			'action' => 'view',
			'named' => array(),
			'plugin' => null,
			'pass' => array('my-slug')
		);
		$this->assertEquals($expected, $result);
	}

}
