<?php
/**
 * Sluggable route class.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       Slugger
 * @subpackage    Slugger.Routing/Route
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
 App::uses('CakeRoute', 'Routing/Route');
 App::uses('Hash', 'Utility');
 App::uses('SlugCache', 'Slugger.Cache');

/**
 * Sluggable Route
 *
 * Automatically slugs routes
 *
 * @package       Slugger
 * @subpackage    Slugger.Routing/Route
 * @link http://someguyjeremy.com/blog/slugs-ugly-bugs-pretty-urls
 * @link http://mark-story.com/posts/view/using-custom-route-classes-in-cakephp
 */
class SluggableRoute extends CakeRoute {

/**
 * Whether to prepend the pk by default
 *
 * @var boolean
 */
	protected $prependPk = false;

/**
 * Slugging function
 *
 * @var callable
 */
	protected $slugFunction = null;

/**
 * List of models to search
 *
 * @var array
 */
	protected $models;

/**
 * Sets up cache config and options
 *
 * @param string $template
 * @param array $defaults
 * @param array $options
 */
	public function __construct($template, $defaults = array(), $options = array()) {
		parent::__construct($template, $defaults, $options);
		$this->config();
	}

/**
 * Override the parsing function to find an id based on a slug
 *
 * @param string $url Url string
 * @return boolean
 */
    public function parse($url) {
		$params = parent::parse($url);

		if (empty($params)) {
			return false;
		}

		if (!empty($this->models) && !empty($params['pass'])) {
			foreach ($this->models as $modelName => $options) {
				list($paramType, $paramName) = $this->params($options);
				$slugSet = $this->getSlugs($modelName);
				if (empty($slugSet)) {
					continue;
				}
				$slugSet = array_flip($slugSet);
				foreach ($params['pass'] as $key => $param) {
					if (isset($slugSet[$param])) {
						unset($params['pass'][$key]);
						if ($paramType == 'pass' && !is_numeric($paramName)) {
							$params[$paramName] = $slugSet[$param];
							$params[$paramType][$key] = $slugSet[$param];
						} else {
							$params[$paramType][$paramName] = $slugSet[$param];
						}
					}
				}
			}
			return $params;
		}

		return false;
	}

/**
 * Matches the model's id and converts it to a slug
 *
 * @param array $url Cake url array
 * @return boolean
 */
	public function match($url) {
		foreach ($this->models as $modelName => $options) {
			list($paramType, $paramName) = $this->params($options);
			$slugSet = $this->getSlugs($modelName);
			if (empty($slugSet)) {
				continue;
			}
			switch ($paramType) {
				case 'pass':
					if (isset($url[$paramName]) && isset($slugSet[$url[$paramName]])) {
						$url[$paramName] = $slugSet[$url[$paramName]];
					}
					break;
				case 'named':
					if (isset($url[$paramName]) && isset($slugSet[$url[$paramName]])) {
						$url[] = $slugSet[$url[$paramName]];
						unset($url[$paramName]);
					}
					break;
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
	public function slug($slug) {
		$str = $slug['_field'];
		if ($slug['_count'] > 1 || $this->prependPk) {
			$str = $slug['_pk'].' '.$str;
		}
		return $this->_slug($str);
	}

/**
 * Normalizes and sets config options as properties
 */
	public function config() {
		if (empty($this->options['models'])) {
			return;
		}
		$this->models = Hash::normalize($this->options['models']);
		if (isset($this->options['prependPk'])) {
			$this->prependPk = $this->options['prependPk'];
		}
		if (isset($this->options['slugFunction'])) {
			$this->slugFunction = $this->options['slugFunction'];
		}
	}

/**
 * Extracts param name and param type from sluggable `param` option
 *
 * @param array $options
 * @return array
 */
	public function params($options = array()) {
		$paramName = 0;
		$paramType = 'pass';
		if (isset($options['param'])) {
			$typeKey = substr($options['param'], 0, 1);
			switch ($typeKey) {
				case ':':
					$paramType = 'pass';
					$paramName = substr($options['param'], 1);
					break;
				default:
					$paramType = 'named';
					$paramName = $options['param'];
			}
		}
		return array($paramType, $paramName);
	}

/**
 * Slugs a string using a custom function if defined. If no custom function is
 * defined, it defaults to a strtolower'd `Inflector::slug($str, '-');`
 *
 * @param string $str The string to slug
 * @param string $str Replacement character
 * @return string
 */
	protected function _slug($str, $replacement = '-') {
		if (!empty($this->slugFunction)) {
			return call_user_func($this->slugFunction, $str);
		}
		return strtolower(Inflector::slug($str, $replacement));
	}

/**
 * Gets slugs, checks cache first
 *
 * @param string $modelName The name of the model
 * @return array Array of slugs
 */
	public function getSlugs($modelName) {
		$slugs = SlugCache::get($modelName);
		if (empty($slugs)) {
			$Model = ClassRegistry::init($modelName, true);
			if ($Model === false) {
				return false;
			}
			$field = $Model->displayField;
			if (!empty($this->models[$modelName]['slugField'])) {
				$field = $this->models[$modelName]['slugField'];
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
			SlugCache::set($modelName, $listedSlugs);
		}

		return SlugCache::get($modelName);
	}

}