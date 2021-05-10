<?php
namespace Sy\Db\MySql;

/**
 * Constructor parameter can be a string, a simple array or an associative array
 * string: "foo = 'bar'" return "foo = 'bar'"
 * array: same as string just implode with AND
 * assoc array: ['t.k1' => 'v1', 't.k2' => 'v2'] return "`t`.`k1` = ? AND `t`.`k2` = ?"
 * - null value: ['c' => null] return "`c` IS NULL"
 * - array value: ['c' => ['one', 'two', 'three']] return "`c` IN (?,?,?)"
 * - assoc array value: ['c' => ['LIKE' => "'foo%'"]] return "`c` LIKE 'foo%'"
 * - empty array: ['c' => []] return "1"
 */
class Where {

	private $where;

	private $params;

	public function __construct($where) {
		$this->where = $where;
		$this->params = [];
		$this->init();
	}

	public function __toString() {
		return $this->where;
	}

	public function getParams() {
		return $this->params;
	}

	private function init() {
		$where = $this->where;
		if (is_array($where)) {
			$w = array_map(function($k) use($where) {
				if (is_string($k)) {
					if (is_null($where[$k])) {
						return $this->formatKey($k) . " IS NULL";
					} elseif (is_array($where[$k])) {
						return $this->formatArray($k, $where[$k]);
					}
					$this->params[] = $where[$k];
					return $this->formatKey($k) . " = ?";
				} else {
					return $where[$k];
				}
			}, array_keys($where));
			$where = implode(' AND ', $w);
		}
		$this->where = $where;
	}

	private function formatKey($k) {
		return '`' . implode('`.`', explode('.', $k)) . '`';
	}

	private function formatArray($k, array $v) {
		if (array_values($v) !== $v) { // is assoc
			return $this->formatKey($k) . ' ' . key($v) . ' ' . $this->formatString(current($v));
		} elseif (empty($v)) {
			return '1';
		} else {
			$this->params = array_merge($this->params, $v);
			$placeholders = implode(',', array_fill(0, count($v), '?'));
			return $this->formatKey($k) . " IN ($placeholders)";
		}
	}

	private function formatString($s) {
		if (!is_string($s)) return $s;
		return "'" . trim($s, "'") . "'";
	}

}