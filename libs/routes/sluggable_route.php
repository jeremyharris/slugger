<?php
/**
 * Sluggable route class.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       slugger
 * @subpackage    slugger.libs.routes
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Sluggable Route
 *
 * Automatically slugs routes based on named parameters
 *
 * @package       slugger
 * @subpackage    slugger.libs.routes
 * @link http://mark-story.com/posts/view/using-custom-route-classes-in-cakephp
 */
class SluggableRoute extends CakeRoute {

/**
 * Internal attribute used to store the original cache config between
 * _initSluggerCache and _restoreOriginalCache
 * 
 * @access private
 */
	var $__originalCacheConfig = null;

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
				if (empty($slugSet)) {
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
					if (empty($slugSet)) {
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
 * @param array $slug The slug array (containing keys '_field' and '_count')
 * @return string
 */
	function slug($slug) {
		$str = $slug['_field'];
		if ($slug['_count'] > 1 || (isset($this->options['prependPk']) && $this->options['prependPk'])) {
			$str = $slug['_pk'].' '.$str;
		}
		return $this->_slug($str);
	}

/**
 * Slugs a string
 *
 * Defaults to Inflector::slug() if it can't do it faster
 *
 * @param string $str The string to slug
 * @param string $str Replacement character
 * @return string
 */
	function _slug($str, $replacement = '-') {
		if (function_exists('iconv')) {
			$str = preg_replace('/[^a-z0-9 ]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str));
			$quotedReplacement = preg_quote($replacement, '/');
			$merge = array(
				'/[^\s\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]/mu' => ' ',
				'/\\s+/' => $replacement,
				sprintf('/^[%s]+|[%s]+$/', $quotedReplacement, $quotedReplacement) => '',
			);
			return strtolower(preg_replace(array_keys($merge), array_values($merge), $str));
		}
		return strtolower(Inflector::slug($str, $replacement));
	}

/**
 * Gets slugs from cache and store in variable for this request
 *
 * @param string $modelName The name of the model
 * @param string $field The field to pull
 * @return array Array of slugs
 */
	function getSlugs($modelName, $field = null) {
		$cacheConfig = $this->_initSluggerCache();

		if (!isset($this->{$modelName.'_slugs'})) {
			$this->{$modelName.'_slugs'} = Cache::read($modelName.'_slugs', $cacheConfig);
		}
		if (empty($this->{$modelName.'_slugs'})) {
			$Model = ClassRegistry::init($modelName);
			if ($Model === false) {
				return false;
			}
			if (!$field) {
				$field = $Model->displayField;
			}
			$start = microtime(true);
			$slugs = $Model->find('list', array(
				'fields' => array(
					$Model->name.'.'.$Model->primaryKey,
					$Model->name.'.'.$field,
				),
				'recursive' => -1
			));
			$counts = $Model->find('all', array(
				'fields' => array(					
					$Model->name.'.'.$field,
					'COUNT(*) AS count'
				),
				'group' => array(
					$Model->name.'.'.$field
				)
			));
			$counts = Set::combine($counts, '{n}.'.$Model->name.'.'.$field, '{n}.0.count');
			foreach ($slugs as $pk => $field) {
				$values = array(
					'_field' => $field,
					'_count' => $counts[$field],
					'_pk' => $pk
				);
				$slugs[$pk] = $this->slug($values);
			}
			Cache::write($modelName.'_slugs', $slugs, $cacheConfig);
			$this->{$modelName.'_slugs'} = $slugs;
		}
		
		$this->_restoreOriginalCache();
		return $this->{$modelName.'_slugs'};
	}

/**
 * Modifies the Cache configuration to use a specific caching type
 * 
 * @return string New cache config name
 * @access protected
 */
	function _initSluggerCache() {
		$cache = Cache::getInstance();
		$this->__originalCacheConfig = $cache->__name;
		Cache::config('Slugger.short', array(
			'engine' => 'File',
			'duration' => '+1 days',
			'prefix' => 'slugger_'
		));
		return 'Slugger.short';
	}

/**
 * Restore the original Cache configuration
 *
 * @return boolean Success of the restoration
 * @access protected
 */
	function _restoreOriginalCache() {
		$success = false;
		if (!empty($this->__originalCacheConfig)) {
			$success = Cache::config($this->__originalCacheConfig) !== false;
			$this->__originalCacheConfig = null;
		}
		return $success;
	}

}

?>