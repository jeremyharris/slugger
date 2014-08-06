<?php

class SlugCache {

/**
 * Var cache (used for multiple slugs in a single request)
 *
 * @var array
 */
	public static $_varCache = array();

/**
 * Cache config
 *
 * @var string
 */
	protected static $_config = 'Slugger';

/**
 * Inits slugger cache if it's not set up
 */
	public function __construct() {
		if (!in_array(self::$_config, Cache::configured())) {
			Cache::config('Slugger', array(
				'engine' => 'File',
				'duration' => '+1 days',
				'prefix' => 'slugger_'
			));
		}
	}

/**
 * Gets or sets the cache config to be used
 *
 * @param string $config
 * @return string
 */
	public static function config($config = null) {
		if (empty($config)) {
			return self::$_config;
		}
		self::$_config = $config;
	}

/**
 * Gets cached slugs for a model
 *
 * @param string $modelName
 * @return array|boolean
 */
	public static function get($modelName = null) {
		if (isset(self::$_varCache[self::$_config]) && isset(self::$_varCache[self::$_config][$modelName])) {
			return self::$_varCache[self::$_config][$modelName];
		}
		$cache = Cache::read($modelName, self::$_config);
		if ($cache !== false) {
			self::$_varCache[self::$_config][$modelName] = $cache;
		}
		return $cache;
	}

/**
 * Caches slugs for a model
 *
 * @param string $modelName
 * @param array $slugs
 */
	public static function set($modelName = null, $slugs = array()) {
		self::$_varCache[self::$_config][$modelName] = $slugs;
		Cache::write($modelName, $slugs, self::$_config);
	}

/**
 * Invalidates all model cache
 *
 * @param string $modelName
 * @return boolean
 */
	public static function invalidate($modelName) {
		unset(self::$_varCache[self::$_config][$modelName]);
		return Cache::delete($modelName, self::$_config);
	}

/**
 * Clears all cache for current config
 */
	public static function clear() {
		Cache::clear(false, self::$_config);
		unset(self::$_varCache[self::$_config]);
	}

}

