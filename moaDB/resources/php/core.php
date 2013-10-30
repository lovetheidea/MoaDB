<?php


/**
 * Copyright (C) 2013 MoaDB
 * @license GPL v3
 */

/**
 * Core-functionality tools
 */
class get {

	/**
	 * Opens up public access to config constants and variables and the cache object
	 * @var object
	 */
	public static $config;

	/**
	 * Index of objects loaded, used to maintain uniqueness of singletons
	 * @var array
	 */
	public static $loadedObjects = array();

	/**
	 * Is PHP Version 5.2.3 or better when htmlentities() added its fourth argument
	 * @var Boolean
	 */
	public static $isPhp523orNewer = true;

	/**
	 * Gets the current URL
	 *
	 * @param array Optional, keys:
	 * get - Boolean Default: false - include GET URL if it exists
	 * abs - Boolean Default: false - true=absolute URL (aka. FQDN), false=just the path for relative links
	 * ssl - Boolean Default: null  - true=https, false=http, unset/null=auto-selects the current protocol
	 * a true or false value implies abs=true
	 * @return string
	 */
	public static function url(array $args = array()) {
		$ssl = null;
		$get = false;
		$abs = false;
		extract($args);

		if (!isset($_SERVER['HTTP_HOST']) && PHP_SAPI == 'cli') {
			$_SERVER['HTTP_HOST'] = trim(`hostname`);
			$argv = $_SERVER['argv'];
			array_shift($argv);
			$_SERVER['REDIRECT_URL'] = '/' . implode('/', $argv);
			$get = false; // command-line has no GET
		}

		$url = (isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['SCRIPT_NAME']);
		if (substr($url, -1) == '/') { //strip trailing slash for URL consistency
			$url = substr($url, 0, -1);
		}

		if (is_null($ssl) && $abs == true) {
			$ssl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
		}
		if ($abs || !is_null($ssl)) {
			$url = (!$ssl ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $url;
		}

		if ($get && isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING']) {
			$url .= '?' . $_SERVER['QUERY_STRING'];
		}
		return ($url ? $url : '/');
	}

	/**
	 * Overloads the php function htmlentities and changes the default charset to UTF-8 and the default value for the
	 * fourth parameter $doubleEncode to false. Also adds ability to pass a null value to get the default $quoteStyle
	 * and $charset (removes need to repeatedly define ENT_COMPAT, 'UTF-8', just to access the $doubleEncode argument)
	 *
	 * If you are using a PHP version prior to 5.2.3 the $doubleEncode parameter is not available and won't do anything
	 *
	 * @param string $string
	 * @param int $quoteStyle Uses ENT_COMPAT if null or omitted
	 * @param string $charset Uses UTF-8 if null or omitted
	 * @param boolean $doubleEncode This is ignored in old versions of PHP before 5.2.3
	 * @return string
	 */
	public static function htmlentities($string, $quoteStyle = ENT_COMPAT, $charset = 'UTF-8', $doubleEncode = false) {
		$quoteStyle = (!is_null($quoteStyle) ? $quoteStyle : ENT_COMPAT);
		$charset = (!is_null($charset) ? $charset : 'UTF-8');
		return (self::$isPhp523orNewer ? htmlentities($string, $quoteStyle, $charset, $doubleEncode) : htmlentities($string, $quoteStyle, $charset));
	}

	/**
	 * Initialize the character maps needed for the xhtmlentities() method and verifies the argument values
	 * passed to it are valid.
	 *
	 * @param int $quoteStyle
	 * @param string $charset Only valid options are UTF-8 and ISO-8859-1 (Latin-1)
	 * @param boolean $doubleEncode
	 */
	protected static function initXhtmlentities($quoteStyle, $charset, $doubleEncode) {
		$chars = get_html_translation_table(HTML_ENTITIES, $quoteStyle);
		if (isset($chars)) {
			unset($chars['<'], $chars['>']);
			$charMaps[$quoteStyle]['ISO-8859-1'][true] = $chars;
			$charMaps[$quoteStyle]['ISO-8859-1'][false] = array_combine(array_values($chars), $chars);
			$charMaps[$quoteStyle]['UTF-8'][true] = array_combine(array_map('utf8_encode', array_keys($chars)), $chars);
			$charMaps[$quoteStyle]['UTF-8'][false] = array_merge($charMaps[$quoteStyle]['ISO-8859-1'][false], $charMaps[$quoteStyle]['UTF-8'][true]);
			self::$loadedObjects['xhtmlEntities'] = $charMaps;
		}
		if (!isset($charMaps[$quoteStyle][$charset][$doubleEncode])) {
			if (!isset($chars)) {
				$invalidArgument = 'quoteStyle = ' . $quoteStyle;
			} else if (!isset($charMaps[$quoteStyle][$charset])) {
				$invalidArgument = 'charset = ' . $charset;
			} else {
				$invalidArgument = 'doubleEncode = ' . (string) $doubleEncode;
			}
			trigger_error('Undefined argument sent to xhtmlentities() method: ' . $invalidArgument, E_USER_NOTICE);
		}
	}

	/**
	 * Converts special characters in a string to XHTML-valid ASCII encoding the same as htmlentities except
	 * this method allows the use of HTML tags within your string. Signature is the same as htmlentities except
	 * that the only character sets available (third argument) are UTF-8 (default) and ISO-8859-1 (Latin-1).
	 *
	 * @param string $string
	 * @param int $quoteStyle Constants available are ENT_NOQUOTES (default), ENT_QUOTES, ENT_COMPAT
	 * @param string $charset Only valid options are UTF-8 (default) and ISO-8859-1 (Latin-1)
	 * @param boolean $doubleEncode Default is false
	 * @return string
	 */
	public static function xhtmlentities($string, $quoteStyle = ENT_NOQUOTES, $charset = 'UTF-8', $doubleEncode = false) {
		$quoteStyles = array(ENT_NOQUOTES, ENT_QUOTES, ENT_COMPAT);
		$quoteStyle = (!in_array($quoteStyle, $quoteStyles) ? current($quoteStyles) : $quoteStyle);
		$charset = ($charset != 'ISO-8859-1' ? 'UTF-8' : $charset);
		$doubleEncode = (Boolean) $doubleEncode;
		if (!isset(self::$loadedObjects['xhtmlEntities'][$quoteStyle][$charset][$doubleEncode])) {
			self::initXhtmlentities($quoteStyle, $charset, $doubleEncode);
		}
		return strtr($string, self::$loadedObjects['xhtmlEntities'][$quoteStyle][$charset][$doubleEncode]);
	}

	/**
	 * Loads an object as a singleton
	 *
	 * @param string $objectType
	 * @param string $objectName
	 * @return object
	 */
	protected static function _loadObject($objectType, $objectName) {
		if (isset(self::$loadedObjects[$objectType][$objectName])) {
			return self::$loadedObjects[$objectType][$objectName];
		}
		$objectClassName = $objectName . ucfirst($objectType);
		if (class_exists($objectClassName)) {
			$objectObject = new $objectClassName;
			self::$loadedObjects[$objectType][$objectName] = $objectObject;
			return $objectObject;
		} else {
			$errorMsg = 'Class for ' . $objectType . ' ' . $objectName . ' could not be found';
		}
		trigger_error($errorMsg, E_USER_WARNING);
	}

	/**
	 * Returns a helper object
	 *
	 * @param string $model
	 * @return object
	 */
	public static function helper($helper) {
		if (is_array($helper)) {
			array_walk($helper, array('self', __METHOD__));
			return;
		}
		if (!isset(self::$config['helpers']) || !in_array($helper, self::$config['helpers'])) {
			self::$config['helpers'][] = $helper;
		}
		return self::_loadObject('helper', $helper);
	}

}

/**
 * Public interface to load elements and cause redirects
 */
class load {

