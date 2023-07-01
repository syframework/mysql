<?php

use PHPUnit\Framework\TestCase;
use Sy\Db\MySql\ConfigException;
use Sy\Db\MySql\Gate;

class GateTest extends TestCase {

	public function testSetConfig() {
		$this->expectException(ConfigException::class);
		new Gate(['hello' => 'world']);
	}

}