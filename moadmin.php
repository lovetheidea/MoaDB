<?php
error_reporting(E_ALL | E_STRICT);
/**
 * Set default Time Zone. GMT default
 */
date_default_timezone_set("GMT");

/**
 * phpMoAdmin - built on a stripped-down version of the high-performance Vork Enterprise Framework
 *
 * www.phpMoAdmin.com
 * www.Vork.us
 * www.MongoDB.org
 *
 * @version 1.1.3
 * @author Eric David Benari, Chief Architect, phpMoAdmin
 * @license GPL v3 - http://vork.us/go/mvz5
 */
/**
 * To enable password protection, uncomment below and then change the username => password
 * You can add as many users as needed, eg.: array('scott' => 'tiger', 'samantha' => 'goldfish', 'gene' => 'alpaca')
 */
//$accessControl = array('username' => 'password');

/**
 * Uncomment to restrict databases-access to just the databases added to the array below
 * uncommenting will also remove the ability to create a new database
 */
//moadminModel::$databaseWhitelist = array('admin');

/**
 * Sets the design theme - themes options are: swanky-purse, trontastic, simple-gray and classic
 */
define('THEME', 'trontastic');

/**
 * To connect to a remote or authenticated Mongo instance, define the connection string in the MONGO_CONNECTION constant
 * mongodb://[username:password@]host1[:port1][,host2[:port2:],...]
 * If you do not know what this means then it is not relevant to your application and you can safely leave it as-is
 */
define('MONGO_CONNECTION', '');

/**
 * Set to true when connecting to a Mongo replicaSet
 * If you do not know what this means then it is not relevant to your application and you can safely leave it as-is
 */
define('REPLICA_SET', false);

/**
 * Default limit for number of objects to display per page - set to 0 for no limit
 */
define('OBJECT_LIMIT', 100);

/**
 * Contributing-developers of the phpMoAdmin project should set this to true, everyone else can leave this as false
 */
define('DEBUG_MODE', false);

/**
 * Vork core-functionality tools
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
	 *              get - Boolean Default: false - include GET URL if it exists
	 *              abs - Boolean Default: false - true=absolute URL (aka. FQDN), false=just the path for relative links
	 *              ssl - Boolean Default: null  - true=https, false=http, unset/null=auto-selects the current protocol
	 *                                             a true or false value implies abs=true
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
				. 'Instructions and database download: <a href="http://vork.us/go/fhk4">http://vork.us/go/fhk4</a>';
	}

}

/**
 * Thrown when the mongo extension for PHP is not installed
 */
class mongoExtensionNotInstalled extends Exception {

	public function __toString() {
		return '<h1>PHP cannot access MongoDB, you need to install the Mongo extension for PHP.</h1> '
				. PHP_EOL . 'Instructions and driver download: '
				. '<a href="http://vork.us/go/tv27">http://vork.us/go/tv27</a>';
	}

}

/**
 * phpMoAdmin data model
 */
class moadminModel {

	/**
	 * mongo connection - if a MongoDB object already exists (from a previous script) then only DB operations use this
	 * @var Mongo
	 */
	protected $_db;

	/**
	 * Name of last selected DB
	 * @var string Defaults to admin as that is available in all Mongo instances
	 */
	public static $dbName = 'admin';

	/**
	 * MongoDB
	 * @var MongoDB
	 */
	public $mongo;

	/**
	 * Returns a new Mongo connection
	 * @return Mongo
	 */
	protected function _mongo() {
		$connection = (!MONGO_CONNECTION ? 'mongodb://localhost:27017' : MONGO_CONNECTION);
		$Mongo = (class_exists('MongoClient') === true ? 'MongoClient' : 'Mongo');
		return (!REPLICA_SET ? new $Mongo($connection) : new $Mongo($connection, array('replicaSet' => true)));
	}

	/**
	 * Connects to a Mongo database if the name of one is supplied as an argument
	 * @param string $db
	 */
	public function __construct($db = null) {
		if (self::$databaseWhitelist && !in_array($db, self::$databaseWhitelist)) {
			$db = self::$dbName = $_GET['db'] = current(self::$databaseWhitelist);
		}
		if ($db) {
			if (!extension_loaded('mongo')) {
				throw new mongoExtensionNotInstalled();
			}
			try {
				$this->_db = $this->_mongo();
				$this->mongo = $this->_db->selectDB($db);
			} catch (MongoConnectionException $e) {
				throw new cannotConnectToMongoServer();
			}
		}
	}

	/**
	 * Executes a native JS MongoDB command
	 * This method is not currently used for anything
	 * @param string $cmd
	 * @return mixed
	 */
	protected function _exec($cmd) {
		$exec = $this->mongo->execute($cmd);
		return $exec['retval'];
	}

	/**
	 * Change the DB connection
	 * @param string $db
	 */
	public function setDb($db) {
		if (self::$databaseWhitelist && !in_array($db, self::$databaseWhitelist)) {
			$db = current(self::$databaseWhitelist);
		}
		if (!isset($this->_db)) {
			$this->_db = $this->_mongo();
		}
		$this->mongo = $this->_db->selectDB($db);
		self::$dbName = $db;
	}

	/**
	 * Total size of all the databases
	 * @var int
	 */
	public $totalDbSize = 0;

	/**
	 * Adds ability to restrict databases-access to those on the whitelist
	 * @var array
	 */
	public static $databaseWhitelist = array();

	/**
	 * Gets list of databases
	 * @return array
	 */
	public function listDbs() {
		$return = array();
		$restrictDbs = (bool) self::$databaseWhitelist;
		$dbs = $this->_db->selectDB('admin')->command(array('listDatabases' => 1));
		$this->totalDbSize = $dbs['totalSize'];
		foreach ($dbs['databases'] as $db) {
			if (!$restrictDbs || in_array($db['name'], self::$databaseWhitelist)) {
				$return[$db['name']] = '('
						. (!$db['empty'] ? round($db['sizeOnDisk'] / 1000000) . 'mb' : 'empty') . ')';
			}
		}
		ksort($return);
		return $return;
	}

	/**
	 * Returns the binary blob for database-image.png 
	 * @var string
	 */
	public static function getDatabaseImage() {
		return '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
		gg==';
	}

	/**
	 * Generate system info and stats
	 * @return array
	 */
	public function getStats() {
		$admin = $this->_db->selectDB('admin');
		$return = array_merge($admin->command(array('buildinfo' => 1)), $admin->command(array('serverStatus' => 1)));
		$profile = $admin->command(array('profile' => -1));
		$return['profilingLevel'] = $profile['was'];
		$return['mongoDbTotalSize'] = round($this->totalDbSize / 1000000) . 'mb';
		$prevError = $admin->command(array('getpreverror' => 1));
		if (!$prevError['n']) {
			$return['previousDbErrors'] = 'None';
		} else {
			$return['previousDbErrors']['error'] = $prevError['err'];
			$return['previousDbErrors']['numberOfOperationsAgo'] = $prevError['nPrev'];
		}
		$return['globalLock']['totalTime'] .= ' &#0181;Sec';
		$return['uptime'] = round($return['uptime'] / 60) . ':' . str_pad($return['uptime'] % 60, 2, '0', STR_PAD_LEFT)
				. ' minutes';
		$unshift['mongo'] = $return['version'] . ' (' . $return['bits'] . '-bit)';
		$unshift['mongoPhpDriver'] = Mongo::VERSION;
		$unshift['phpMoAdmin'] = '1.0.9';
		$unshift['php'] = PHP_VERSION . ' (' . (PHP_INT_MAX > 2200000000 ? 64 : 32) . '-bit)';
		$unshift['gitVersion'] = $return['gitVersion'];
		unset($return['ok'], $return['version'], $return['gitVersion'], $return['bits']);
		$return = array_merge(array('version' => $unshift), $return);
		$iniIndex = array(-1 => 'Unlimited', 'Off', 'On');
		$phpIni = array('allow_persistent', 'auto_reconnect', 'chunk_size', 'cmd', 'default_host', 'default_port',
			'max_connections', 'max_persistent');
		foreach ($phpIni as $ini) {
			$key = 'php_' . $ini;
			$return[$key] = ini_get('mongo.' . $ini);
			if (isset($iniIndex[$return[$key]])) {
				$return[$key] = $iniIndex[$return[$key]];
			}
		}
		return $return;
	}

	/**
	 * Repairs a database
	 * @return array Success status
	 */
	public function repairDb() {
		return $this->mongo->repair();
	}

	/**
	 * Drops a database
	 */
	public function dropDb() {
		$this->mongo->drop();
		return;
		if (!isset($this->_db)) {
			$this->_db = $this->_mongo();
		}
		$this->_db->dropDB($this->mongo);
	}

	/**
	 * Gets a list of database collections
	 * @return array
	 */
	public function listCollections() {
		$collections = array();
		$MongoCollectionObjects = $this->mongo->listCollections();
		foreach ($MongoCollectionObjects as $collection) {
			$collection = substr(strstr((string) $collection, '.'), 1);
			$collections[$collection] = $this->mongo->selectCollection($collection)->count();
		}
		ksort($collections);
		return $collections;
	}

	/**
	 * Drops a collection
	 * @param string $collection
	 */
	public function dropCollection($collection) {
		$this->mongo->selectCollection($collection)->drop();
	}

	/**
	 * Creates a collection
	 * @param string $collection
	 */
	public function createCollection($collection) {
		if ($collection) {
			$this->mongo->createCollection($collection);
		}
	}

	/**
	 * Renames a collection
	 *
	 * @param string $from
	 * @param string $to
	 */
	public function renameCollection($from, $to) {
		$result = $this->_db->selectDB('admin')->command(array(
			'renameCollection' => self::$dbName . '.' . $from,
			'to' => self::$dbName . '.' . $to,
				));
	}

	/**
	 * Gets a list of the indexes on a collection
	 *
	 * @param string $collection
	 * @return array
	 */
	public function listIndexes($collection) {
		return $this->mongo->selectCollection($collection)->getIndexInfo();
	}

	/**
	 * Ensures an index
	 *
	 * @param string $collection
	 * @param array $indexes
	 * @param array $unique
	 */
	public function ensureIndex($collection, array $indexes, array $unique) {
		$unique = ($unique ? true : false); //signature requires a bool in both Mongo v. 1.0.1 and 1.2.0
		$this->mongo->selectCollection($collection)->ensureIndex($indexes, $unique);
	}

	/**
	 * Removes an index
	 *
	 * @param string $collection
	 * @param array $index Must match the array signature of the index
	 */
	public function deleteIndex($collection, array $index) {
		$this->mongo->selectCollection($collection)->deleteIndex($index);
	}

	/**
	 * Sort array - currently only used for collections
	 * @var array
	 */
	public $sort = array('_id' => 1);

	/**
	 * Number of rows in the entire resultset (before limit-clause is applied)
	 * @var int
	 */
	public $count;

	/**
	 * Array keys in the first and last object in a collection merged together (used to build sort-by options)
	 * @var array
	 */
	public $colKeys = array();

