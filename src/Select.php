<?php
namespace Sy\Db\MySql;

class Select extends \Sy\Db\Sql {

	private $sql;

	private $params;

	public function __construct(array $parameters) {
		$this->params = array();
		$this->sql = new \Sy\Template\PhpTemplate();
		$this->sql->setFile(__DIR__ . '/Select.tpl', 'php');
		$this->buildSql($parameters);
		parent::__construct($this->sql->getRender(), $this->params);
	}

	private function buildSql(array $parameters) {
		$select = empty($parameters['SELECT']) ? '*' : $parameters['SELECT'];
		$this->buildSelect($select);
		if (!empty($parameters['FROM'])) {
			$this->buildFrom($parameters['FROM']);
		}
		if (!empty($parameters['JOIN'])) {
			$this->buildJoin($parameters['JOIN']);
		}
		if (!empty($parameters['WHERE'])) {
			$this->buildWhere($parameters['WHERE']);
		}
		if (!empty($parameters['GROUP BY'])) {
			$this->buildGroupBy($parameters['GROUP BY']);
		}
		if (!empty($parameters['HAVING'])) {
			$this->buildHaving($parameters['HAVING']);
		}
		if (!empty($parameters['ORDER BY'])) {
			$this->buildOrderBy($parameters['ORDER BY']);
		}
		if (!empty($parameters['LIMIT'])) {
			$this->sql->setVar('LIMIT', $parameters['LIMIT']);
			if (!empty($parameters['OFFSET'])) {
				$this->sql->setVar('OFFSET', $parameters['OFFSET']);
			}
		}
	}

	private function buildSelect($select) {
		if (is_array($select)) {
			if (array_values($select) !== $select) { // is assoc
				$tmp = [];
				foreach ($select as $k => $v) {
					$tmp[] = $this->formatKey($k) . " AS `$v`";
				}
				$select = implode(', ', $tmp);
			} else {
				$select = implode(', ', array_map([$this, 'formatKey'], $select));
			}
		}
		$this->sql->setVar('SELECT', $select);
	}

	private function buildFrom($from) {
		if (is_array($from)) {
			if (array_values($from) !== $from) { // is assoc
				$tmp = [];
				foreach ($from as $k => $v) {
					$tmp[] = $this->formatKey($k) . " AS $v";
				}
				$from = implode(', ', $tmp);
			} else {
				$from = implode(', ', array_map([$this, 'formatKey'], $from));
			}
		}
		$this->sql->setVar('FROM', $from);
	}

	private function buildJoin($join) {
		$this->sql->setVar('JOIN', $join);
	}

	private function buildWhere($where) {
		$w = new Where($where);
		$this->params = $w->getParams();
		$this->sql->setVar('WHERE', $w);
	}

	private function buildGroupBy($groupBy) {
		$this->sql->setVar('GROUP_BY', $groupBy);
	}

	private function buildHaving($having) {
		$this->sql->setVar('HAVING', $having);
	}

	private function buildOrderBy($orderBy) {
		$this->sql->setVar('ORDER_BY', $orderBy);
	}

	private function formatKey($k) {
		return '`' . implode('`.`', array_map(function($x) { return trim($x, "\"'`"); }, explode('.', $k))) . '`';
	}

}