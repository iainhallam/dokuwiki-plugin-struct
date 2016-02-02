<?php

use plugin\struct\meta\SchemaData;
use plugin\struct\meta\SchemaBuilder;
use plugin\struct\meta\Schema;

/**
 * Tests to the DB for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class schemaDataDB_struct_test extends DokuWikiTest {

    protected $pluginsEnabled = array('struct', 'sqlite',);

    public function setUp() {
        parent::setUp();

        $testdata = array();
        $testdata['new']['new1']['sort'] = 70;
        $testdata['new']['new1']['label'] = 'testcolumn';
        $testdata['new']['new1']['ismulti'] = 0;
        $testdata['new']['new1']['config'] = '{"prefix": "", "postfix": ""}';
        $testdata['new']['new1']['class'] = 'Text';
        $testdata['new']['new2']['sort'] = 40;
        $testdata['new']['new2']['label'] = 'testMulitColumn';
        $testdata['new']['new2']['ismulti'] = 1;
        $testdata['new']['new2']['config'] = '{"prefix": "", "postfix": ""}';
        $testdata['new']['new2']['class'] = 'Text';

        $testname = 'testTable';
        $testname = Schema::cleanTableName($testname);

        $builder = new SchemaBuilder($testname, $testdata);
        $builder->build();

        /** @var helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $sqlite = $sqlite->getDB();

        // revision 1
        $sqlite->query("INSERT INTO data_testtable (pid, rev, col1) VALUES (?,?,?)", array('testpage', 123, 'value1',));
        $sqlite->query("INSERT INTO multivals (tbl, colref, pid, rev, row, value) VALUES (?,?,?,?,?,?)",
                       array('data_testtable',2,'testpage',123,1,'value2.1',));
        $sqlite->query("INSERT INTO multivals (tbl, colref, pid, rev, row, value) VALUES (?,?,?,?,?,?)",
                       array('data_testtable',2,'testpage',123,2,'value2.2',));


        // revision 2
        $sqlite->query("INSERT INTO data_testtable (pid, rev, col1) VALUES (?,?,?)", array('testpage', 789, 'value1a',));
        $sqlite->query("INSERT INTO multivals (tbl, colref, pid, rev, row, value) VALUES (?,?,?,?,?,?)",
                       array('data_testtable',2,'testpage',789,1,'value2.1a',));
        $sqlite->query("INSERT INTO multivals (tbl, colref, pid, rev, row, value) VALUES (?,?,?,?,?,?)",
                       array('data_testtable',2,'testpage',789,2,'value2.2a',));

    }

    public function tearDown() {
        parent::tearDown();

        /** @var helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $sqlite = $sqlite->getDB();

        $res = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = $sqlite->res2arr($res);
        $tableNames = array_map(function ($value) { return $value['name'];},$tableNames);
        $sqlite->res_close($res);

        foreach ($tableNames as $tableName) {
            if ($tableName == 'opts') continue;
            $sqlite->query('DROP TABLE ?;', $tableName);
        }

        $sqlite->query("CREATE TABLE schema_assignments ( assign NOT NULL, tbl NOT NULL, PRIMARY KEY(assign, tbl) );");
        $sqlite->query("CREATE TABLE schema_cols ( sid INTEGER REFERENCES schemas (id), colref INTEGER NOT NULL, enabled BOOLEAN DEFAULT 1, tid INTEGER REFERENCES types (id), sort INTEGER NOT NULL, PRIMARY KEY ( sid, colref) )");
        $sqlite->query("CREATE TABLE schemas ( id INTEGER PRIMARY KEY AUTOINCREMENT, tbl NOT NULL, ts INT NOT NULL, chksum DEFAULT '' )");
        $sqlite->query("CREATE TABLE sqlite_sequence(name,seq)");
        $sqlite->query("CREATE TABLE types ( id INTEGER PRIMARY KEY AUTOINCREMENT, class NOT NULL, ismulti BOOLEAN DEFAULT 0, label DEFAULT '', config DEFAULT '' )");
        $sqlite->query("CREATE TABLE multivals ( tbl NOT NULL, colref INTEGER NOT NULL, pid NOT NULL, rev INTEGER NOT NULL, row INTEGER NOT NULL, value, PRIMARY KEY(tbl, colref, pid, rev, row) )");
    }

    public function test_getDataFromDB_currentRev() {

        // act
        $schemaData = new SchemaData('testtable','testpage', "");
        $schemaData->setCorrectTimestamp();
        $actual_data =  $schemaData->getDataFromDB();

        $expected_data = array(
            array(
                'col1' => 'value1a',
                'col2' => 'value2.1a',
            ),
            array(
                'col1' => 'value1a',
                'col2' => 'value2.2a',
            ),
        );


        $this->assertEquals($expected_data, $actual_data , '');
    }

    public function test_getDataFromDB_oldRev() {

        // act
        $schemaData = new SchemaData('testtable','testpage','');
        $schemaData->setCorrectTimestamp(200);
        $actual_data = $schemaData->getDataFromDB();

        $expected_data = array(
            array(
                'col1' => 'value1',
                'col2' => 'value2.1',
            ),
            array(
                'col1' => 'value1',
                'col2' => 'value2.2',
            ),
        );

        $this->assertEquals($expected_data, $actual_data , '');
    }

    public function test_getData_currentRev() {

        // act
        $schemaData = new SchemaData('testtable','testpage', "");
        $schemaData->setCorrectTimestamp();
        $actual_data = $schemaData->getData();

        $expected_data = array(
            'testMulitColumn' => array('value2.1a', 'value2.2a'),
            'testcolumn' => 'value1a',
        );

        // assert
        $this->assertEquals($expected_data, $actual_data , '');
    }

    public function test_getData_oldRev() {

        // act
        $schemaData = new SchemaData('testtable','testpage','');
        $schemaData->setCorrectTimestamp(200);
        $actual_data = $schemaData->getData();

        $expected_data = array(
            'testMulitColumn' => array('value2.1', 'value2.2'),
            'testcolumn' => 'value1',
        );

        // assert
        $this->assertEquals($expected_data, $actual_data , '');
    }
}
