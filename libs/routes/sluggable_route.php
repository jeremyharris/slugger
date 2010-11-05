<?php
/**
 * Sluggable route class.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       slugger
 * @subpackage    slugger.libs.routes
 */

/**
 * Sluggable Route
 *
 * Automatically slugs routes based on named parameters
 *
 * @package       slugger
 * @subpackage    slugger.libs.routes
 * @todo benchmark the caching, maybe change it to use a faster cache if slow
 * @link http://mark-story.com/posts/view/using-custom-route-classes-in-cakephp
 */
class SluggableRoute extends CakeRoute {

/*
 * Override the parsing function to find an id based on a slug
 *
 * @param string $url Url string
 * @return boolean
 */
    function parse($url) {
		$params = parent::parse($url);

		if (empty($params)) {
			return false;
		}

		if (isset($this->options['models']) && isset($params['_args_'])) {
			foreach ($this->options['models'] as $checkNamed => $slugField) {
				if (is_numeric($checkNamed)) {
					$checkNamed = $slugField;
					$slugField = null;
				}
				$slugSet = $this->getSlugs($checkNamed, $slugField);
				if ($slugSet === false) {
					continue;
				}
				$slugSet = array_flip($slugSet);
				$passed = explode('/', $params['_args_']);
				foreach ($passed as $key => $pass) {
					if (isset($slugSet[$pass])) {
						unset($passed[$key]);
						$passed[] = $checkNamed.':'.$slugSet[$pass];
					}
				}
				$params['_args_'] = implode('/', $passed);
			}
			return $params;
		}
		
		return false;
	}

/*
 * Matches the model's id and converts it to a slug
 *
 * @param array $url Cake url array
 * @return boolean
 */
	function match($url) {
		// grab id and convert to username (from the user param)
		if (isset($this->options['models'])) {
			foreach ($this->options['models'] as $checkNamed => $slugField) {
				if (is_numeric($checkNamed)) {
					$checkNamed = $slugField;
					$slugField = null;
				}
				if (isset($url[$checkNamed])) {
					$slugSet = $this->getSlugs($checkNamed, $slugField);
					if ($slugSet === false) {
						continue;
					}					
					if (isset($slugSet[$url[$checkNamed]])) {
						$url[] = $slugSet[$url[$checkNamed]];
						unset($url[$checkNamed]);
					}
				}
			}
		}
		
		return parent::match($url);
	}

/**
 * Slugs a string for the purpose of this route
 *
 * @param integer $id The key for the set
 * @param array $set The set
 * @return string
 */
	function slug($id, $set) {
		$str = $set[$id];
		$counts = array_count_values($set);
		if ($counts[$str] > 1 || (isset($this->options['prependPk']) && $this->options['prependPk'])) {
			$str = $id.' '.$str;
		}
		return $this->_slug($str);
	}

/**
 * Slugs a string
 *
 * @param string $str The string to slug
 * @return string
 */
	function _slug($str) {
		return strtolower(Inflector::slug($str, '-'));
	}

/**
 * Gets slugs from cache
 *
 * @param Model $Model
 * @return mixed False if the model fails to initialize, slug set array on success
 */
	function getSlugs($modelName, $field = null) {
		$cache = Cache::getInstance();
		$originalCacheConfig = $cache->__name;
		Cache::config('Slugger.short', array(
			'engine' => 'File',
			'duration' => '+1 days',
			'prefix' => 'slugger_',
		));

		$slugs = Cache::read($modelName.'_slugs', 'Slugger.short');
		if (empty($slugs)) {
			$Model = ClassRegistry::init($modelName);
			if ($Model === false) {
				return false;
			}

			if (!$field) {
				$field = $Model->displayField;
			}
			$results = $Model->find('list', array(
				'fields' => array(
					$Model->name.'.'.$Model->primaryKey,
					$Model->name.'.'.$field,
				),
				'recursive' => -1
			));
			if (empty($results)) {
				return array();
			}
			
			$results = Set::filter($results);
			$slugs = array_map(array($this, '_slug'), $results);
			foreach ($slugs as $key => &$slug) {
				$slug = $this->slug($key, $results);
			}
			Cache::write($modelName.'_slugs', $slugs, 'Slugger.short');
		}		
		
		Cache::config($originalCacheConfig);
		return $slugs;
	}
}

?>