	/**
	 * Sends a redirects header and disables view rendering
	 * This redirects via a browser command, this is not the same as changing controllers which is handled within MVC
	 *
	 * @param string $url Optional, if undefined this will refresh the page (mostly useful for dumping post values)
	 */
	public static function redirect($url = null) {
		header('Location: ' . ($url ? $url : get::url(array('get' => true))));
	}

}

/**
 * Thrown when the mongod server is not accessible
 */
class cannotConnectToMongoServer extends Exception {

	public function __toString() {
		return '<h1>Cannot connect to the MongoDB database.</h1> ' . PHP_EOL . 'If Mongo is installed then be sure that'
				. ' an instance of the "mongod" server, not "mongo" shell, is running. <br />' . PHP_EOL
				. 'Instructions and database download: <a href="http://php.net/manual/en/mongo.installation.php">http://php.net/manual/en/mongo.installation.php</a>';
	}

}

/**
 * Thrown when the mongo extension for PHP is not installed
 */
class mongoExtensionNotInstalled extends Exception {

	public function __toString() {
		return '<h1>PHP cannot access MongoDB, you need to install the Mongo extension for PHP.</h1> '
				. PHP_EOL . 'Instructions and driver download: '
				. '<a href="http://php.net/manual/en/mongo.installation.php">http://php.net/manual/en/mongo.installation.php</a>';
	}

}

/**
 * moaDB specific functionality
 */
class moaDB {
	/**
	 * Sets the depth limit for moaDB::getArrayKeys (and prevents an endless loop with self-referencing objects)
	 */

	const DRILL_DOWN_DEPTH_LIMIT = 8;

	/**
	 * Retrieves all the keys & subkeys of an array recursively drilling down
	 *
	 * @param array $array
	 * @param string $path
	 * @param int $drillDownDepthCount
	 * @return array
	 */
	public static function getArrayKeys(array $array, $path = '', $drillDownDepthCount = 0) {
		$return = array();
		if ($drillDownDepthCount) {
			$path .= '.';
		}
		if (++$drillDownDepthCount < self::DRILL_DOWN_DEPTH_LIMIT) {
			foreach ($array as $key => $val) {
				$return[$id] = $id = $path . $key;
				if (is_array($val)) {
					$return = array_merge($return, self::getArrayKeys($val, $id, $drillDownDepthCount));
				}
			}
		}
		return $return;
	}

	/**
	 * Strip slashes recursively - used only when magic quotes is enabled (this reverses magic quotes)
	 *
	 * @param mixed $val
	 * @return mixed
	 */
	public static function stripslashes($val) {
		return (is_array($val) ? array_map(array('self', 'stripslashes'), $val) : stripslashes($val));
	}

}