	/**
	 * Get the records in a collection
	 *
	 * @param string $collection
	 * @return array
	 */
	public function listRows($collection) {
		foreach ($this->sort as $key => $val) { //cast vals to int
			$sort[$key] = (int) $val;
		}
		$col = $this->mongo->selectCollection($collection);

		$find = array();
		if (isset($_GET['find']) && $_GET['find']) {
			$_GET['find'] = trim($_GET['find']);
			if (strpos($_GET['find'], 'array') === 0) {
				eval('$find = ' . $_GET['find'] . ';');
			} else if (is_string($_GET['find'])) {
				$findArr = json_decode($_GET['find'], true);
				if ($findArr) {
					$find = $findArr;
				} else if (!$findArr && isset($_GET['find'])) {
					//Not valid JSON :)
				}
			}
		}

		$removequery = array();
		if (isset($_GET['remove']) && $_POST['remove']) {
			$_POST['remove'] = trim($_POST['remove']);
			if (strpos($_POST['remove'], 'array') === 0) {
				eval('$remove = ' . $_POST['remove'] . ';');
			} else if (is_string($_POST['remove'])) {
				$removeArr = json_decode($_POST['remove'], true);
				if ($removeArr) {
					$removequery = $removeArr;
				}
			}
			if (!empty($removequery)) {
				$col->remove($removequery);
			}
		}

		if (isset($_GET['search']) && $_GET['search']) {
			switch (substr(trim($_GET['search']), 0, 1)) { //first character
				case '/': //regex
					$find[$_GET['searchField']] = new mongoRegex($_GET['search']);
					break;
				case '{': //JSON
					if ($search = json_decode($_GET['search'], true)) {
						$find[$_GET['searchField']] = $search;
					}
					break;
				case '(':
					$types = array('bool', 'boolean', 'int', 'integer', 'float', 'double', 'string', 'array', 'object',
						'null', 'mongoid');
					$closeParentheses = strpos($_GET['search'], ')');
					if ($closeParentheses) {
						$cast = strtolower(substr($_GET['search'], 1, ($closeParentheses - 1)));
						if (in_array($cast, $types)) {
							$search = trim(substr($_GET['search'], ($closeParentheses + 1)));
							if ($cast == 'mongoid') {
								$search = new MongoID($search);
							} else {
								settype($search, $cast);
							}
							$find[$_GET['searchField']] = $search;
							break;
						}
					} //else no-break
				default: //text-search
					if (strpos($_GET['search'], '*') === false) {
						if (!is_numeric($_GET['search'])) {
							$find[$_GET['searchField']] = $_GET['search'];
						} else { //$_GET is always a string-type
							$in = array((string) $_GET['search'], (int) $_GET['search'], (float) $_GET['search']);
							$find[$_GET['searchField']] = array('$in' => $in);
						}
					} else { //text with wildcards
						$regex = '/' . str_replace('\*', '.*', preg_quote($_GET['search'])) . '/i';
						$find[$_GET['searchField']] = new mongoRegex($regex);
					}
					break;
			}
		}
//		Test document find
//		print_r($find);
//		$cursor = $col->find($find);
//		foreach ($cursor as $doc) {
//			var_dump($doc);
//		}	exit;
		$cols = (!isset($_GET['cols']) ? array() : array_fill_keys($_GET['cols'], true));
		$cur = $col->find($find, $cols)->sort($sort);
		$this->count = $cur->count();

		//get keys of first object
		if ($_SESSION['limit'] && $this->count > $_SESSION['limit'] //more results than per-page limit
				&& (!isset($_GET['export']) || $_GET['export'] != 'nolimit')) {
			if ($this->count > 1) {
				$this->colKeys = phpMoAdmin::getArrayKeys($col->findOne());
			}
			$cur->limit($_SESSION['limit']);
			if (isset($_GET['skip'])) {
				if ($this->count <= $_GET['skip']) {
					$_GET['skip'] = ($this->count - $_SESSION['limit']);
				}
				$cur->skip($_GET['skip']);
			}
		} else if ($this->count) { // results exist but are fewer than per-page limit
			$this->colKeys = phpMoAdmin::getArrayKeys($cur->getNext());
		} else if ($find && $col->count()) { //query is not returning anything, get cols from first obj in collection
			$this->colKeys = phpMoAdmin::getArrayKeys($col->findOne());
		}

		//get keys of last or much-later object
		if ($this->count > 1) {
			$curLast = $col->find()->sort($sort);
			if ($this->count > 2) {
				$curLast->skip(min($this->count, 100) - 1);
			}
			$this->colKeys = array_merge($this->colKeys, phpMoAdmin::getArrayKeys($curLast->getNext()));
			ksort($this->colKeys);
		}
		return $cur;
	}

	/**
	 * Returns a serialized element back to its native PHP form
	 *
	 * @param string $_id
	 * @param string $idtype
	 * @return mixed
	 */
	protected function _unserialize($_id, $idtype) {
		if ($idtype == 'object' || $idtype == 'array') {
			$errLevel = error_reporting();
			error_reporting(0); //unserializing an object that is not serialized throws a warning
			$_idObj = unserialize($_id);
			error_reporting($errLevel);
			if ($_idObj !== false) {
				$_id = $_idObj;
			}
		} else if (gettype($_id) != $idtype) {
			settype($_id, $idtype);
		}
		return $_id;
	}

	/**
	 * Removes an object from a collection
	 *
	 * @param string $collection
	 * @param string $_id
	 * @param string $idtype
	 */
	public function removeObject($collection, $_id, $idtype) {
		$this->mongo->selectCollection($collection)->remove(array('_id' => $this->_unserialize($_id, $idtype)));
	}

	/**
	 * Removes an object or multiple objects from a collection using query
	 *
	 * @param string $collection
	 * @param string $_id
	 * @param string $idtype
	 */
	public function removeQuery($collection, $query) {
		$this->mongo->selectCollection($collection)->remove($query);
	}

	/**
	 * Retieves an object for editing
	 *
	 * @param string $collection
	 * @param string $_id
	 * @param string $idtype
	 * @return array
	 */
	public function editObject($collection, $_id, $idtype) {
		return $this->mongo->selectCollection($collection)->findOne(array('_id' => $this->_unserialize($_id, $idtype)));
	}

	/**
	 * Saves an object
	 *
	 * @param string $collection
	 * @param string $obj
	 * @return array
	 */
	public function saveObject($collection, $obj) {
		//Change object to array in order to save.
		if (!is_array($obj)) {
			$object = json_decode($obj, true);
			if ($object) {
				if (isset($object['_id']) && (isset($object['_id']['$id']) || isset($object['_id']['$oid'])))
					$object['_id'] = new MongoId(array_shift(array_values($object['_id'])));
				#TODO map to more Mongo types
			}
		}
		if (!$object)
			$object = (array) $obj;
		//eval('$object=' . $obj . ';'); //cast from string to array

		try {
			$v = $this->mongo->selectCollection($collection)->save($object);
		} catch (Exception $e) {
			return false;
		}
		return $v;
	}

	/**
	 * Imports data into the current collection
	 *
	 * @param string $collection
	 * @param array $data
	 * @param string $importMethod Valid options are batchInsert, save, insert, update
	 */
	public function import($collection, array $data, $importMethod) {
		$coll = $this->mongo->selectCollection($collection);
		switch ($importMethod) {
			case 'batchInsert':
				foreach ($data as &$obj) {
					$obj = unserialize($obj);
				}
				$coll->$importMethod($data);
				break;
			case 'update':
				foreach ($data as $obj) {
					$obj = unserialize($obj);
					if (is_object($obj) && property_exists($obj, '_id')) {
						$_id = $obj->_id;
					} else if (is_array($obj) && isset($obj['_id'])) {
						$_id = $obj['_id'];
					} else {
						continue;
					}
					$coll->$importMethod(array('_id' => $_id), $obj);
				}
				break;
			default: //insert & save
				foreach ($data as $obj) {
					$coll->$importMethod(unserialize($obj));
				}
				break;
		}
	}

}

/**
 * phpMoAdmin application control
 */
class moadminComponent {

	/**
	 * $this->mongo is used to pass properties from component to view without relying on a controller to return them
	 * @var array
	 */
	public $mongo = array();

	/**
	 * Model object
	 * @var moadminModel
	 */
	public static $model;

	/**
	 * Removes the POST/GET params
	 */
	protected function _dumpFormVals() {
		load::redirect(get::url() . '?action=listRows&db=' . urlencode($_GET['db'])
				. '&collection=' . urlencode($_GET['collection']));
	}

	/**
	 * Routes requests and sets return data
	 */
	public function __construct() {
		if (class_exists('mvc')) {
			mvc::$view = '#moadmin';
		}
		$this->mongo['dbs'] = self::$model->listDbs();
		if (isset($_GET['db'])) {
			if (strpos($_GET['db'], '.') !== false)
				$_GET['db'] = $_POST['newdb'];
			self::$model->setDb($_GET['db']);
		}

		if (isset($_GET['remove']))
			$this->_dumpFormVals();

		if (!isset($_POST['newdb']) && !array_key_exists($_GET['db'], $this->mongo['dbs'])) {
			return load::redirect(get::url());
		}

		if (isset($_POST['limit'])) {
			$_SESSION['limit'] = (int) $_POST['limit'];
		} else if (!isset($_SESSION['limit'])) {
			$_SESSION['limit'] = OBJECT_LIMIT;
		}

		if (isset($_FILES['import']) && is_uploaded_file($_FILES['import']['tmp_name']) && isset($_GET['collection'])) {
			$data = json_decode(file_get_contents($_FILES['import']['tmp_name']));
			self::$model->import($_GET['collection'], $data, $_POST['importmethod']);
		}

		$action = (isset($_GET['action']) ? $_GET['action'] : 'listCollections');
		if (isset($_POST['object'])) {
			$object = $_POST['object'];
			if (self::$model->saveObject($_GET['collection'], $object)) {
				$action = 'editObject';
				$_GET['saved'] = true;
				//continue
			} else {
				$action = 'editObject';
				$_POST['errors']['object'] = 'Error: object could not be saved - check your array syntax.';
			}
		}

		switch ($action) {
			case 'createCollection' :
				self::$model->$action($_GET['collection']);
				load::redirect(get::url() . '?db=' . urlencode($_GET['db']));
				break;
			case 'renameCollection' :
				if (isset($_POST['collectionto']) && $_POST['collectionto'] != $_POST['collectionfrom']) {
					self::$model->$action($_POST['collectionfrom'], $_POST['collectionto']);
					$_GET['collection'] = $_POST['collectionto'];
					$action = 'listRows';
				}
				break;
			case 'editObject' :
				$this->mongo[$action] = (isset($_GET['_id']) ? self::$model->$action($_GET['collection'], $_GET['_id'], $_GET['idtype']) : '');
				$this->mongo['listCollections'] = self::$model->listCollections();
				return;
			case 'removeObject' :
				self::$model->$action($_GET['collection'], $_GET['_id'], $_GET['idtype']);
				return $this->_dumpFormVals();
			case 'ensureIndex' :
				foreach ($_GET['index'] as $key => $field) {
					$indexes[$field] = (isset($_GET['isdescending'][$key]) && $_GET['isdescending'][$key] ? -1 : 1);
				}
				self::$model->$action($_GET['collection'], $indexes, ($_GET['unique'] == 'Unique' ? array('unique' => true) : array()));
				$action = 'listCollections';
				break;
			case 'deleteIndex' :
				self::$model->$action($_GET['collection'], unserialize($_GET['index']));
				return $this->_dumpFormVals();
			case 'dropDb' :
				self::$model->$action();
				return $this->_dumpFormVals();
			case 'getStats' :
			default:
				if ($action !== 'listRows')
					$this->mongo['getStats'] = self::$model->getStats();
				if ($action === 'getStats')
					unset($this->mongo['listCollections']);
				break;
		}

		if (isset($_GET['sort'])) {
			self::$model->sort = array($_GET['sort'] => $_GET['sortdir']);
		}

		//adds new database or fetches collections if it exists.
		$this->mongo['listCollections'] = self::$model->listCollections();

		//refresh database list if new database is added.
		if (isset($_POST['newdb'])) {
			unset($_POST['newdb']);
			$this->mongo['dbs'] = self::$model->listDbs();
		}




		if (isset($_GET['collection']) && $action != 'listCollections' && method_exists(self::$model, $action)) {
			$this->mongo[$action] = self::$model->$action($_GET['collection']);
			$this->mongo['count'] = self::$model->count;
			$this->mongo['colKeys'] = self::$model->colKeys;
		}
		if ($action == 'listRows') {
			$this->mongo['listIndexes'] = self::$model->listIndexes($_GET['collection']);
		} else if ($action == 'dropCollection') {
			return load::redirect(get::url() . '?db=' . urlencode($_GET['db']));
		}
	}

}

/**
 * HTML helper tools
 */
class htmlHelper {

	/**
	 * Internal storage of the link-prefix and hypertext protocol values
	 * @var string
	 */
			protected $_linkPrefix, $_protocol;

	/**
	 * Internal list of included CSS & JS files used by $this->_tagBuilder() to assure that files are not included twice
	 * @var array
	 */
	protected $_includedFiles = array();

	/**
	 * Flag array to avoid defining singleton JavaScript & CSS snippets more than once
	 * @var array
	 */
			protected $_jsSingleton = array(), $_cssSingleton = array();

	/**
	 * Sets the protocol (http/https) - this is modified from the original Vork version for phpMoAdmin usage
	 */
	public function __construct() {
		$this->_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://');
	}

