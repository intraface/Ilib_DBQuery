<?php
error_reporting(E_ALL ^~E_STRICT);
set_include_path(dirname(__FILE__) . '/../src/' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__) . '/bootstrap.php';
require_once dirname(__FILE__) . '/../src/Ilib/DBQuery.php';
require_once 'MDB2.php';
require_once 'Ilib/Error.php';
require_once 'DB/Sql.php';
