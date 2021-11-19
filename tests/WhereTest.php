<?php

use PHPUnit\Framework\TestCase;
use Sy\Db\MySql\Where;

class WhereTest extends TestCase {

	public function testStringParam() {
		$where = new Where("foo = 'bar'");
		$this->assertEquals("foo = 'bar'", $where->__toString());
		$this->assertEquals([], $where->getParams());
	}

	public function testArrayOfStringParam() {
		$where = new Where(["a = 'one'", "b = 'two'", "c = 'three'"]);
		$this->assertEquals("a = 'one' AND b = 'two' AND c = 'three'", $where->__toString());
		$this->assertEquals([], $where->getParams());
	}

	public function testAssocArrayParam() {
		$where = new Where(['t.k1' => 'v1', 't.k2' => 'v2']);
		$this->assertEquals("`t`.`k1` = ? AND `t`.`k2` = ?", $where->__toString());
		$this->assertEquals(['v1', 'v2'], $where->getParams());
	}

	public function testAssocArrayParamNullValue() {
		$where = new Where(['c' => null]);
		$this->assertEquals("`c` IS NULL", $where->__toString());
		$this->assertEquals([], $where->getParams());

		$where = new Where(['a' => 'b', 'c' => null]);
		$this->assertEquals("`a` = ? AND `c` IS NULL", $where->__toString());
		$this->assertEquals(['b'], $where->getParams());
	}

	public function testAssocArrayParamArrayValue() {
		$where = new Where(['c' => ['one', 'two', 'three']]);
		$this->assertEquals("`c` IN (?,?,?)", $where->__toString());
		$this->assertEquals(['one', 'two', 'three'], $where->getParams());
	}

	public function testAssocArrayParamAssocArrayValue() {
		$where = new Where(['c' => ['LIKE' => "'foo%'"]]);
		$this->assertEquals("`c` LIKE 'foo%'", $where->__toString());
		$this->assertEquals([], $where->getParams());
	}

	public function testAssocArrayParamEmptyArrayValue() {
		$where = new Where(['c' => []]);
		$this->assertEquals("1", $where->__toString());
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
			'd' => []
		]);
		$this->assertEquals("foo = 'bar' AND `t`.`k1` = ? AND `t`.`k2` = ? AND `a` IS NULL AND `b` IN (?,?,?) AND `c` LIKE 'foo%' AND 1", $where->__toString());
		$this->assertEquals(['v1', 'v2', 'one', 'two', 'three'], $where->getParams());
	}

	public function testFormatKey() {
		$where = new Where(["'t'.'k1'" => 'v1', '"t"."k2"' => 'v2', '`t`.`k3`' => 'v3']);
		$this->assertEquals("`t`.`k1` = ? AND `t`.`k2` = ? AND `t`.`k3` = ?", $where->__toString());
		$this->assertEquals(['v1', 'v2', 'v3'], $where->getParams());
	}

}