	/**
	 * Creates simple HTML wrappers, accessed via $this->__call()
	 *
	 * JS and CSS files are never included more than once even if requested twice. If DEBUG mode is enabled than the
	 * second request will be added to the debug log as a duplicate. The jsSingleton and cssSingleton methods operate
	 * the same as the js & css methods except that they will silently skip duplicate requests instead of logging them.
	 *
	 * jsInlineSingleton and cssInlineSingleton makes sure a JavaScript or CSS snippet will only be output once, even
	 * if echoed out multiple times and this method will attempt to place the JS code into the head section, if <head>
	 * has already been echoed out then it will return the JS code inline the same as jsInline. Eg.:
	 * $helloJs = "function helloWorld() {alert('Hello World');}";
	 * echo $html->jsInlineSingleton($helloJs);
	 *
	 * Adding an optional extra argument to jsInlineSingleton/cssInlineSingleton will return the inline code bare (plus
	 * a trailing linebreak) if it cannot place it into the head section, this is used for joint JS/CSS statements:
	 * echo $html->jsInline($html->jsInlineSingleton($helloJs, true) . 'helloWorld();');
	 *
	 * @param string $tagType
	 * @param array $args
	 * @return string
	 */
	protected function _tagBuilder($tagType, $args = array()) {
		$arg = current($args);
		if (empty($arg) || $arg === '') {
			$errorMsg = 'Missing argument for ' . __CLASS__ . '::' . $tagType . '()';
			trigger_error($errorMsg, E_USER_WARNING);
		}

		if (is_array($arg)) {
			foreach ($arg as $thisArg) {
				$return[] = $this->_tagBuilder($tagType, array($thisArg));
			}
			$return = implode(PHP_EOL, $return);
		} else {
			switch ($tagType) {
				case 'js':
				case 'jsSingleton':
				case 'css': //Optional extra argument to define CSS media type
				case 'cssSingleton':
				case 'jqueryTheme':
					if ($tagType == 'jqueryTheme') {
						$arg = $this->_protocol . 'ajax.googleapis.com/ajax/libs/jqueryui/1/themes/'
								. str_replace(' ', '-', strtolower($arg)) . '/jquery-ui.css';
						$tagType = 'css';
					}
					if (!isset($this->_includedFiles[$tagType][$arg])) {
						if ($tagType == 'css' || $tagType == 'cssSingleton') {
							$return = '<link rel="stylesheet" type="text/css" href="' . $arg . '"'
									. ' media="' . (isset($args[1]) ? $args[1] : 'all') . '" />';
						} else {
							$return = '<script type="text/javascript" src="' . $arg . '"></script>';
						}
						$this->_includedFiles[$tagType][$arg] = true;
					} else {
						$return = null;
						if (DEBUG_MODE && ($tagType == 'js' || $tagType == 'css')) {
							debug::log($arg . $tagType . ' file has already been included', 'warn');
						}
					}
					break;
				case 'cssInline': //Optional extra argument to define CSS media type
					$return = '<style type="text/css" media="' . (isset($args[1]) ? $args[1] : 'all') . '">'
							. PHP_EOL . '/*<![CDATA[*/'
							. PHP_EOL . '<!--'
							. PHP_EOL . $arg
							. PHP_EOL . '//-->'
							. PHP_EOL . '/*]]>*/'
							. PHP_EOL . '</style>';
					break;
				case 'jsInline':
					$return = '<script type="text/javascript">'
							. PHP_EOL . '//<![CDATA['
							. PHP_EOL . '<!--'
							. PHP_EOL . $arg
							. PHP_EOL . '//-->'
							. PHP_EOL . '//]]>'
							. PHP_EOL . '</script>';
					break;
				case 'jsInlineSingleton': //Optional extra argument to supress adding of inline JS/CSS wrapper
				case 'cssInlineSingleton':
					$tagTypeBase = substr($tagType, 0, -15);
					$return = null;
					$md5 = md5($arg);
					if (!isset($this->{'_' . $tagTypeBase . 'Singleton'}[$md5])) {
						$this->{'_' . $tagTypeBase . 'Singleton'}[$md5] = true;
						if (!$this->_bodyOpen) {
							$this->vorkHead[$tagTypeBase . 'Inline'][] = $arg;
						} else {
							$return = (!isset($args[1]) || !$args[1] ? $this->{$tagTypeBase . 'Inline'}($arg) : $arg . PHP_EOL);
						}
					}
					break;
				case 'div':
				case 'li':
				case 'p':
				case 'h1':
				case 'h2':
				case 'h3':
				case 'h4':
					$return = '<' . $tagType . '>' . $arg . '</' . $tagType . '>';
					break;
				default:
					$errorMsg = 'TagType ' . $tagType . ' not valid in ' . __CLASS__ . '::' . __METHOD__;
					throw new Exception($errorMsg);
					break;
			}
		}
		return $return;
	}

	/**
	 * Creates virtual wrapper methods via $this->_tagBuilder() for the simple wrapper functions including:
	 * $html->css, js, cssInline, jsInline, div, li, p and h1-h4
	 *
	 * @param string $method
	 * @param array $arg
	 * @return string
	 */
	public function __call($method, $args) {
		$validTags = array('css', 'js', 'cssSingleton', 'jsSingleton', 'jqueryTheme',
			'cssInline', 'jsInline', 'jsInlineSingleton', 'cssInlineSingleton',
			'div', 'li', 'p', 'h1', 'h2', 'h3', 'h4');
		if (in_array($method, $validTags)) {
			return $this->_tagBuilder($method, $args);
		} else {
			$errorMsg = 'Call to undefined method ' . __CLASS__ . '::' . $method . '()';
			trigger_error($errorMsg, E_USER_ERROR);
		}
	}

	/**
	 * Flag to make sure that header() can only be opened one-at-a-time and footer() can only be used after header()
	 * @var boolean
	 */
	private $_bodyOpen = false;

	/**
	 * Sets the default doctype to XHTML 1.1
	 * @var string
	 */
	protected $_docType = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';

	/**
	 * Allows modification of the docType
	 *
	 * Can either set to an actual doctype definition or to one of the presets (case-insensitive):
	 * XHTML Mobile 1.2
	 * XHTML Mobile 1.1
	 * XHTML Mobile 1.0
	 * Mobile 1.2 (alias for XHTML Mobile 1.2)
	 * Mobile 1.1 (alias for XHTML Mobile 1.1)
	 * Mobile 1.0 (alias for XHTML Mobile 1.0)
	 * Mobile (alias for the most-strict Mobile DTD, currently 1.2)
	 * XHTML 1.1 (this is the default DTD, there is no need to apply this method for an XHTML 1.1 doctype)
	 * XHTML (Alias for XHTML 1.1)
	 * XHTML 1.0 Strict
	 * XHTML 1.0 Transitional
	 * XHTML 1.0 Frameset
	 * XHTML 1.0 (Alias for XHTML 1.0 Strict)
	 * HTML 5
	 * HTML 4.01
	 * HTML (Alias for HTML 4.01)
	 *
	 * @param string $docType
	 */
	public function setDocType($docType) {
		$docType = str_replace(' ', '', strtolower($docType));
		if ($docType == 'xhtml1.1' || $docType == 'xhtml') {
			return; //XHTML 1.1 is the default
		} else if ($docType == 'xhtml1.0') {
			$docType = 'strict';
		}
		$docType = str_replace(array('xhtml mobile', 'xhtml1.0'), array('mobile', ''), $docType);
		$docTypes = array(
			'mobile1.2' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" '
			. '"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">',
			'mobile1.1' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.1//EN '
			. '"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile11.dtd">',
			'mobile1.0' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" '
			. '"http://www.wapforum.org/DTD/xhtml-mobile10.dtd">',
			'strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
			'transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
			'frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
			'html4.01' => '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" '
			. '"http://www.w3.org/TR/html4/strict.dtd">',
			'html5' => '<!DOCTYPE html>'
		);
		$docTypes['mobile'] = $docTypes['mobile1.2'];
		$docTypes['html'] = $docTypes['html4.01'];
		$this->_docType = (isset($docTypes[$docType]) ? $docTypes[$docType] : $docType);
	}

	/**
	 * Array used internally by Vork to cache JavaScript and CSS snippets and place them in the head section
	 * Changing the contents of this property may cause Vork components to be rendered incorrectly.
	 * @var array
	 */
	public $vorkHead = array();

