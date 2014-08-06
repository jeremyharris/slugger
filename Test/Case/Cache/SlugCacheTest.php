<?php

App::uses('SlugCache', 'Slugger.Cache');

class SlugCacheTest extends CakeTestCase {

	public function setUp() {
		$this->disabled = Configure::read('Cache.disable');
		Configure::write('Cache.disable', false);
		$this->slugCache = SlugCache::config();
		Cache::config('SluggerTest', array(
			'engine' => 'File',
			'duration' => '+1 days',
			'prefix' => 'slugger_test_'
		));
		SlugCache::config('SluggerTest');
		parent::setUp();
	}

	public function tearDown() {
		SlugCache::clear();
		SlugCache::config($this->slugCache);
		Configure::write('Cache.disable', $this->disabled);
		Cache::drop('SluggerTest');
		parent::tearDown();
	}

	public function testSet() {
		SlugCache::set('User', array(
			1 => 2
		));
		SlugCache::set('Route', array(
			3 => 4
		));

		$result = SlugCache::$_varCache;
		$expected = array(
			'SluggerTest' => array(
				'User' => array(
					1 => 2
				),
				'Route' => array(
					3 => 4
				)
			)
		);
		$this->assertEquals($expected, $result);

		$result = Cache::read('User', 'SluggerTest');
		$expected = array(
			1 => 2
		);
		$this->assertEquals($expected, $result);
	}

	public function testGet() {
		SlugCache::set('User', array(
			1 => 2
		));

		$result = SlugCache::get('User');
		$expected = array(
			1 => 2
		);
		$this->assertEquals($expected, $result);

		SlugCache::$_varCache = array();

		$result = SlugCache::get('User');
		$expected = array(
			1 => 2
		);
		$this->assertEquals($expected, $result);

		$result = SlugCache::$_varCache;
		$expected = array(
			'SluggerTest' => array(
				'User' => array(
					1 => 2
				)
			)
		);
		$this->assertEquals($expected, $result);
	}

	public function testClear() {
		SlugCache::set('User', array(
			1 => 2
		));
		SlugCache::set('Route', array(
			3 => 4
		));

		SlugCache::clear();

		$this->assertFalse(Cache::read('User', 'SluggerTest'));
		$this->assertFalse(Cache::read('Route', 'SluggerTest'));
		$this->assertTrue(empty(SlugCache::$_varCache));
	}

	public function testInvalidate() {
		SlugCache::set('User', array(
			1 => 2
		));
		SlugCache::set('Route', array(
			3 => 4
		));

		SlugCache::invalidate('User');

		$this->assertFalse(Cache::read('User', 'SluggerTest'));

		$expected = array(
			3 => 4
		);
		$result = Cache::read('Route', 'SluggerTest');
		$this->assertEquals($expected, $result);

		$expected = array(
			'SluggerTest' => array(
				'Route' => array(
					3 => 4
				)
			)
		);
		$result = SlugCache::$_varCache;
		$this->assertEquals($expected, $result);
	}

}