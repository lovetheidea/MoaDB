<?php


/**
 * Copyright (C) 2013 MoaDB
 * @license GPL v3
 */

/**
 * moadDB Mongo moaModel
 */
class moaModel {
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
		$unshift['moaDB'] = '1.0.9';
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
				$this->colKeys = moaDB::getArrayKeys($col->findOne());
			}
			$cur->limit($_SESSION['limit']);
			if (isset($_GET['skip'])) {
				if ($this->count <= $_GET['skip']) {
					$_GET['skip'] = ($this->count - $_SESSION['limit']);
				}
				$cur->skip($_GET['skip']);
			}
		} else if ($this->count) { // results exist but are fewer than per-page limit
			$this->colKeys = moaDB::getArrayKeys($cur->getNext());
		} else if ($find && $col->count()) { //query is not returning anything, get cols from first obj in collection
			$this->colKeys = moaDB::getArrayKeys($col->findOne());
		}

		//get keys of last or much-later object
		if ($this->count > 1) {
			$curLast = $col->find()->sort($sort);
			if ($this->count > 2) {
				$curLast->skip(min($this->count, 100) - 1);
			}
			$this->colKeys = array_merge($this->colKeys, moaDB::getArrayKeys($curLast->getNext()));
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