	/**
	 * Returns an HTML header and opens the body container
	 * This method will trigger an error if executed more than once without first calling
	 * the footer() method on the prior usage
	 * This is meant to be utilized within layouts, not views (but will work in either)
	 *
	 * @param array $args
	 * @return string
	 */
	public function header(array $args) {
		if (!$this->_bodyOpen) {
			$this->_bodyOpen = true;
			extract($args);
			$return = $this->_docType
					. PHP_EOL . '<html xmlns="http://www.w3.org/1999/xhtml">'
					. PHP_EOL . '<head>'
					. PHP_EOL . '<title>' . $title . '</title>';

			if (!isset($metaheader['Content-Type'])) {
				$metaheader['Content-Type'] = 'text/html; charset=utf-8';
			}
			foreach ($metaheader as $name => $content) {
				$return .= PHP_EOL . '<meta http-equiv="' . $name . '" content="' . $content . '" />';
			}

//            $meta['generator'] = 'Forked Vork 2.00';
//            foreach ($meta as $name => $content) {
//                $return .= PHP_EOL . '<meta name="' . $name . '" content="' . $content . '" />';
//            }

			if (isset($favicon)) {
				$return .= PHP_EOL . '<link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon" />';
			}
			if (isset($animatedFavicon)) {
				$return .= PHP_EOL . '<link rel="icon" href="' . $animatedFavicon . '" type="image/gif" />';
			}

			$containers = array('css', 'cssInline', 'js', 'jsInline', 'jqueryTheme');
			foreach ($containers as $container) {
				if (isset($$container)) {
					$return .= PHP_EOL . $this->$container($$container);
				}
			}

			if (isset($head)) {
				$return .= PHP_EOL . (is_array($head) ? implode(PHP_EOL, $head) : $head);
			}

			$return .= PHP_EOL . '</head>' . PHP_EOL . '<body>';

			//Deprecation : GoChat tracking.
			//. $this->js('https://GoChat.us/chat.js#identity=5047dd509c3a8dd8fec07b5b&appid=phpmoadmin.com');

			return $return;
		} else {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - the header has already been returned';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Returns an HTML footer and optional Google Analytics
	 * This method will trigger an error if executed without first calling the header() method
	 * This is meant to be utilized within layouts, not views (but will work in either)
	 *
	 * @param array $args
	 * @return string
	 */
	public function footer(array $args = array()) {
		if ($this->_bodyOpen) {
			$this->_bodyOpen = false;
			return '</body></html>';
		} else {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - header() has not been called';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Establishes a basic set of JavaScript tools, just echo $html->jsTools() before any JavaScript code that
	 * will use the tools.
	 *
	 * This method will only operate from the first occurrence in your code, subsequent calls will not output anything
	 * but you should add it anyway as it will make sure that your code continues to work if you later remove a
	 * previous call to jsTools.
	 *
	 * Tools provided:
	 *
	 * dom() method is a direct replacement for document.getElementById() that works in all JS-capable
	 * browsers Y2k and newer.
	 *
	 * vork object - defines a global vork storage space; use by appending your own properties, eg.: vork.widgetCount
	 *
	 * @param Boolean $noJsWrapper set to True if calling from within a $html->jsInline() wrapper
	 * @return string
	 */
	public function jsTools($noJsWrapper = false) {
		return $this->jsInlineSingleton("var vork = function() {}
var dom = function(id) {
    if (typeof document.getElementById != 'undefined') {
        dom = function(id) {return document.getElementById(id);}
    } else if (typeof document.all != 'undefined') {
        dom = function(id) {return document.all[id];}
    } else {
        return false;
    }
    return dom(id);
}", $noJsWrapper);
	}

	/**
	 * Load a JavaScript library via Google's AJAX API
	 * http://code.google.com/apis/ajaxlibs/documentation/
	 *
	 * Version is optional and can be exact (1.8.2) or just version-major (1 or 1.8)
	 *
	 * Usage:
	 * echo $html->jsLoad('jquery');
	 * echo $html->jsLoad(array('yui', 'mootools'));
	 * echo $html->jsLoad(array('yui' => 2.7, 'jquery', 'dojo' => '1.3.1', 'scriptaculous'));
	 *
	 * //You can also use the Google API format JSON-decoded in which case version is required & name must be lowercase
	 * $jsLibs = array(array('name' => 'mootools', 'version' => 1.2, 'base_domain' => 'ditu.google.cn'), array(...));
	 * echo $html->jsLoad($jsLibs);
	 *
	 * @param mixed $library Can be a string, array(str1, str2...) or , array(name1 => version1, name2 => version2...)
	 *                       or JSON-decoded Google API syntax array(array('name' => 'yui', 'version' => 2), array(...))
	 * @param mixed $version Optional, int or str, this is only used if $library is a string
	 * @param array $options Optional, passed to Google "optionalSettings" argument, only used if $library == str
	 * @return str
	 */
	public function jsLoad($library, $version = null, array $options = array()) {
		$versionDefaults = array('swfobject' => 2, 'yui' => 2, 'ext-core' => 3, 'mootools' => 1.2);
		if (!is_array($library)) { //jsLoad('yui')
			$library = strtolower($library);
			if (!$version) {
				$version = (!isset($versionDefaults[$library]) ? 1 : $versionDefaults[$library]);
			}
			$library = array('name' => $library, 'version' => $version);
			$library = array(!$options ? $library : array_merge($library, $options));
		} else {
			foreach ($library as $key => $val) {
				if (!is_array($val)) {
					if (is_int($key)) { //jsLoad(array('yui', 'prototype'))
						$val = strtolower($val);
						$version = (!isset($versionDefaults[$val]) ? 1 : $versionDefaults[$val]);
						$library[$key] = array('name' => $val, 'version' => $version);
					} else if (!is_array($val)) { // //jsLoad(array('yui' => '2.8.0r4', 'prototype' => 1.6))
						$library[$key] = array('name' => strtolower($key), 'version' => $val);
					}
				}
			}
		}
		$url = $this->_protocol . 'www.google.com/jsapi';
		if (!isset($this->_includedFiles['js'][$url])) { //autoload library
			$this->_includedFiles['js'][$url] = true;
			$url .= '?autoload=' . urlencode(json_encode(array('modules' => array_values($library))));
			$return = $this->js($url);
		} else { //load inline
			foreach ($library as $lib) {
				$js = 'google.load("' . $lib['name'] . '", "' . $lib['version'] . '"';
				if (count($lib) > 2) {
					unset($lib['name'], $lib['version']);
					$js .= ', ' . json_encode($lib);
				}
				$jsLoads[] = $js . ');';
			}
			$return = $this->jsInline(implode(PHP_EOL, $jsLoads));
		}
		return $return;
	}

	/**
	 * Takes an array of key-value pairs and formats them in the syntax of HTML-container properties
	 *
	 * @param array $properties
	 * @return string
	 */
	public static function formatProperties(array $properties) {
		$return = array();
		foreach ($properties as $name => $value) {
			$return[] = $name . '="' . get::htmlentities($value) . '"';
		}
		return implode(' ', $return);
	}

	/**
	 * Creates an anchor or link container
	 *
	 * @param array $args
	 * @return string
	 */
	public function anchor(array $args) {
		if (!isset($args['text']) && isset($args['href'])) {
			$args['text'] = $args['href'];
		}
		if (!isset($args['title']) && isset($args['text'])) {
			$args['title'] = str_replace(array("\n", "\r"), ' ', strip_tags($args['text']));
		}
		$return = '';
		if (isset($args['ajaxload'])) {
			$return = $this->jsSingleton('/js/ajax.js');
			$onclick = "return ajax.load('" . $args['ajaxload'] . "', this.href);";
			$args['onclick'] = (!isset($args['onclick']) ? $onclick : $args['onclick'] . '; ' . $onclick);
			unset($args['ajaxload']);
		}
		$text = (isset($args['text']) ? $args['text'] : null);
		unset($args['text']);
		return $return . '<a ' . self::formatProperties($args) . '>' . $text . '</a>';
	}

	/**
	 * Shortcut to access the anchor method
	 *
	 * @param str $href
	 * @param str $text
	 * @param array $args
	 * @return str
	 */
	public function link($href, $text = null, array $args = array()) {
		if ($href && strpos($href, 'http') !== 0) {
			$href = $this->_linkPrefix . $href;
		}
		if ($href !== null)
			$args['href'] = $href;
		if ($text !== null) {
			$args['text'] = $text;
		}
		return $this->anchor($args);
	}

	/**
	 * Wrapper display computer-code samples
	 *
	 * @param str $str
	 * @return str
	 */
	public function code($str) {
		return '<code>' . str_replace('  ', '&nbsp;&nbsp;', nl2br(get::htmlentities($str))) . '</code>';
	}

	/**
	 * Will return true if the number passed in is even, false if odd.
	 *
	 * @param int $number
	 * @return boolean
	 */
	public function isEven($number) {
		return (Boolean) ($number % 2 == 0);
	}

	/**
	 * Will return number friendly format.
	 * by james at bandit.co.nz
	 * @param int $n
	 * @return string
	 */
	public function bd_nice_number($n) {
		// first strip any formatting;
		$n = (0 + str_replace(",", "", $n));

		// is this a number?
		if (!is_numeric($n))
			return false;

		// now filter it;
		if ($n > 1000000000000)
			return round(($n / 1000000000000), 2) . 't';
		else if ($n > 1000000000)
			return round(($n / 1000000000), 2) . 'b';
		else if ($n > 1000000)
			return round(($n / 1000000), 2) . 'm';
		else if ($n > 1000)
			return round(($n / 1000), 2) . 'k';

		return number_format($n);
	}

	/**
	 * Internal incrementing integar for the alternator() method
	 * @var int
	 */
	private $alternator = 1;

	/**
	 * Returns an alternating Boolean, useful to generate alternating background colors
	 * Eg.:
	 * $colors = array(true => 'gray', false => 'white');
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //gray background
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //white background
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //gray background
	 *
	 * @return Boolean
	 */
	public function alternator() {
		return $this->isEven(++$this->alternator);
	}

	/**
	 * Returns a list of notifications if there are any - similar to the Flash feature of Ruby on Rails
	 *
	 * @param mixed $messages String or an array of strings
	 * @param string $class
	 * @return string Returns null if there are no notifications to return
	 */
	public function getNotifications($messages, $class = 'errormessage') {
		if (isset($messages) && $messages) {
			return '<div class="' . $class . '">'
					. (is_array($messages) ? implode('<br />', $messages) : $messages) . '</div>';
		}
	}

}

/**
 * Vork form-helper
 */
class formHelper {

	/**
	 * Internal flag to keep track if a form tag has been opened and not yet closed
	 * @var boolean
	 */
	private $_formOpen = false;

	/**
	 * Internal form element counter
	 * @var int
	 */
	private $_inputCounter = array();

	/**
	 * Converts dynamically-assigned array indecies to use an explicitely defined index
	 *
	 * @param string $name
	 * @return string
	 */
	protected function _indexDynamicArray($name) {
		$dynamicArrayStart = strpos($name, '[]');
		if ($dynamicArrayStart) {
			$prefix = substr($name, 0, $dynamicArrayStart);
			if (!isset($this->_inputCounter[$prefix])) {
				$this->_inputCounter[$prefix] = -1;
			}
			$name = $prefix . '[' . ++$this->_inputCounter[$prefix] . substr($name, ($dynamicArrayStart + 1));
		}
		return $name;
	}

	/**
	 * Form types that do not change value with user input
	 * @var array
	 */
	protected $_staticTypes = array('hidden', 'submit', 'button', 'image');

	/**
	 * Sets the standard properties available to all input elements in addition to user-defined properties
	 * Standard properties are: name, value, class, style, id
	 *
	 * @param array $args
	 * @param array $propertyNames Optional, an array of user-defined properties
	 * @return array
	 */
	protected function _getProperties(array $args, array $propertyNames = array()) {
		$method = (isset($this->_formOpen['method']) && $this->_formOpen['method'] == 'get' ? $_GET : $_POST);
		if (isset($args['name']) && (!isset($args['type']) || !in_array($args['type'], $this->_staticTypes))) {
			$arrayStart = strpos($args['name'], '[');
			if (!$arrayStart) {
				if (isset($method[$args['name']])) {
					$args['value'] = $method[$args['name']];
				}
			} else {
				$name = $this->_indexDynamicArray($args['name']);
				if (preg_match_all('/\[(.*)\]/', $name, $arrayIndex)) {
					array_shift($arrayIndex); //dump the 0 index element containing full match string
				}
				$name = substr($name, 0, $arrayStart);
				if (isset($method[$name])) {
					$args['value'] = $method[$name];
					if (!isset($args['type']) || $args['type'] != 'checkbox') {
						foreach ($arrayIndex as $idx) {
							if (isset($args['value'][current($idx)])) {
								$args['value'] = $args['value'][current($idx)];
							} else {
								unset($args['value']);
								break;
							}
						}
					}
				}
			}
		}
		$return = array();
		$validProperties = array_merge($propertyNames, array('name', 'value', 'class', 'style', 'id'));
		foreach ($validProperties as $propertyName) {
			if (isset($args[$propertyName])) {
				$return[$propertyName] = $args[$propertyName];
			}
		}
		return $return;
	}

	/**
	 * Begins a form
	 * Includes a safety mechanism to prevent re-opening an already-open form
	 *
	 * @param array $args
	 * @return string
	 */
	public function open(array $args = array()) {
		if (!$this->_formOpen) {
			if (!isset($args['method'])) {
				$args['method'] = 'post';
			}

			$this->_formOpen = array('id' => (isset($args['id']) ? $args['id'] : true),
				'method' => $args['method']);

			if (!isset($args['action'])) {
				$args['action'] = $_SERVER['REQUEST_URI'];
			}
			if (isset($args['upload']) && $args['upload'] && !isset($args['enctype'])) {
				$args['enctype'] = 'multipart/form-data';
			}
			if (isset($args['legend'])) {
				$legend = $args['legend'];
				unset($args['legend']);
				if (!isset($args['title'])) {
					$args['title'] = $legend;
				}
			} else if (isset($args['title'])) {
				$legend = $args['title'];
			}
			if (isset($args['alert'])) {
				if ($args['alert']) {
					$alert = (is_array($args['alert']) ? implode('<br />', $args['alert']) : $args['alert']);
				}
				unset($args['alert']);
			}
			$return = '<form ' . htmlHelper::formatProperties($args) . '>' . PHP_EOL . '<fieldset>' . PHP_EOL;
			if (isset($legend)) {
				$return .= '<legend>' . $legend . '</legend>' . PHP_EOL;
			}
			if (isset($alert)) {
				$return .= $this->getErrorMessageContainer((isset($args['id']) ? $args['id'] : 'form'), $alert);
			}
			return $return;
		} else if (DEBUG_MODE) {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - a form is already open';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Closes a form if one is open
	 *
	 * @return string
	 */
	public function close() {
		if ($this->_formOpen) {
			$this->_formOpen = false;
			return '</fieldset></form>';
		} else if (DEBUG_MODE) {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - there is no open form to close';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Adds label tags to a form element
	 *
	 * @param array $args
	 * @param str $formElement
	 * @return str
	 */
	protected function _getLabel(array $args, $formElement) {
		if (!isset($args['label']) && isset($args['name'])
				&& (!isset($args['type']) || !in_array($args['type'], $this->_staticTypes))) {
			$args['label'] = ucfirst($args['name']);
		}

		if (isset($args['label'])) {
			$label = get::xhtmlentities($args['label']);
			if (isset($_POST['errors']) && isset($args['name']) && isset($_POST['errors'][$args['name']])) {
				$label .= ' ' . $this->getErrorMessageContainer($args['name'], $_POST['errors'][$args['name']]);
			}
			$labelFirst = (!isset($args['labelFirst']) || $args['labelFirst']);
			if (isset($args['id'])) {
				$label = '<label for="' . $args['id'] . '" id="' . $args['id'] . 'label">'
						. $label . '</label>';
			}
			if (isset($args['addBreak']) && $args['addBreak']) {
				$label = ($labelFirst ? $label . '<br />' : '<br />' . $label);
			}
			$formElement = ($labelFirst ? $label . $formElement : $formElement . $label);
			if (!isset($args['id'])) {
				$formElement = '<label>' . $formElement . '</label>';
			}
		}
		return $formElement;
	}

	/**
	 * Returns a standardized container to wrap an error message
	 *
	 * @param string $id
	 * @param string $errorMessage Optional, you may want to leave this blank and populate dynamically via JavaScript
	 * @return string
	 */
	public function getErrorMessageContainer($id, $errorMessage = '') {
		return '<span class="errormessage" id="' . $id . 'errorwrapper">'
				. get::htmlentities($errorMessage) . '</span>';
	}

	/**
	 * Used for text, textarea, hidden, password, file, button, image and submit
	 *
	 * Valid args are any properties valid within an HTML input as well as label
	 *
	 * @param array $args
	 * @return string
	 */
	public function input(array $args) {
		$args['type'] = (isset($args['type']) ? $args['type'] : 'text');

		switch ($args['type']) {
			case 'select':
				return $this->select($args);
				break;
			case 'checkbox':
				return $this->checkboxes($args);
				break;
			case 'radio':
				return $this->radios($args);
				break;
		}

		if ($args['type'] == 'textarea' && isset($args['maxlength'])) {
			if (!isset($args['id']) && isset($args['name'])) {
				$args['id'] = $args['name'];
			}
			if (isset($args['id'])) {
				$maxlength = $args['maxlength'];
			}
			unset($args['maxlength']);
		}

		if ($args['type'] == 'submit' && !isset($args['class'])) {
			$args['class'] = $args['type'];
		}

		$takeFocus = (isset($args['focus']) && $args['focus'] && $args['type'] != 'hidden');
		if ($takeFocus && !isset($args['id'])) {
			if (isset($args['name'])) {
				$args['id'] = $args['name'];
			} else {
				$takeFocus = false;
				if (DEBUG_MODE) {
					$errorMsg = 'Either name or id is required to use the focus option on a form input';
					trigger_error($errorMsg, E_USER_NOTICE);
				}
			}
		}

		$properties = $this->_getProperties($args, array('type', 'maxlength'));

		if ($args['type'] == 'image') {
			$properties['src'] = $args['src'];
			$properties['alt'] = (isset($args['alt']) ? $args['alt'] : '');
			$optionalProperties = array('height', 'width');
			foreach ($optionalProperties as $optionalProperty) {
				if (isset($args[$optionalProperty])) {
					$properties[$optionalProperty] = $args[$optionalProperty];
				}
			}
		}

		if ($args['type'] != 'textarea') {
			$return[] = '<input ' . htmlHelper::formatProperties($properties) . ' />';
		} else {
			unset($properties['type']);
			if (isset($properties['value'])) {
				$value = $properties['value'];
				unset($properties['value']);
			}
			if (isset($args['preview']) && $args['preview'] && !isset($properties['id'])) {
				$properties['id'] = 'textarea_' . rand(100, 999);
			}
			$properties['rows'] = (isset($args['rows']) ? $args['rows'] : 13);
			$properties['cols'] = (isset($args['cols']) ? $args['cols'] : 55);
			$return[] = '<textarea ' . htmlHelper::formatProperties($properties);
			if (isset($maxlength)) {
				$return[] = ' onkeyup="document.getElementById(\''
						. $properties['id'] . 'errorwrapper\').innerHTML = (this.value.length > '
						. $maxlength . ' ? \'Form content exceeds maximum length of '
						. $maxlength . ' characters\' : \'Length: \' + this.value.length + \' (maximum: '
						. $maxlength . ' characters)\')"';
			}
			$return[] = '>';
			if (isset($value)) {
				$return[] = get::htmlentities($value, null, null, true); //double-encode allowed
			}
			$return[] = '</textarea>';
			if (isset($maxlength) && (!isset($args['validatedInput']) || !$args['validatedInput'])) {
				$return[] = $this->getErrorMessageContainer($properties['id']);
			}
		}
		if (!isset($args['addBreak'])) {
			$args['addBreak'] = true;
		}
		if ($takeFocus) {
			$html = get::helper('html');
			$return[] = $html->jsInline($html->jsTools(true) . 'dom("' . $args['id'] . '").focus();');
		}
		if (isset($args['preview']) && $args['preview']) {
			$js = 'document.writeln(\'<div class="htmlpreviewlabel">'
					. '<label for="livepreview_' . $properties['id'] . '">Preview:</label></div>'
					. '<div id="livepreview_' . $properties['id'] . '" class="htmlpreview"></div>\');' . PHP_EOL
					. 'if (dom("livepreview_' . $properties['id'] . '")) {' . PHP_EOL
					. '    var updateLivePreview_' . $properties['id'] . ' = '
					. 'function() {dom("livepreview_' . $properties['id'] . '").innerHTML = '
					. 'dom("' . $properties['id'] . '").value};' . PHP_EOL
					. '    dom("' . $properties['id'] . '").onkeyup = updateLivePreview_' . $properties['id'] . ';'
					. ' updateLivePreview_' . $properties['id'] . '();' . PHP_EOL
					. '}';
			if (!isset($html)) {
				$html = get::helper('html');
			}
			$return[] = $html->jsInline($html->jsTools(true) . $js);
		}
		return $this->_getLabel($args, implode($return));
	}

	/**
	 * Returns a form select element
	 *
	 * $args = array(
	 * 'name' => '',
	 * 'multiple' => true,
	 * 'leadingOptions' => array(),
	 * 'optgroups' => array('group 1' => array('label' => 'g1o1', 'value' => 'grp 1 opt 1'),
	 *                      'group 2' => array(),),
	 * 'options' => array('value1' => 'text1', 'value2' => 'text2', 'value3' => 'text3'),
	 * 'value' => array('value2', 'value3') //if (multiple==false) 'value' => (str) 'value3'
	 * );
	 *
	 * @param array $args
	 * @return str
	 */
	public function select(array $args) {
		if (!isset($args['id'])) {
			$args['id'] = $args['name'];
		}
		if (isset($args['multiple']) && $args['multiple']) {
			$args['multiple'] = 'multiple';
			if (substr($args['name'], -2) != '[]') {
				$args['name'] .= '[]';
			}
		}
		$properties = $this->_getProperties($args, array('multiple'));
		$values = (isset($properties['value']) ? $properties['value'] : null);
		unset($properties['value']);
		if (!is_array($values)) {
			$values = ($values != '' ? array($values) : array());
		}
		$return = '<select ' . htmlHelper::formatProperties($properties) . '>';
		if (isset($args['prependBlank']) && $args['prependBlank']) {
			$return .= '<option value=""></option>';
		}

		if (isset($args['leadingOptions'])) {
			$useValues = (key($args['leadingOptions']) !== 0
					|| (isset($args['useValue']) && $args['useValue']));
			foreach ($args['leadingOptions'] as $value => $text) {
				if (!$useValues) {
					$value = $text;
				}
				$return .= '<option value="' . get::htmlentities($value) . '"';
				if (in_array((string) $value, $values)) {
					$return .= ' selected="selected"';
				}
				$return .= '>' . get::htmlentities($text) . '</option>';
			}
		}

		if (isset($args['optgroups'])) {
			foreach ($args['optgroups'] as $groupLabel => $optgroup) {
				$return .= '<optgroup label="' . get::htmlentities($groupLabel) . '">';
				foreach ($optgroup as $value => $label) {
					$return .= '<option value="' . get::htmlentities($value) . '"';
					if (isset($label)) {
						$return .= ' label="' . get::htmlentities($label) . '"';
					}
					if (in_array((string) $value, $values)) {
						$return .= ' selected="selected"';
					}
					$return .= '>' . get::htmlentities($label) . '</option>';
				}
				$return .= '</optgroup>';
			}
		}

		if (isset($args['options'])) {
			$useValues = (key($args['options']) !== 0 || (isset($args['useValue']) && $args['useValue']));
			foreach ($args['options'] as $value => $text) {
				if (!$useValues) {
					$value = $text;
				}
				$return .= '<option value="' . get::htmlentities($value) . '"';
				if (in_array((string) $value, $values)) {
					$return .= ' selected="selected"';
				}
				$return .= '>' . get::htmlentities($text) . '</option>';
			}
		}
		$return .= '</select>';
		if (!isset($args['addBreak'])) {
			$args['addBreak'] = true;
		}
		$return = $this->_getLabel($args, $return);
		if (isset($args['error'])) {
			$return .= $this->getErrorMessageContainer($args['id'], '<br />' . $args['error']);
		}
		return $return;
	}

	/**
	 * Cache containing individual radio or checkbox elements in an array
	 * @var array
	 */
			public $radios = array(), $checkboxes = array();

	/**
	 * Returns a set of radio form elements
	 *
	 * array(
	 * 'name' => '',
	 * 'value' => '',
	 * 'id' => '',
	 * 'legend' => '',
	 * 'options' => array('value1' => 'text1', 'value2' => 'text2', 'value3' => 'text3'),
	 * 'options' => array('text1', 'text2', 'text3'), //also acceptable (cannot do half this, half above syntax)
	 * )
	 *
	 * @param array $args
	 * @return str
	 */
	public function radios(array $args) {
		$id = (isset($args['id']) ? $args['id'] : $args['name']);
		$properties = $this->_getProperties($args);
		if (isset($properties['value'])) {
			$checked = $properties['value'];
			unset($properties['value']);
		}
		$properties['type'] = (isset($args['type']) ? $args['type'] : 'radio');
		$useValues = (key($args['options']) !== 0 || (isset($args['useValue']) && $args['useValue']));
		foreach ($args['options'] as $value => $text) {
			if (!$useValues) {
				$value = $text;
			}
			$properties['id'] = $id . '_' . preg_replace('/\W/', '', $value);
			$properties['value'] = $value;
			if (isset($checked) &&
					((($properties['type'] == 'radio' || !is_array($checked)) && $value == $checked)
					|| ($properties['type'] == 'checkbox' && is_array($checked) && in_array((string) $value, $checked)))) {
				$properties['checked'] = 'checked';
				$rowClass = (!isset($properties['class']) ? 'checked' : $properties['class'] . ' checked');
			}
			$labelFirst = (isset($args['labelFirst']) ? $args['labelFirst'] : false);
			$labelArgs = array('label' => $text, 'id' => $properties['id'], 'labelFirst' => $labelFirst);
			$input = '<input ' . htmlHelper::formatProperties($properties) . ' />';
			$row = $this->_getLabel($labelArgs, $input);
			if (isset($rowClass)) {
				$row = '<span class="' . $rowClass . '">' . $row . '</span>';
			}
			$radios[] = $row;
			unset($properties['checked'], $rowClass);
		}
		$this->{$properties['type'] == 'radio' ? 'radios' : 'checkboxes'} = $radios;
		$break = (!isset($args['optionBreak']) ? '<br />' : $args['optionBreak']);
		$addFieldset = (isset($args['addFieldset']) ? $args['addFieldset'] : ((isset($args['label']) && $args['label']) || count($args['options']) > 1));
		if ($addFieldset) {
			$return = '<fieldset id="' . $id . '">';
			if (isset($args['label'])) {
				$return .= '<legend>' . get::htmlentities($args['label']) . '</legend>';
			}
			$return .= implode($break, $radios) . '</fieldset>';
		} else {
			$return = implode($break, $radios);
		}
		if (isset($_POST['errors']) && isset($_POST['errors'][$id])) {
			$return = $this->getErrorMessageContainer($id, $_POST['errors'][$id]) . $return;
		}
		return $return;
	}

	/**
	 * Returns a set of checkbox form elements
	 *
	 * This method essentially extends the radios method and uses an identical signature except
	 * that $args['value'] can also accept an array of values to be checked.
	 *
	 * @param array $args
	 * @return str
	 */
	public function checkboxes(array $args) {
		$args['type'] = 'checkbox';
		if (isset($args['value']) && !is_array($args['value'])) {
			$args['value'] = array($args['value']);
		}
		$nameParts = explode('[', $args['name']);
		if (!isset($args['id'])) {
			$args['id'] = $nameParts[0];
		}
		if (!isset($nameParts[1]) && count($args['options']) > 1) {
			$args['name'] .= '[]';
		}
		return $this->radios($args);
	}

	/**
	 * Opens up shorthand usage of form elements like $form->file() and $form->submit()
	 *
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	public function __call($name, array $args) {
		$inputShorthand = array('text', 'textarea', 'password', 'file', 'hidden', 'submit', 'button', 'image');
		if (in_array($name, $inputShorthand)) {
			$args[0]['type'] = $name;
			return $this->input($args[0]);
		}
		trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
	}

}

class jsonHelper {

	/**
	 * Outputs content in JSON format
	 * @param mixed $content Can be a JSON string or an array of any data that will automatically be converted to JSON
	 * @param string $filename Default filename within the user-prompt for saving the JSON file
	 */
	public function echoJson($content, $filename = null) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 01 Jan 2000 01:00:00 GMT');
		header('Content-type: application/json');
		if ($filename) {
			header('Content-Disposition: attachment; filename=' . $filename);
		}
		echo (!is_array($content) && !is_object($content) ? $content : json_encode($content));
	}

}

/**
 * phpMoAdmin specific functionality
 */
class phpMoAdmin {
	/**
	 * Sets the depth limit for phpMoAdmin::getArrayKeys (and prevents an endless loop with self-referencing objects)
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

/**
 * phpMoAdmin bootstrap
 */
session_start();
if (get_magic_quotes_gpc()) {
	$_GET = phpMoAdmin::stripslashes($_GET);
	$_POST = phpMoAdmin::stripslashes($_POST);
}
$hasDB = (isset($_GET['db']) ? true : (isset($_POST['db']) ? true : false)); //$_GET['db'] will default to admin
$hasCollection = (isset($_GET['collection']) ? true : false);

if (!isset($_GET['db']) && !isset($_POST['newdb'])) {
	$_GET['db'] = moadminModel::$dbName;
} else if (!isset($_GET['db']) && isset($_POST['newdb'])) {
	$_GET['db'] = $_POST['newdb'];
}
try {
	moadminComponent::$model = new moadminModel($_GET['db']);
} catch (Exception $e) {
	echo $e;
	exit(0);
}
$html = get::helper('html');
$ver = explode('.', phpversion());
get::$isPhp523orNewer = ($ver[0] >= 5 && ($ver[1] > 2 || ($ver[1] == 2 && $ver[2] >= 3)));
$form = new formHelper;
$mo = new moadminComponent;

if (isset($_GET['export']) && isset($mo->mongo['listRows'])) {
	$rows = array();
	foreach ($mo->mongo['listRows'] as $row) {
		$rows[] = serialize($row);
	}
	$filename = get::htmlentities($_GET['db']);
	if (isset($_GET['collection'])) {
		$filename .= '~' . get::htmlentities($_GET['collection']);
	}
	$filename .= '.json';
	get::helper('json')->echoJson($rows, $filename);
	exit(0);
}

/**
 * phpMoAdmin front-end view-element
 */
$headerArgs = [
	'title' => (isset($_GET['action']) ? 'Moa[db] - ' . get::htmlentities($_GET['action']) : 'Moa[db]'),
	'css' => [
		'//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css',
		'http://fonts.googleapis.com/css?family=Exo:900',
		'//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.min.css'
	],
	'js' => [
		'//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js',
	],
	'cssInline' => '
/* reset */
/* Space out content a bit */
body {
  padding-top: 20px;
  padding-bottom: 20px;
}

/* Everything but the jumbotron gets side spacing for mobile first views */
.header,
.marketing,
.footer {
  padding-left: 15px;
  padding-right: 15px;
}

/* Custom page header */
.header {
  border-bottom: 1px solid #e5e5e5;
}
/* Make the masthead heading the same height as the navigation */
.header h3 {
  margin-top: 0;
  margin-bottom: 0;
  line-height: 40px;
  padding-bottom: 19px;
}
.header h3.text-muted,h3.text-muted {
  font-family: "Exo", sans-serif;
  font-weight: 900;
  color:black;
  font-size: 34px;
 }

/* Custom page footer */
.footer {
  padding-top: 19px;
  color: #777;
  border-top: 1px solid #e5e5e5;
}

/* Customize container */
@media (min-width: 768px) {
  .container {
    max-width: 720px;
  }
}
.container-narrow > hr {
  margin: 30px 0;
}

/* Main marketing message and sign up button */
.jumbotron {
  text-align: center;
  border-bottom: 1px solid #e5e5e5;
}

/* Supporting marketing content */
.marketing {
  margin: 40px 0;
  word-break: break-all;
}
.marketing .col-lg-2, .marketing .col-lg-3{
	padding-top:5px;
	border-radius: 5px;
	-moz-border-radius: 5px;
}
.marketing .col-lg-3.database.click:hover{
	background:#ddd;
	cursor:pointer;
}
.marketing p {
  font-size: 12px;
}
.marketing img {
  width: 50%;
}
.marketing p + h4 {
  margin-top: 28px;
}

.database a.close {
  background: #F00;
  border-radius: 16px;
  padding: 0px 5px 3px 6px;
  text-shadow: none;
  color: #FFF;
  position: absolute;
  margin-left: 27px;
  margin-top: -16px;
}
.nav>li>a.open, .nav>li>a.active{
  text-decoration: none;
  background-color: #EEE;
}
#mongo_collections { 
float:left;
width: 36%;
border: 1px solid #ddd;
font-size: 15px;
overflow-y: scroll;
height: 462px;
border-radius: 5px 0 0 5px;
-moz-border-radius: 5px 0 0 5px;
padding: 10px;
background: #FFF;
position: relative;
top: -48px;
left: -59px;
}
.jumbotron { max-height: 462px; }
#mongo_collections table { font-size: 15px;}
#mongo_collections table tr td {line-height: 24px;}
#mongo_collections table tr td icon {top: 6px;
position: relative;}
#mongo_collections table tr td a:not(.close) { text-overflow: ellipsis;
width: 140px;
white-space: nowrap;
display: block;
overflow: hidden;}

#main-content { 
width: 64%;
position: relative;
float: right;
font-size: 15px;
margin-top:-25px;
}
#main-content .content-scroll{ 
overflow-y: scroll;
height: 360px;
padding-top: 20px;
}
#main-content .content-scroll.full{ height:410px;}
#main-content table.table-hover td {
line-height: 2;
}
#main-content table.table-hover td:last-child {
padding-left: 30px;
}
#main-content table.table-hover td:nth-child(-n+3){
width:30px;
}
#main-content table.table-hover tr:nth-child(4n+1){ background:#E9E9E9;}
#main-content table{font-size: 13px;}
#main-content table.stats { color: #bbb;
opacity:0.6;-moz-opacity:0.6;
transition: opacity 1s, color 1s;
-webkit-transition: opacity 1s, color 1s;}
#main-content table.stats:hover { color: #444; opacity:1;-moz-opacity:1;}
#main-content table.table-hover tr:nth-child(2n+1) [class^="icon-"],
#main-content table.table-hover tr:nth-child(2n+1) [class*=" icon-"] {
font-size:15px;
position: relative;
top: 6px;
}
#main-content textarea.input-lg {
height:345px;
}
#main-content .alert.stats{
padding:0; margin-bottom:10px;
}
#main-content .opacity{
opacity:0.4;-moz-opacity:0.4;
}
.aotoggle { cursor:pointer;}
.noMargin { margin: 0;}
.nav>li>a.divider:hover, .nav>li>a.divider:focus {
background-color: transparent;
}

