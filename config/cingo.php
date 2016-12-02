<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


// Setup different logons based on global ENVIRONMENT variable set in index.php
$hosts['development']['host'] = NULL;                    // Generally localhost
$hosts['development']['port'] = NULL;                        // Generally 27017
$hosts['development']['login_db'] = NULL;              // Database to log in to
$hosts['development']['db'] = NULL;                      // Database to work on
$hosts['development']['user'] = NULL;   // Required if Mongo is using auth mode
$hosts['development']['pass'] = NULL;   // Required if Mongo is using auth mode

// Set connection params based on ENVIRONMENT
$config['host'] = $hosts[ENVIRONMENT]['host'];
$config['port'] = $hosts[ENVIRONMENT]['port'];
$config['login_db'] = $hosts[ENVIRONMENT]['login_db'];
$config['db'] = $hosts[ENVIRONMENT]['db'];
$config['user'] = $hosts[ENVIRONMENT]['user'];
$config['pass'] = $hosts[ENVIRONMENT]['pass'];
