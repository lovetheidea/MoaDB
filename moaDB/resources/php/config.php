<?php

/**
 * Copyright (C) 2013 MoaDB
 *
 * www.MongoDB.org
 *
 * @version 1.0.0
 * @license GPL v3
 */

/**
 * Set default Time Zone. GMT default
 */
date_default_timezone_set("GMT");

/**
 * To enable password protection, uncomment below and then change the username => password
 * You can add as many users as needed, eg.: array('scott' => 'tiger', 'samantha' => 'goldfish', 'gene' => 'alpaca')
 */
//$accessControl = array('username' => 'password');

/**
 * Uncomment to restrict databases-access to just the databases added to the array below
 * uncommenting will also remove the ability to create a new database
 */
//moaModel::$databaseWhitelist = array('admin');

/**
 * To connect to a remote or authenticated Mongo instance, define the connection string in the MONGO_CONNECTION constant
 * mongodb://[username:password@]host1[:port1][,host2[:port2:],...]
 * If you do not know what this means then it is not relevant to your application and you can safely leave it as-is
 */
define('MONGO_CONNECTION', getenv("MONGODB_URL"));

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
 * Contributing-developers of the moaDB project should set this to true, everyone else can leave this as false
 */
define('DEBUG_MODE', false);

