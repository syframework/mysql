<?php

use PHPUnit\Framework\TestCase;
use Sy\Db\MySql\Where;

class WhereTest extends TestCase {

	public function testStringParam() {
		$where = new Where("foo = 'bar'");
		$this->assertEquals("foo = 'bar'", strval($where));
		$this->assertEquals([], $where->getParams());
	}

	public function testArrayOfStringParam() {
		$where = new Where(["a = 'one'", "b = 'two'", "c = 'three'"]);
		$this->assertEquals("a = 'one' AND b = 'two' AND c = 'three'", strval($where));
		$this->assertEquals([], $where->getParams());
	}

	public function testAssocArrayParam() {
		$where = new Where(['t.k1' => 'v1', 't.k2' => 'v2']);
		$this->assertEquals("`t`.`k1` = ? AND `t`.`k2` = ?", strval($where));
		$this->assertEquals(['v1', 'v2'], $where->getParams());
	}

	public function testAssocArrayParamNullValue() {
		$where = new Where(['c' => null]);
		$this->assertEquals("`c` IS NULL", strval($where));
		$this->assertEquals([], $where->getParams());

		$where = new Where(['a' => 'b', 'c' => null]);
		$this->assertEquals("`a` = ? AND `c` IS NULL", strval($where));
		$this->assertEquals(['b'], $where->getParams());
	}

	public function testAssocArrayParamArrayValue() {
		$where = new Where(['c' => ['one', 'two', 'three']]);
		$this->assertEquals("`c` IN (?,?,?)", strval($where));
		$this->assertEquals(['one', 'two', 'three'], $where->getParams());
	}

	public function testAssocArrayParamAssocArrayValue() {
		$where = new Where(['c' => ['LIKE' => "'foo%'"]]);
		$this->assertEquals("`c` LIKE 'foo%'", strval($where));
		$this->assertEquals([], $where->getParams());
	}

	public function testAssocArrayParamEmptyArrayValue() {
		$where = new Where(['c' => []]);
		$this->assertEquals("1", strval($where));
		$this->assertEquals([], $where->getParams());
	}

	public function testMixedArrayParam() {
		$where = new Where([
			"foo = 'bar'",
			't.k1' => 'v1',
			't.k2' => 'v2',
			'a' => null,
			'b' => ['one', 'two', 'three'],
			'c' => ['LIKE' => "'foo%'"],
			'd' => [],
		]);
		$this->assertEquals("foo = 'bar' AND `t`.`k1` = ? AND `t`.`k2` = ? AND `a` IS NULL AND `b` IN (?,?,?) AND `c` LIKE 'foo%' AND 1", strval($where));
		$this->assertEquals(['v1', 'v2', 'one', 'two', 'three'], $where->getParams());
	}

	public function testFormatKey() {
		$where = new Where(["'t'.'k1'" => 'v1', '"t"."k2"' => 'v2', '`t`.`k3`' => 'v3']);
		$this->assertEquals("`t`.`k1` = ? AND `t`.`k2` = ? AND `t`.`k3` = ?", strval($where));
		$this->assertEquals(['v1', 'v2', 'v3'], $where->getParams());
	}

	public function testParamArgument() {
		$where = new Where('id = uuid_to_bin(?, 1)', ['123456']);
		$this->assertEquals('id = uuid_to_bin(?, 1)', strval($where));
		$this->assertEquals(['123456'], $where->getParams());

		$where = new Where(['id = uuid_to_bin(?, 1)', 't.k1' => 'v1'], ['123456']);
		$this->assertEquals('id = uuid_to_bin(?, 1) AND `t`.`k1` = ?', strval($where));
		$this->assertEquals(['123456', 'v1'], $where->getParams());
	}

}