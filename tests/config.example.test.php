<?php
define('TESTS_DB_DSN', 'mysql://root:@localhost/intraface');
define('DB_DSN', TESTS_DB_DSN);

set_include_path(dirname(__FILE__) . '/../src/' . PATH_SEPARATOR . get_include_path());
