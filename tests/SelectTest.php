<?php

use PHPUnit\Framework\TestCase;
use Sy\Db\MySql\Select;
use Sy\Db\MySql\Where;

class SelectTest extends TestCase {

	public function testSelect() {
		$select = new Select([
			'SELECT' => ['one', 'two', 'three'],
			'FROM' => 'table',
		]);
		$this->assertEquals('SELECT `one`, `two`, `three` FROM table', $select->getSql());

		$select = new Select([
			'SELECT' => ['one' => 'col1', 'two' => 'col2', 'three' => 'col3'],
			'FROM' => 'table',
		]);
		$this->assertEquals('SELECT `one` AS `col1`, `two` AS `col2`, `three` AS `col3` FROM table', $select->getSql());
	}

	public function testFrom() {
		$select = new Select([
			'FROM' => ['table1', 'table2'],
		]);
		$this->assertEquals('SELECT * FROM `table1`, `table2`', $select->getSql());

		$select = new Select([
			'FROM' => ['db1.table1', 'db2.table2'],
		]);
		$this->assertEquals('SELECT * FROM `db1`.`table1`, `db2`.`table2`', $select->getSql());

		$select = new Select([
			'FROM' => ['db1.table1' => 'alias1', 'db2.table2' => 'alias2'],
		]);
		$this->assertEquals('SELECT * FROM `db1`.`table1` AS alias1, `db2`.`table2` AS alias2', $select->getSql());
	}

	public function testWhere() {
		$select = new Select([
			'FROM' => 'table',
			'WHERE' => 'id = 1',
		]);
		$this->assertEquals('SELECT * FROM table WHERE id = 1', $select->getSql());
		$this->assertEquals([], $select->getParams());

		$select = new Select([
			'FROM' => 'table',
			'WHERE' => ['id' => 1, 'status' => 'active'],
		]);
		$this->assertEquals('SELECT * FROM table WHERE `id` = ? AND `status` = ?', $select->getSql());
		$this->assertEquals([1, 'active'], $select->getParams());

		$select = new Select([
			'FROM' => 'table',
			'WHERE' => new Where(['id' => 1, 'status' => 'active']),
		]);
		$this->assertEquals('SELECT * FROM table WHERE `id` = ? AND `status` = ?', $select->getSql());
		$this->assertEquals([1, 'active'], $select->getParams());
	}

}