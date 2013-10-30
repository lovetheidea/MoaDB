<?php
/**
 * Copyright (C) 2013 MoaDB
 * @license GPL v3
 */
/**
 * moaDB Start
 */
session_start();

if (get_magic_quotes_gpc()) {
	$_GET = moaDB::stripslashes($_GET);
	$_POST = moaDB::stripslashes($_POST);
}

$hasDB = (isset($_GET['db']) ? true : (isset($_POST['db']) ? true : false)); //$_GET['db'] will default to admin
$hasCollection = (isset($_GET['collection']) ? true : false);

if (!isset($_GET['db']) && !isset($_POST['newdb'])) {
	$_GET['db'] = moaModel::$dbName;
} else if (!isset($_GET['db']) && isset($_POST['newdb'])) {
	$_GET['db'] = $_POST['newdb'];
}
try {
	moaController::$model = new moaModel($_GET['db']);
} catch (Exception $e) {
	echo $e;
	exit(0);
}
$html = get::helper('html');
$ver = explode('.', phpversion());
get::$isPhp523orNewer = ($ver[0] >= 5 && ($ver[1] > 2 || ($ver[1] == 2 && $ver[2] >= 3)));
$form = new formHelper;
$mo = new moaController;

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
 * moaDB front-end view-element
 */
//Change to local path or url
$headerArgs = [
	'title' => 'MoaDB',
	'css' => [
		'//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css',
		'./resources/css/base.css',
		'//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.min.css',
		'http://fonts.googleapis.com/css?family=Exo:900'
	],
	'js' => [
		'//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js',
		'./resources/js/main.js',
		'./resources/js/classyWiggle.js',
	]
];
//Load Google jQuery from URL.
echo $html->jsLoad(array('jquery', 'jqueryui'));
echo $html->header($headerArgs);


$baseUrl = $_SERVER['SCRIPT_NAME'];
$db = (isset($_GET['db']) ? $_GET['db'] : (isset($_POST['db']) ? $_POST['db'] : 'admin')); //admin is in every Mongo DB
$dbUrl = urlencode($db);

if (isset($_GET['collection'])) {
	$collection = get::htmlentities($_GET['collection']);
	unset($_GET['collection']);
}

$showUserPassword = false;
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
		$showUserPassword = true;
	}
}