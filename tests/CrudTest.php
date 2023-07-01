<?php

use PHPUnit\Framework\TestCase;
use Sy\Db\MySql\Gate;
use Sy\Db\MySql\Crud;

class CrudTest extends TestCase {

	private $crud;

	private $gate;

	protected function setUp(): void {
		$gate = new Gate([
			'host'     => '127.0.0.1',
			'port'     => '3333',
			'dbname'   => 'sytest',
			'username' => 'root',
			'password' => 'password',
		]);
		$gate->execute('
			CREATE TABLE t_user (
				id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				uuid BINARY(16) NOT NULL DEFAULT (UUID_TO_BIN(UUID(), 1)),
				firstname TEXT NOT NULL,
				lastname TEXT NOT NULL,
				number INT NULL,
				flag BOOL NULL,
				UNIQUE INDEX `uuid`(`uuid` ASC)
			)
		');
		$gate->insert('t_user', ['firstname' => 'John', 'lastname' => 'Doe']);
		$gate->insert('t_user', ['firstname' => 'Jane', 'lastname' => 'Doe']);
		$gate->insert('t_user', ['firstname' => 'John', 'lastname' => 'Wick']);
		$this->crud = new Crud('t_user');
		$this->crud->setDb($gate);
		$this->gate = $gate;
	}

	protected function tearDown(): void {
		$this->gate->execute('DROP TABLE t_user');
	}

	public function testCreate() {
		$id = $this->crud->create(['firstname' => 'Peter', 'lastname' => 'Parker']);
		$res = $this->crud->retrieve(['id' => 4]);
		$this->assertEquals(4, $id);
		$this->assertEquals('Peter', $res['firstname']);
	}

	public function testCreateMany() {
		$this->crud->createMany([
			['firstname' => 'Gwen', 'lastname' => 'Stacy'],
			['firstname' => 'Miles', 'lastname' => 'Morales'],
		]);
		$res = $this->crud->retrieve(['id' => 5]);
		$this->assertEquals('Miles', $res['firstname']);
	}

	public function testRetrieve() {
		$res = $this->crud->retrieve(['id' => 2]);
		$this->assertEquals('Jane', $res['firstname']);
	}

	public function testRetrieveAll() {
		$res = $this->crud->retrieveAll();
		$this->assertEquals(3, count($res));
	}

	public function testChange() {
		$res = $this->crud->retrieve(['id' => 1]);
		$this->assertEquals('John', $res['firstname']);
		$this->assertEquals('Doe', $res['lastname']);

		$this->crud->change(['id' => 1, 'firstname' => 'Jim', 'lastname' => 'Doe']);
		$res = $this->crud->retrieve(['id' => 1]);
		$this->assertEquals('John', $res['firstname']);

		$this->crud->change(['id' => 1, 'firstname' => 'Jim', 'lastname' => 'Doe'], ['firstname' => 'Jim']);
		$res = $this->crud->retrieve(['id' => 1]);
		$this->assertEquals('Jim', $res['firstname']);

		$this->crud->change(['uuid' => $res['uuid'], 'firstname' => 'Jane', 'lastname' => 'Doe'], ['firstname' => 'Jane']);
		$res = $this->crud->retrieve(['id' => 1]);
		$this->assertEquals('Jane', $res['firstname']);
	}

	public function testCount() {
		$res = $this->crud->Count();
		$this->assertEquals(3, $res);
	}

	public function testGetColumns() {
		$res = $this->crud->getColumns();
		$this->assertEquals(6, count($res));
		$this->assertEquals('id'       , $res[0]['Field']);
		$this->assertEquals('uuid'     , $res[1]['Field']);
		$this->assertEquals('firstname', $res[2]['Field']);
		$this->assertEquals('lastname' , $res[3]['Field']);
		$this->assertEquals('number'   , $res[4]['Field']);
		$this->assertEquals('flag'     , $res[5]['Field']);
	}

}