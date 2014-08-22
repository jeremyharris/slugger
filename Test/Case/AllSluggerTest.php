<?php
/**
 * All Slugger plugin tests
 */
class AllSluggerTest extends CakeTestCase {

/**
 * Suite define the tests for this plugin
 *
 * @return void
 */
	public static function suite() {
		$suite = new CakeTestSuite('All Slugger test');

		$path = CakePlugin::path('Slugger') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}

}