body.modal-open, .modal-open .navbar-fixed-top, .modal-open .navbar-fixed-bottom {
margin-right: 0px;
}
#modal h4, #modal .input-lg {font-size:16px}
.hidden {display:none;}

#mongo_rows .btn-default {
font-weight: bold;}
#mongo_rows .running {border-color: #FFA500;
background: #FDF9F2;}
/* Responsive: Portrait tablets and up */
@media screen and (min-width: 768px) {
  /* Remove the padding we set earlier */
  .header,
  .marketing,
  .footer {
    padding-left: 0;
    padding-right: 0;
  }
  /* Space out the masthead */
  .header {
    margin-bottom: 30px;
  }
  /* Remove the bottom border on the jumbotron for visual effect */
  .jumbotron {
    border-bottom: 0;
  }
}
.container {
max-width: 920px;
}
ul, li{ list-style-type: none;}
#modal .btn-block{text-align: left;
text-indent: 15px;font-size: 15px;}
.help-inline {
display: inline;
margin-top: 5px;
margin-bottom: 10px;
color: #737373;
}
a { cursor:pointer;}

'];



echo $html->jsLoad(array('jquery', 'jqueryui'));
echo $html->header($headerArgs);
$baseUrl = $_SERVER['SCRIPT_NAME'];
$db = (isset($_GET['db']) ? $_GET['db'] : (isset($_POST['db']) ? $_POST['db'] : 'admin')); //admin is in every Mongo DB
$dbUrl = urlencode($db);

