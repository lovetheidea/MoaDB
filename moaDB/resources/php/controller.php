<?php

/**
 * Copyright (C) 2013 MoaDB
 * @license GPL v3
 */

/**
 * moaDB application controller
 */
class moaController {

	/**
	 * $this->mongo is used to pass properties from component to view without relying on a controller to return them
	 * @var array
	 */
	public $mongo = array();

	/**
	 * Model object
	 * @var moaModel
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
