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
 * @link http://42pixels.com/blog/slugs-ugly-bugs-pretty-urls
 * @link http://mark-story.com/posts/view/using-custom-route-classes-in-cakephp
 */
class SluggableRoute extends CakeRoute {

/**
 * Internal attribute used to store the original cache config between
 * _initSluggerCache and _restoreOriginalCache
 *
 * @var string
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
					if (!isset($slugSet[$pass]) && isset($this->options['autoInvalidate']) && $this->options['autoInvalidate']) {
						$this->invalidateCache($checkNamed);
						$slugSet = $this->getSlugs($checkNamed, $slugField);
						$slugSet = array_flip($slugSet);
					}
					if (isset($slugSet[$pass])) {
						unset($passed[$key]);
						$passed[] = $checkNamed.':'.$slugSet[$pass];
					} elseif (isset($this->options['prependPk']) && $this->options['prependPk']) {
						$slugSet = $this->getSlugs($checkNamed, $slugField);
						$pk = $this->_extractPk($pass);
						if (isset($slugSet[$pk])) {
							unset($passed[$key]);
							$passed[] = $checkNamed.':'.$pk;
						}
						$slugSet = array_flip($slugSet);
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
					if (!isset($slugSet[$url[$checkNamed]]) && isset($this->options['autoInvalidate']) && $this->options['autoInvalidate']) {
						$this->invalidateCache($checkNamed);
						$slugSet = $this->getSlugs($checkNamed, $slugField);
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
 * Slugs a string using a custom function if defined. If no custom function is
 * defined, it defaults to a strtolower'd `Inflector::slug($str, '-');`
 *
 * @param string $str The string to slug
 * @param string $str Replacement character
 * @return string
 */
	function _slug($str, $replacement = '-') {
		if (isset($this->options['slugFunction'])) {
			return call_user_func($this->options['slugFunction'], $str);
		}
		return strtolower(Inflector::slug($str, $replacement));
	}

/**
 * Extracts a PK from an existing slug.
 *
 * @param string $str The string to extract the pk from
 * @return integer
 */
	function _extractPk($str) {
		if (isset($this->options['extractPkFunction'])) {
			return call_user_func($this->options['extractPkFunction'], $str);
		}
		$exp = explode('-', $str);
		return $exp[0];
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
			$slugs = $Model->find('all', array(
				'fields' => array(
					$Model->name.'.'.$Model->primaryKey,
					$Model->name.'.'.$field,
				),
				'recursive' => -1
			));
			$counts = $Model->find('all', array(
				'fields' => array(					
					'LOWER(TRIM('.$Model->name.'.'.$field.')) AS '.$field,
					'COUNT(*) AS count'
				),
				'group' => array(
					$field
				)
			));
			$counts = Set::combine($counts, '{n}.0.'.$field, '{n}.0.count');
			$listedSlugs = array();
			foreach ($slugs as $pk => $fields) {
				$values = array(
					'_field' => $fields[$Model->name][$field],
					'_count' => $counts[strtolower(trim($fields[$Model->name][$field]))],
					'_pk' => $fields[$Model->name][$Model->primaryKey]
				);
				$listedSlugs[$fields[$Model->name][$Model->primaryKey]] = $this->slug($values);
			}
			Cache::write($modelName.'_slugs', $listedSlugs, $cacheConfig);
			$this->{$modelName.'_slugs'} = $listedSlugs;
		}
		
		$this->_restoreOriginalCache();
		return $this->{$modelName.'_slugs'};
	}

/**
 * Invalidate cached slugs for a given model or entry
 *
 * @param string $modelName Name of the model to invalidate cache for
 * @param string $id If of the only entry to update
 * @return boolean True if the value was succesfully deleted, false if it didn't exist or couldn't be removed
 * @access public
 */
	function invalidateCache($modelName, $id = null) {
		$cacheConfig = $this->_initSluggerCache();

		if (is_null($id)) {
			$result = Cache::delete($modelName.'_slugs', $cacheConfig);
			unset($this->{$modelName.'_slugs'});
		} else {
			$slugs = Cache::read($modelName.'_slugs', $cacheConfig);
			if ($slugs === false) {
				$result = false;
			} else {
				$slugs[$id] = $this->_generateSlug($modelName, $id);
				if ($slugs[$id] === false) {
					unset($slugs[$id]);
				}
				if (isset($this->{$modelName.'_slugs'}) && $slugs[$id] !== false) {
					$this->{$modelName.'_slugs'}[$id] = $slugs[$id];
				}
				$result = Cache::write($modelName.'_slugs', $slugs, $cacheConfig);
			}
		}

		$this->_restoreOriginalCache();
		return $result;
	}

/**
 * Generates a slug for a given model and id from the database
 *
 * @param string $modelName The name of the model
 * @param string $id Id of the entry to generate a slug for
 * @return mixed False if the config is not found for this model or the entry
 *	does not exist. The generated slug otherwise
 * @access protected
 */
	function _generateSlug($modelName, $id) {
		$slug = false;

		if (isset($this->options['models'])) {
			if (array_key_exists($modelName, $this->options['models'])) {
				$slugField = $this->options['models'][$modelName];
			} elseif (array_search($modelName, $this->options['models']) !== false) {
				$slugField = false;
			}

			if (isset($slugField)) {
				$Model = ClassRegistry::init($modelName);
				if ($Model !== false) {
					if (!$slugField) {
						$slugField = $Model->displayField;
					}
					$text = $Model->field($slugField, array(
						$Model->name.'.'.$Model->primaryKey => $id
					));
					if ($text !== false) {
						$count = $Model->find('count', array(
							'conditions' => array($Model->name.'.'.$slugField => $text)
						));
						$values = array('_field' => $text, '_count' => $count, '_pk' => $id);
						$slug = $this->slug($values);
					}
				}
			}
		}

		return $slug;
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