if (isset($_GET['collection'])) {
	$collection = get::htmlentities($_GET['collection']);
	unset($_GET['collection']);
}
if (isset($accessControl) && !isset($_SESSION['user'])) {
	if (isset($_POST['username'])) {
		$_POST = array_map('trim', $_POST);
		if (isset($accessControl[$_POST['username']]) && $accessControl[$_POST['username']] == $_POST['password']) {
			$_SESSION['user'] = $_POST['username'];
		} else {
			$_POST['errors']['username'] = 'Incorrect username or password';
		}
	}
	if (!isset($_SESSION['user'])) {
	?>
	<div class="col-md-offset-4">
		<form role="form" class="col-md-5">
			<h3 class="text-muted">moa[db]</h3>
			<?=isset($_POST['errors']) ? $_POST['errors']['username']: ''?>
			<div class="form-group">
				<label for="exampleInputEmail1">Username</label>
				<input type="text" class="form-control" id="exampleInputEmail1" name="username" placeholder="Enter Username">
			</div>
			<div class="form-group">
				<label for="exampleInputPassword1">Password</label>
				<input type="password" class="form-control" id="exampleInputPassword1" name="password" placeholder="Password">
			</div>
			<button type="submit" class="btn btn-default">Submit</button>
		</form>
	</div>
		<?php
		exit;
	}
}
?>
<script>
	// backward compat. 
	var mo = {};
</script>
<div class="container">
	<div class="header">
        <ul class="nav nav-pills pull-right">
			<?php if ($hasCollection) : ?>
				<li class="active"><a data-view="CollectionRow" href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>">&nbsp;<icon class="icon-list">&nbsp;</icon></a></li>
			<? endif; ?>
			<?php if ($hasDB) : ?>
				<li <?= $hasCollection ? '' : 'class="active"' ?>><a data-view="Collections" href="<?= $baseUrl ?>?db=<?= $db ?>"><icon class="icon-inbox"></icon> Collections</a></li>
			<?php endif; ?>
			<li <?= $hasDB ? '' : 'class="active"' ?>><a data-view="Databases" href="<?= $baseUrl ?>"><icon class="icon-hdd"></icon> Databases</a></li>
			<li><a class="divider">|</a></li>
			<li><a id="edit-button"><icon class="icon-pencil"></icon> Edit</a></li>
			<li><a data-popup data-title="<?= $hasDB ? ($hasCollection ? 'New Object' : 'New Collection') : 'New Database"' ?>" data-body="<?= $hasDB ? ($hasCollection ? '#new-object' : '#new-collection') : '#new-database"' ?>"><icon class="icon-plus-sign-alt"></icon> <?= $hasCollection ? 'Insert' : 'Add' ?></a></li>
        </ul>
        <h3 class="text-muted">moa[db]</h3>
	</div>

	<div class="jumbotron">
		<div class="row marketing <?= $hasDB ? 'noMargin' : '' ?>">
			<?php
			if (!$hasDB) :
				$newRow = 0;
				foreach ($mo->mongo['dbs'] as $db => $desc):
					?>
					<div class="col-lg-3 database click">
						<?= $html->link("javascript: dropDatabase('" . get::htmlentities($db) . "'); void(0);", '&times;', ['class' => 'close hidden', 'title' => 'Drop Database']) ?>
						<img src="data:image/png;base64,<?= moadminModel::getDatabaseImage() ?>" class="wiggle" />
						<h4><?= $db ?></h4>
						<p><?= $desc ?></p>
					</div>
					<?php
				endforeach;
			elseif ($hasDB) :
				if (isset($mo->mongo['listCollections'])) :
					?>
					<div id="mongo_collections" class="side-nav">
						<?php
						if (!$mo->mongo['listCollections']) :
							echo $html->div('No collections exist');
						else :
							?>
							<table class="table collection click">
								<tbody>
									<?php
									$totalcount = 0;
									foreach ($mo->mongo['listCollections'] as $col => $rowCount) :
										$totalcount += $rowCount;
										?>
										<tr>
											<td>
									<icon class="shown icon-inbox"></icon>
									<?= $html->link("javascript: collectionDrop('" . urlencode($col) . "'); void(0);", '&times;', ['class' => 'close hidden', 'title' => 'Drop Collection']) ?>
									</td>
									<td><?= $html->link($baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode($col), $col, ['class' => '']) ?></td>
									<td><small title="<?= number_format($rowCount) ?>"><?= '(' . $html->bd_nice_number($rowCount) . ')' ?></small></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?
						endif;
						$url = $baseUrl . '?' . http_build_query($_GET);
						if (isset($collection)) {
							$url .= '&collection=' . urlencode($collection);
						}

