<?php
require_once dirname(__FILE__) . '/bootstrap.php';
require_once dirname(__FILE__) . '/../src/Ilib/DBQuery.php';
require_once 'MDB2.php';
require_once 'Ilib/Error.php';
require_once 'DB/Sql.php';

define('TESTS_DB_DSN', 'mysql://'.$GLOBALS['db_username'].':'.$GLOBALS['db_password'].'@localhost/'.$GLOBALS['db_name']);
define('DB_DSN', TESTS_DB_DSN);

/**
 * The actual tests of DBQuery should be in Intraface_3Party
 */
class DBQueryTest extends PHPUnit_Framework_TestCase
{
    protected $db;
    protected $table = 'dbquery_test';
    protected $session_id;
    protected $backupGlobals = FALSE;

    function setUp()
    {
        $this->session_id = 'dsjr93jdi93id39ei2d93kdd9d2';

        $this->db = MDB2::factory(TESTS_DB_DSN);
        $result = $this->db->exec('TRUNCATE TABLE dbquery_result');
        $result = $this->db->exec('TRUNCATE TABLE keyword');
        $result = $this->db->exec('TRUNCATE TABLE keyword_x_object');

        $result = $this->db->exec('DROP TABLE ' . $this->table);

        /*
         TODO: DROP THE TABLE IF IT EXISTS

        $result = $this->db->exec('DROP TABLE ' . $this->table);
        */

        $result = $this->db->exec('CREATE TABLE ' . $this->table . '(
            id int(11) NOT NULL auto_increment, name varchar(255) NOT NULL, PRIMARY KEY  (id))'
        );
        $this->insertPosts();
    }

    function createDBQuery()
    {
        return new Ilib_DBQuery($this->table);
    }

    function insertPosts($count = 21)
    {
        $data = array('one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'æske', 'åbne');
        $i = 0;
        foreach ($data as $d) {
            if ($count <= $i) {
                break;
            }
            $this->createPost($d);
            $i++;
        }
    }

    function createPost($post)
    {
        $result = $this->db->exec('INSERT INTO ' . $this->table . ' (name) VALUES ('.$this->db->quote($post, 'text').')');
    }

    function tearDown()
    {
        $this->db = MDB2::factory(TESTS_DB_DSN);
        $result = $this->db->exec('DROP TABLE ' . $this->table);
    }

    ///////////////////////////////////////////////////////////////////////////

    function testConstructor()
    {
        $dbquery = $this->createDBQuery();
        $this->assertTrue(is_object($dbquery));
        $this->assertEquals($this->table, $dbquery->getTableName());
    }

    function testRequiredConditions()
    {
        $condition = 'name = 1';
        $dbquery = new Ilib_DBQuery($this->table, $condition);
        $this->assertEquals($condition, $dbquery->required_conditions);
    }

    function testGetCharacters()
    {
        $dbquery = $this->createDBQuery();
        $db = $dbquery->getRecordset('*', '', false);
        $this->assertEquals(21, $db->numRows());
        $dbquery->useCharacter();
        $dbquery->defineCharacter('t', 'name');
        $this->assertTrue($dbquery->getUseCharacter());
        $characters = $dbquery->getCharacters();
        $this->assertEquals(8, count($characters));
    }

    function testDisplayCharactersWillNotDiplayIfPostsBelowThreshold()
    {
        $result = $this->db->exec('DROP TABLE ' . $this->table);
        $result = $this->db->exec('CREATE TABLE ' . $this->table . '(
            id int(11) NOT NULL auto_increment, name varchar(255) NOT NULL, PRIMARY KEY  (id))'
        );

        $this->insertPosts(21);

        $dbquery = $this->createDBQuery();
        $db = $dbquery->getRecordset('*', '', false);
        $this->assertEquals(21, $db->numRows());
        $dbquery->useCharacter();
        $characters = $dbquery->display('character');
        $this->assertEquals('', $characters);
    }

    function testPaging()
    {
        $dbquery = $this->createDBQuery();
        $paging_name = 'paging';
        $rows_pr_page = 2;
        $dbquery->usePaging($paging_name, $rows_pr_page);
        $db = $dbquery->getRecordset('*', '', false);

        $this->assertEquals($paging_name, $dbquery->getPagingVarName());

        $paging = $dbquery->getPaging();
        $expected_offset = array(1=>0, 2=>2, 3=>4, 4=>6, 5=>8, 6=>10, 7=>12, 8=>14, 9=>16,10=>18,11=>20);
        $this->assertEquals($expected_offset, $paging['offset']);
        $this->assertEquals(0, $paging['previous']);
        $this->assertEquals(2, $paging['next']);
    }

    function testGetCharactersEncoding()
    {
        $dbquery = $this->createDBQuery();
        $dbquery->useCharacter();
        $dbquery->defineCharacter("character", "name");

        $db = $dbquery->getRecordset('*', '', false);

        $expected = array(
            'e', 'f', 'n', 'o', 's', 't', 'æ', 'ø'
        );

        $this->assertEquals($expected, $dbquery->getCharacters());
    }

    function testGetUriReturnsCorrectLink()
    {
        $dbquery = $this->createDBQuery();
        $uri = '/index/page.php';
        $dbquery->setUri($uri);
        $paging = $dbquery->getUri();
        $this->assertEquals($uri, $paging);
    }

    function testGetRecordset()
    {
        $dbquery = $this->createDBQuery();

        $dbquery->setCondition('id > 2');

        $db = $dbquery->getRecordset('id, name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }

        $this->assertEquals(19, count($result));
    }

    function testCreateStoreThrowsAnExceptionIfStrLenForSessionIdIsLessThanTen()
    {
        try {
            $dbquery = $this->createDBQuery();
            $dbquery->createStore('', 'intranet_id = 1');
            $this->assertTrue(false, 'exception not thrown');
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    function testUseStoreOnTopLevel()
    {
        $dbquery = $this->createDBQuery();
        $dbquery->setCondition('id > 10');
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $_GET['use_stored'] = 'true';
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }
        $this->assertEquals(11, count($result));
    }

    function testUseStoreOnTopLevelWithAnotherOneInBetween()
    {
        // the first page
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->setCondition('id > 10');
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');

        // another page also with toplevel - overrides the first one saved
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->storeResult("use_stored", 'unittest-on-another-page', "toplevel");
        $db = $dbquery->getRecordset('id, name');

        // then back to the first page again - the result should not be saved
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $_GET['use_stored'] = 'true';
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }
        $this->assertEquals(21, count($result));
    }

    function testUseStoreOnSublevelNotChangingToplevel()
    {
        // the first page
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->setCondition('id > 10');
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');

        // another page with sublevel - does not override the first one saved
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->storeResult("use_stored", 'unittest-on-another-page', "sublevel");
        $db = $dbquery->getRecordset('id, name');

        // then back to the first page again - the result should be saved
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $_GET['use_stored'] = 'true';
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }
        $this->assertEquals(11, count($result));
    }

    function testUseStoreWithTwoDifferentUsers()
    {
        // the first page
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $dbquery->setCondition('id > 10');
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');

        // another user on the same page
        $dbquery = $this->createDBQuery();
        $dbquery->createStore('another-session-id', 'intranet_id = 1');
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');

        // then back to the first page again - the result should be saved
        $dbquery = $this->createDBQuery();
        $dbquery->createStore($this->session_id, 'intranet_id = 1');
        $_GET['use_stored'] = 'true';
        $dbquery->storeResult("use_stored", 'unittest', "toplevel");
        $db = $dbquery->getRecordset('id, name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }
        $this->assertEquals(11, count($result));
    }

    function testGetResultWithOneKeyword()
    {
        // actual not necesarry now
        $this->db->exec('INSERT INTO keyword SET keyword = "keyword1"');

        for ($i = 1; $i <= 10; $i++) {
            $this->db->exec('INSERT INTO keyword_x_object SET intranet_id = 1, belong_to = '.$i.', keyword_id = 1');
        }

        $dbquery = $this->createDBQuery();

        $dbquery->setKeyword(1);

        $db = $dbquery->getRecordset('dbquery_test.id, dbquery_test.name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }

        $this->assertEquals(10, count($result));

    }

    function testGetResultWithTwoKeywords()
    {
        // actual not necesarry now
        $this->db->exec('INSERT INTO keyword SET keyword = "keyword1"');
        $this->db->exec('INSERT INTO keyword SET keyword = "keyword2"');
        $this->db->exec('INSERT INTO keyword SET keyword = "keyword3"');


        for ($i = 3; $i <= 18; $i++) {
            $this->db->exec('INSERT INTO keyword_x_object SET intranet_id = 1, belong_to = '.$i.', keyword_id = 1');
            $this->db->exec('INSERT INTO keyword_x_object SET intranet_id = 1, belong_to = '.$i.', keyword_id = 3');

            if ($i <= 14) {
                $this->db->exec('INSERT INTO keyword_x_object SET intranet_id = 1, belong_to = '.$i.', keyword_id = 2');
            }
        }

        $dbquery = $this->createDBQuery();

        $dbquery->setKeyword(array(1, 2));

        $db = $dbquery->getRecordset('dbquery_test.id, dbquery_test.name');
        $i = 0;
        while ($db->nextRecord()) {
            $result[$i]['id'] = $db->f('id');
            $result[$i]['name'] = $db->f('name');
            $i++;
        }

        $this->assertEquals(12, count($result));
    }
}