//					SET limits #TODO
//					-----
//					echo $form->open(array('action' => $url, 'style' => 'width: 80px; height: 20px;'))
//					. $form->input(array('name' => 'limit', 'value' => $_SESSION['limit'], 'label' => '', 'addBreak' => false,
//						'style' => 'width: 40px;'))
//					. $form->submit(array('value' => 'limit', 'class' => 'ui-state-hover'))
//					. $form->close();
//					
						?>
					</div>
					<?
				endif;
			endif;
			?>
			<div id="main-content">
				<?php
				//stats on main page
				if ($hasDB && isset($mo->mongo['getStats'])) :
					?>
					<div class="content-scroll full">
						<div class="alert alert-success stats">You have <?= number_format($totalcount) ?> records and <?= count($mo->mongo['listCollections']) ?> collections.</div>
						<div class="alert alert-info stats <?= $totalcount > 0 ? 'opacity' : '' ?>">To add a new collection click 'Add' in the top right-hand corner.</div>	
						<table class="table stats">
							<tbody>
								<tr><td><h4>Database : <?= $db ?></h4></td></tr>
								<?php foreach ($mo->mongo['getStats'] as $key => $val) : ?>
									<tr>
										<td>
											<?php
											if (!is_array($val)) {
												echo $key . ': ' . $val;
											} else {
												echo $key . '<ul>';
												foreach ($val as $subkey => $subval) {
													echo $html->li((is_int($subkey) ? '' : $subkey . ': ') . (is_array($subval) ? print_r($subval) : $subval));
												}
												echo '</ul>';
											}
											?>
										</td>
									</tr>
								<? endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php
				endif;
				unset($mo->mongo['getStats']);

				//show collection object list
				if (isset($mo->mongo['listRows'])) {

//				Title and renaming #TODO
//				-----
//				echo $form->open(array('action' => $baseUrl . '?db=' . $dbUrl . '&action=renameCollection',
//					'style' => 'width: 600px; display: none;', 'id' => 'renamecollectionform'))
//				. $form->hidden(array('name' => 'collectionfrom', 'value' => $collection))
//				. $form->input(array('name' => 'collectionto', 'value' => $collection, 'label' => '', 'addBreak' => false))
//				. $form->submit(array('value' => 'Rename Collection', 'class' => 'ui-state-hover'))
//				. $form->close();
//				$js = "$('#collectionname').hide(); $('#renamecollectionform').show(); void(0);";
//				echo $collection.'<h1 id="collectionname">' . $html->link('javascript: ' . $js, $collection) . '</h1>';
//				Create and delete Mongo Indexes
//				------
//				if (isset($mo->mongo['listIndexes'])) {
//					echo '<ol id="indexes" style="display: none; margin-bottom: 10px;">';
//					echo $form->open(array('method' => 'get'));
//					echo '<div id="indexInput">'
//					. $form->input(array('name' => 'index[]', 'label' => '', 'addBreak' => false))
//					. $form->checkboxes(array('name' => 'isdescending[]', 'options' => array('Descending')))
//					. '</div>'
//					. '<a id="addindexcolumn" style="margin-left: 160px;" href="javascript: '
//					. "$('#addindexcolumn').before('<div>' + $('#indexInput').html().replace(/isdescending_Descending/g, "
//					. "'isdescending_Descending' + mo.indexCount++) + '</div>'); void(0);"
//					. '">[Add another index field]</a>'
//					. $form->radios(array('name' => 'unique', 'options' => array('Index', 'Unique'), 'value' => 'Index'))
//					. $form->submit(array('value' => 'Add new index', 'class' => 'ui-state-hover'))
//					. $form->hidden(array('name' => 'action', 'value' => 'ensureIndex'))
//					. $form->hidden(array('name' => 'db', 'value' => get::htmlentities($db)))
//					. $form->hidden(array('name' => 'collection', 'value' => $collection))
//					. $form->close();
//					foreach ($mo->mongo['listIndexes'] as $indexArray) {
//						$index = '';
//						foreach ($indexArray['key'] as $key => $direction) {
//							$index .= (!$index ? $key : ', ' . $key);
//							if (!is_object($direction)) {
//								$index .= ' [' . ($direction == -1 ? 'desc' : 'asc') . ']';
//							}
//						}
//						if (isset($indexArray['unique']) && $indexArray['unique']) {
//							$index .= ' [unique]';
//						}
//						if (key($indexArray['key']) != '_id' || count($indexArray['key']) !== 1) {
//							$index = '[' . $html->link($baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection)
//											. '&action=deleteIndex&index='
//											. serialize($indexArray['key']), 'X', array('title' => 'Drop Index',
//										'onclick' => "mo.confirm.href=this.href; "
//										. "mo.confirm('Are you sure that you want to drop this index?', "
//										. "function() {window.location.replace(mo.confirm.href);}); return false;")
//									) . '] '
//									. $index;
//						}
//						echo '<li>' . $index . '</li>';
//					}
//					echo '</ol>';
//				}

					$objCount = $mo->mongo['listRows']->count(true); //count of rows returned

					$paginator = number_format($mo->mongo['count']) . ' objects'; //count of rows in collection
					if ($objCount && $mo->mongo['count'] != $objCount) {
						$skip = (isset($_GET['skip']) ? $_GET['skip'] : 0);
						$get = $_GET;
						unset($get['skip']);
						$url = $baseUrl . '?' . http_build_query($get) . '&collection=' . urlencode($collection) . '&skip=';
						$paginator = $html->li(addslashes($html->link('', number_format($skip + 1) . '-' . number_format(min($skip + $objCount, $mo->mongo['count']))
												. ' of ' . $paginator)));
						if ($skip) { //back
							$paginator = $html->li(addslashes($html->link($url . max($skip - $objCount, 0), '&laquo;'))) . ' ' . $paginator;
						}
						if ($mo->mongo['count'] > ($objCount + $skip)) { //forward
							$paginator .= ' ' . $html->li(addslashes($html->link($url . ($skip + $objCount), '&raquo;')));
						}
					}

					$get = $_GET;
					$get['collection'] = urlencode($collection);
					$queryGet = $searchGet = $sortGet = $get;
					unset($sortGet['sort'], $sortGet['sortdir']);
					unset($searchGet['search'], $searchGet['searchField']);
					unset($queryGet['find']);


					echo $html->jsInline('mo.indexCount = 1;
				$(document).ready(function() {
					$("#mongo_rows .content-scroll").append("<ul class=\"pagination\">' . $paginator . '</ul>");
				});' . $dbcollnavJs);
					$jsShowIndexes = "javascript: $('#indexeslink').hide(); $('#indexes').show(); void(0);";

					/*
					 * Toolbar Options
					 */

					echo '<div id="mongo_rows">';

					$linkFindClass = isset($_GET['find']) ? ' running' : '';
					$query = ['id' => 'querylink',
						'class' => 'btn btn-default' . $linkFindClass,
						'data-popup' => 'true',
						'data-title' => 'Find Query',
						'data-body' => '#queryform',
						'data-button' => 'Run'
					];

					echo $html->link(null, 'find', $query);
					if (isset($index)) {
						echo $html->link($jsShowIndexes, 'indexes', ['id' => 'indexeslink', 'class' => 'btn btn-default']);
					}
					echo $html->link(null, 'export', ['id' => 'exportlink', 'class' => 'btn btn-default', 'data-popup' => 'true', 'data-title' => 'Export Options', 'data-body' => '#export', 'data-button' => 'hidden']);
					echo $html->link(null, 'import', ['id' => 'importlink', 'class' => 'btn btn-default', 'data-popup' => 'true', 'data-title' => 'Import Options', 'data-body' => '#import', 'data-button' => 'Import records into this collection']);

					if ($mo->mongo['colKeys']) {
						$colKeys = $mo->mongo['colKeys'];
						unset($colKeys['_id']);
						natcasesort($colKeys);

						$linkSortClass = isset($_GET['sort']) ? ' running' : '';
						$sort = ['id' => 'sortlink',
							'class' => 'btn btn-default' . $linkSortClass,
							'data-popup' => 'true',
							'data-title' => 'Sort Options',
							'data-body' => '#sortform',
							'data-button' => 'Sort'
						];

						echo $html->link(null, 'sort', $sort);
						?><div id="sortform" class="hidden">
							<select name="sort" id="sort" class="form-control" data-type="sort">
								<?php
								$defaultCols = ['_id' => '_id'];
								$colKeys = array_merge($defaultCols, $colKeys);
								foreach ($colKeys as $k => $v) :
									?>
									<option value="<?= $k ?>" <?= isset($_GET['sort']) && $_GET['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option>
								<?php endforeach; ?>
							</select><br>
							<select name="sortdir" id="sortdir" class="form-control">
								<option value="1" <?= isset($_GET['sortdir']) && $_GET['sortdir'] == 1 ? 'selected' : '' ?>>asc</option>
								<option value="-1" <?= isset($_GET['sortdir']) && $_GET['sortdir'] == -1 ? 'selected' : '' ?>>desc</option>
							</select><br>
							<?php if (isset($_GET['sort'])) : ?>
								<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear sorting options</a>
							<?php endif; ?>
						</div><?php
					$linkSearchClass = isset($_GET['search']) ? ' running' : '';
					$search = ['id' => 'searchlink',
						'class' => 'btn btn-default' . $linkSearchClass,
						'data-popup' => 'true',
						'data-title' => 'Search Options',
						'data-body' => '#searchform',
						'data-button' => 'Search'
					];

					echo $html->link(null, 'search', $search);
							?><div id="searchform" class="hidden">
							<p class="alert alert-info">Valid search formats : exact-text, type-casted value, mongoid, text (with * wildcards), regex or JSON.</p>
							<select name="searchField" id="searchField" class="form-control" data-type="search">
								<?php
								$defaultCols = ['_id' => '_id'];
								$colKeys = array_merge($defaultCols, $colKeys);
								foreach ($colKeys as $k => $v) :
									?>
									<option value="<?= $k ?>" <?= isset($_GET['searchField']) && $_GET['searchField'] === $k ? 'selected' : '' ?>><?= $v ?></option>
								<?php endforeach; ?>
							</select><br>
							<input type="text" name="search" id="search" class="form-control input-lg"  placeholder="Search..." value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
							<br>
							<?php if (isset($_GET['search'])) : ?>
								<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear search options</a>
							<?php endif; ?>
						</div><?php
				}

				$remove = ['id' => 'removelink',
					'class' => 'btn btn-default',
					'data-popup' => 'true',
					'data-title' => 'Remove Query',
					'data-body' => '#removeform',
					'data-button' => 'Remove'
				];

				echo $html->link(null, 'remove', $remove);

				/*
				 * List Rows
				 */

				echo '<div class="content-scroll">';
				echo '<table class="table table-hover""><tbody>';
				$rowCount = (!isset($skip) ? 0 : $skip);
				$isChunksTable = (substr($collection, -7) == '.chunks');
				if ($isChunksTable) {
					$chunkUrl = $baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode(substr($collection, 0, -7))
							. '.files#';
				}
				foreach ($mo->mongo['listRows'] as $row) {
					$showEdit = true;
					$id = $idString = $row['_id'];
					if (is_object($idString)) {
						$idString = '(' . get_class($idString) . ') ' . $idString;
						$idForUrl = serialize($id);
					} else if (is_array($idString)) {
						$idString = '(array) ' . json_encode($idString);
						$idForUrl = serialize($id);
					} else {
						$idForUrl = urlencode($id);
					}
					$idType = gettype($row['_id']);
					if ($isChunksTable && isset($row['data']) && is_object($row['data'])
							&& get_class($row['data']) == 'MongoBinData') {
						$showEdit = false;
						$row['data'] = $html->link($chunkUrl . $row['files_id'], 'MongoBinData Object', array('class' => 'Moa_Reference'));
					}
					$jdata = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
					$data = str_replace('{', '[', ($jdata));
					$data = str_replace('}', ']', ($data));
					$data = str_replace(':', ' =>', ($data));

					echo ('<tr id="' . $row['_id'] . '">'
					. '<td><icon class="icon-caret-right"></icon></td>'
					. '<td class="hidden noclick">' . $html->link("javascript: removeObject('" . $idForUrl . "', '" . $idType
							. "'); void(0);", '<icon class="icon-remove-sign"></icon>', ['title' => 'Delete', 'class' => 'close']) . '</td> '
					. ($showEdit ? '<td class="noclick">' . $html->link($baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection)
									. '&action=editObject&_id=' . $idForUrl . '&idtype=' . $idType, '<icon class="icon-edit-sign"></icon>', array('title' => 'Edit')) . '</td> ' : ' <td><span title="Cannot edit objects containing MongoBinData">N/A</span></td> ')
					. '<td>' . $idString . '</td>' . '</tr><tr data-ref="' . $row['_id'] . '" class="hidden"><td colspan="4"><icon class="icon-eye-open"></icon> <a class="aotoggle">Obj View</a><pre>'
					. $jdata . '</pre><pre class="hidden">' . $data . '</pre></td></tr>');
				}
				echo '</tbody></table>';
				echo '</div>';
				if (!isset($idString)) {
					echo '<div class="errormessage">No records in this collection</div>';
				}
				echo '</div>';

				//edit object
			} else if (isset($mo->mongo['editObject'])) {

				$action = $baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection);
				if (isset($_GET['_id']))
					$action = $baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection) . '&action=editObject&_id=' . $_GET['_id'] . '&idtype=' . $_GET['idtype'];
				echo $form->open(array('action' => $action));
				if (isset($_GET['_id']) && $_GET['_id'] && ($_GET['idtype'] == 'object' || $_GET['idtype'] == 'array')) {
					$_GET['_id'] = unserialize($_GET['_id']);
					if (is_array($_GET['_id'])) {
						$_GET['_id'] = json_encode($_GET['_id']);
					}
				}
						?><h4><?= isset($_GET['_id']) && $_GET['_id'] ? get::htmlentities($_GET['_id']) : '[New Object]' ?></h4><?
				$textarea = array('name' => 'object', 'label' => '', 'rows' => "14", 'class' => 'form-control input-lg', 'addBreak' => false);
				$textarea['value'] = ($mo->mongo['editObject'] !== '' ? json_encode($mo->mongo['editObject'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{' . PHP_EOL . PHP_EOL . '}');
				echo $html->div($form->textarea($textarea)
						. $form->hidden(array('name' => 'action', 'value' => 'editObject')));
				echo $html->div($form->hidden(array('name' => 'db', 'value' => get::htmlentities($db))));
						?><a href="<?= $baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode($collection); ?>" id="close-object" class="btn btn-default">Close</a>
					<button type="submit" id="edit-object" class="btn btn-<?= isset($_GET['saved']) ? 'success' : 'primary' ?> "><?= isset($_GET['_id']) && $_GET['_id'] ? (isset($_GET['saved']) ? 'Saved' : 'Save' ) : (isset($_GET['saved']) ? 'Saved' : 'Add' ) ?></button><?php
				echo $form->close();
			}
					?>

			</div>

		</div>
	</div>

	<!-- Modal Content -->
	<div id="new-collection" class="hidden">
		<form method = "GET" data-type ="collection" action="<?= $baseUrl ?>?db=<?= $db ?>">
			<input type="text" name="collection" class="form-control input-lg" placeholder="Collection name">
			<input type="hidden" name="action" value="createCollection">
			<input type="hidden" name="db" value="<?= $db ?>">
		</form>
	</div>

	<div id="new-database" class="hidden">
		<form method = "POST" data-type="database">
			<input type="hidden" name="db" value="new.database" />
			<input type="text" name="newdb" class="form-control input-lg" placeholder="Database name" />
		</form>
	</div>

	<ul id="export" class="hidden">
		<div>
			<?= $html->link(get::url(array('get' => true)) . '&export=limited', '<icon class="icon-download"></icon>&nbsp;Export exactly the results visible on this page', ['class' => "btn btn-success btn-lg btn-block"]); ?>
			<?= $html->link(get::url(array('get' => true)) . '&export=nolimit', '<icon class="icon-cloud-download"></icon>&nbsp;Export full results of this query <small>(ignoring limit and skip clauses)</small>', ['class' => "btn btn-default btn-lg btn-block"]); ?>
		</div>
	</ul>

	<div id="import" class="hidden">
		<?= $form->open(['upload' => true, 'role' => 'form']) ?>
		<fieldset>
			<div class="form-group">
				<label for="exampleInputFile">Browse / Choose your file</label>
				<input type="file" name="import" accept="application/json">
				<p class="help-block"><small>File ending with ".json".</small></p>
			</div>
			<div id="importmethod">
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_insert" value="insert" checked="checked">&nbsp;Insert
						<small class="help-inline">&nbsp;-&nbsp;Skips over duplicate records</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_save" value="save">&nbsp;Save
						<small class="help-inline">&nbsp;-&nbsp;Overwrites duplicate records</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_update" value="update">&nbsp;Update
						<small class="help-inline">&nbsp;-&nbsp;Overwrites only records that currently exist (skips new objects)</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_batchInsert" value="batchInsert">&nbsp;Batch Insert
						<small class="help-inline">&nbsp;-&nbsp;Halt upon reaching first duplicate record (may result in partial dataset)</small>
					</label>
				</div>
			</div>
		</fieldset>
		<?= $form->close() ?>
	</div>

	<div id="queryform" class="hidden">
		<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
		<div class="alert alert-danger hidden">Invalid json or dot-notation query, e.g. {"values.text.value":"ABC"}.</div>
		<textarea data-type="query" id="find" rows="4" class="form-control input-lg" placeholder="{ Enter query }"><?= isset($_GET['find']) ? $_GET['find'] : '' ?></textarea>
		<small class="help-block">Need help? Check out the documentation here : <a href="http://docs.mongodb.org/manual/reference/method/db.collection.find/">Mongo Query Find</a></small>
		<?php if (isset($_GET['find'])) : ?>
			<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear query find</a>
		<?php endif; ?>					
	</div>

	<div id="removeform" class="hidden">
		<form action="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>&remove=query&request=<?= time() ?>" method="post" data-type="removeQuery">
			<p class="alert alert-info">You can also remove objects manually by closing this panel and clicking the 'Edit' button in the top right-hand corner. Please take care when removing via query.</p>				
			<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
			<div class="alert alert-danger hidden">Invalid json or dot-notation query, try again.</div>
			<textarea id="removeQuery" rows="2" name="remove" class="form-control input-lg" placeholder="{ Remove query }"><?= isset($_POST['remove']) ? $_POST['remove'] : '' ?></textarea>
			<small class="help-block">Need help? Check out the documentation here : <a href="http://docs.mongodb.org/manual/reference/method/db.collection.remove/">Mongo Query Remove</a></small>				
		</form>
	</div>

	<div id="new-object" class="hidden">
		<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
		<div class="alert alert-danger hidden">Your object is invalid so will be saved a string, i.e. {0 : "<span class="string">string</span>"}. Press 'Add' again to continue or <strong><a class="objectclose">dismiss</a></strong> to edit.</div>

		<form action="<?= $baseUrl ?>?db=<?= $db ?>&collection=<?= $collection ?>" method="post" data-type="object">
			<fieldset>
				<textarea name="object" id="newObj" class="form-control input-lg" rows="10">{ }</textarea>
				<input type="hidden" name="action" value="editObject">
				<input type="hidden" name="db" value="<?= $db ?>">
			</fieldset>
		</form>
	</div>


	<!-- Modal -->
	<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title"></h4>
				</div>
				<div class="modal-body"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button type="button" id="btn-main" class="btn btn-primary ">Add</button>
				</div>
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->



	<div class="footer">
        <p><small>Contribute to Moa[db]. Jason Pickering © 2013 | <small>Forked from phpMoAdmin</small></small></p>
	</div>

</div> <!-- /container -->
<script> 
	//<!-- original public functions -->
	var urlEncode = function(str) {
		return escape(str).replace(/\+/g, "%2B").replace(/%20/g, "+").replace(/\*/g, "%2A").replace(/\//g, "%2F").replace(/@/g, "%40");
	};
	var repairDatabase = function(db) {
		if(confirm("Are you sure that you want to repair and compact the " + db + " database?")) {
			//window.location.replace("' . $baseUrl . '?db=' . $dbUrl . '&action=repairDb");
		}
	};
<?php if (!$hasDB) : ?>
		var dropDatabase = function(db) {
			if(confirm("Are you sure that you want to drop the " + db + " database?")) {
				if(confirm("All the collections in the " + db + " database will be lost along with all the data within them!"
					+ '\n\nAre you 100% sure that you want to drop this database?'
					+ "\n\nLast chance to cancel!")){
					window.location.replace("<?= $baseUrl ?>" + "?db=" + db + "&action=dropDb");
				}
			}
		};
<?php elseif ($hasCollection) : ?>
		var removeObject = function(_id, idType) {
			if(confirm("Are you sure that you want to delete this " + _id + " object?")) {
				window.location.replace("<?= $baseUrl . '?db=' . $db . '&collection=' . urlencode($collection)
	. '&action=removeObject&_id='
	?>" + urlEncode(_id) + "&idtype=" + idType);
							}
						};
<?php else : ?>
		var collectionDrop = function(collection) {
			if(confirm("Are you sure that you want to drop " + collection + "?")){
				window.location.replace("<?= $baseUrl . '?db=' . $db . '&collection=' ?>" + collection + "<?= '&action=dropCollection' ?>");
							}
						};
<?php endif; ?>
</script>
<script>
	$('[data-popup]').click(function(e){
		e.preventDefault;
		$('#modal #btn-main').show();
		if($(this).attr('data-title'))
			$('#modal .modal-title').text($(this).attr('data-title'));
		if($(this).attr('data-body'))
			$('#modal .modal-body').html($($(this).attr('data-body')).html());
		if($(this).attr('data-button'))
			if($(this).attr('data-button') === 'hidden')
				$('#modal #btn-main').hide();
		else
			$('#modal #btn-main').text($(this).attr('data-button'));
		$('#modal').modal('show');
	});
	$('#modal #btn-main').click(function(e){
		var m = $('#modal .modal-body');
		var f = m.find('form');
		switch(m.find('[data-type]').attr('data-type')){
			case 'object' :
				if(m.find('.alert-warning').hasClass('hidden') &&
					m.find('.alert-danger').hasClass('hidden')){
					var obj = $('#modal #newObj').val();
					var query = obj;
					if (query.substring(0, 1) !== "{") query = '{' + query;
					if (query.substring(query.length-1, query.length) !== "}") query = query + '}';
					try{
						var objectvalid = JSON.parse(query);
					}catch(e){
						if(e.message == "Unexpected token '"){
							m.find('.alert-warning').removeClass('hidden');
							m.find('.swopquote').click(function(){
								$('#modal #newObj').val($('#modal #newObj').val().replace(/\'/g, '"'));
								m.find('.alert-warning').addClass('hidden');
							});
						}
						else{
							m.find('.alert-danger .string').text(obj.substring(0, 4) + '...');
							m.find('.alert-danger').removeClass('hidden');
							m.find('.objectclose').click(function(){
								m.find('.alert-danger').addClass('hidden');
							});
						}
						return;
					}
				}
				break;
			case 'collection' :
				break;
			case 'database' :
				break;
			case 'sort' :
				document.location = "<?= $baseUrl . '?' . http_build_query($sortGet) . '&sort=' ?>" + $('#modal #sort').val() + '&sortdir=' + $('#modal #sortdir').val();
				return;
			case 'search' :
				document.location = "<?= $baseUrl . '?' . http_build_query($searchGet) . '&search=' ?>" + $('#modal #search').val() + '&searchField=' + $('#modal #searchField').val();
				return;
			case 'removeQuery' :
				var removequery = $('#modal #removeQuery').val();
				if (removequery.substring(0, 1) !== "{") removequery = '{' + removequery;
				if (removequery.substring(removequery.length-1, removequery.length) !== "}") removequery = removequery + '}';
				try{
					var queryvalid = JSON.parse(removequery);
					$('#modal #removeQuery').val(removequery);
				}catch(e){
					if(e.message == "Unexpected token '"){
						m.find('.alert-warning').removeClass('hidden');
						m.find('.swopquote').click(function(){
							$('#modal #find').val($('#modal #find').val().replace(/\'/g, '"'));
							m.find('.alert-warning').addClass('hidden');
						});
					}
					else
						m.find('.alert-danger').removeClass('hidden');
					return;
				}
				break;
			case 'query' :
				var query = $('#modal #find').val();
				if (query.substring(0, 1) !== "{") query = '{' + query;
				if (query.substring(query.length-1, query.length) !== "}") query = query + '}';
				try{
					var queryvalid = JSON.parse(query);
				}catch(e){
					if(e.message == "Unexpected token '"){
						m.find('.alert-warning').removeClass('hidden');
						m.find('.swopquote').click(function(){
							$('#modal #find').val($('#modal #find').val().replace(/\'/g, '"'));
							m.find('.alert-warning').addClass('hidden');
						});
					}
					else
						m.find('.alert-danger').removeClass('hidden');
					return;
				}
				document.location = "<?= $baseUrl . '?' . http_build_query($queryGet) . '&find=' ?>" + query;
				return;
								
			}
			if(f.length) 
				f.submit();

		});
	
		$('#modal .modal-body').keyup(function(){
			if($('.alert-danger', this).hasClass('hidden') && $('.alert-warning', this).hasClass('hidden')) 
				return;
			$('.alert', this).addClass('hidden');
		});
		$('#edit-button').click(function(){
			var type = $('.header li.active a').attr('data-view');
			switch(type) {
				case 'Databases':
					var d = $('.database');
					if(d.hasClass('edit'))
						$('.database .wiggle').ClassyWiggle('stop');
					else
						$('.database .wiggle').ClassyWiggle('start');
					d.toggleClass('click edit');
					d.find('.close').toggleClass('hidden');
					break;
				case 'Collections':
					var c = $('.collection');
					c.toggleClass('click edit');
					c.find('.shown').toggleClass('hidden');
					c.find('.close').toggleClass('hidden');
					break;
				case 'CollectionRow':
					$('#main-content table .close').parent('td').toggleClass('hidden');
					break;
				default : return;
			}
			$(this).toggleClass('open');
		});
		$('.marketing .database.click').click(function(){
			if(!$('.marketing .database.click').length) return;
			window.location.href = '?db=' + $(this).find('h4').html();
		});
		$('table.table-hover tr td:not(.noclick)').click(function(e){
			var $this = $(this).parents('tr');
			if($this.attr('data-ref')) return;
			$('.icon-caret-right, .icon-caret-down' ,$this).toggleClass('icon-caret-right icon-caret-down');
			$('table.table-hover tr[data-ref="'+$this.attr('id')+'"]').toggleClass('hidden');
		});
		$('.aotoggle').click(function() {
			var v = $(this).html();
			var pre = $(this).parents('td').find('pre');
			if(v === 'Obj View')
				$(this).html('Array View');
			else
				$(this).html('Obj View');
			pre.toggleClass('hidden');
		
		});
</script>
<script>
		/*!
		 * jQuery ClassyWiggle
		 * http://www.class.pm/projects/jquery/classywiggle
		 *
		 * Copyright 2011 - 2013, Class.PM www.class.pm
		 * Written by Marius Stanciu - Sergiu <marius@picozu.net>
		 * Licensed under the GPL Version 3 license.
		 * Version 1.1.0
		 *
		 */
		(function($) {
			$.fn.ClassyWiggle = function(method, options) {
				options = $.extend({
					degrees: ['2','4','2','0','-2','-4','-2','0'],
					delay: 35,
					limit: null,
					randomStart: true,
					onWiggle: function(o) {

					},
					onWiggleStart: function(o) {

					},
					onWiggleStop: function(o) {
                
					}
				}, options);
				var methods = {
					wiggle: function(o, step){
						if (step === undefined) {
							step = options.randomStart ? Math.floor(Math.random() * options.degrees.length) : 0;
						}
						if (!$(o).hasClass('wiggling')) {
							$(o).addClass('wiggling');
						}
						var degree = options.degrees[step];
						$(o).css({
							'-webkit-transform': 'rotate(' + degree + 'deg)',
							'-moz-transform': 'rotate(' + degree + 'deg)',
							'-o-transform': 'rotate(' + degree + 'deg)',
							'-sand-transform': 'rotate(' + degree + 'deg)',
							'transform': 'rotate(' + degree + 'deg)'
						});
						if (step == (options.degrees.length - 1)) {
							step = 0;
							if ($(o).data('wiggles') === undefined) {
								$(o).data('wiggles', 1);
							}
							else {
								$(o).data('wiggles', $(o).data('wiggles') + 1);
							}
							options.onWiggle(o);
						}
						if (options.limit && $(o).data('wiggles') == options.limit) {
							return methods.stop(o);
						}
						o.timeout = setTimeout(function() {
							methods.wiggle(o, step + 1);
						}, options.delay);
					},
					stop: function(o) {
						$(o).data('wiggles', 0);
						$(o).css({
							'-webkit-transform': 'rotate(0deg)',
							'-moz-transform': 'rotate(0deg)',
							'-o-transform': 'rotate(0deg)',
							'-sand-transform': 'rotate(0deg)',
							'transform': 'rotate(0deg)'
						});
						if ($(o).hasClass('wiggling')) {
							$(o).removeClass('wiggling');
						}
						clearTimeout(o.timeout);
						o.timeout = null;
						options.onWiggleStop(o);
					},
					isWiggling: function(o) {
						return !o.timeout ? false : true;
					}
				};
				if (method == 'isWiggling' && this.length == 1) {
					return methods.isWiggling(this[0]);
				}
				this.each(function() {
					if ((method == 'start' || method === undefined) && !this.timeout) {
						methods.wiggle(this);
						options.onWiggleStart(this);
					}
					else if (method == 'stop') {
						methods.stop(this);
					}
				});
				return this;
			}
		})(jQuery);
</script>