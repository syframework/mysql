<?php
namespace Sy\Db\MySql;

class Select extends \Sy\Db\Sql {

	/**
	 * @var \Sy\Template\ITemplate
	 */
	private $sql;

	/**
	 * @var array
	 */
	private $params;

	/**
	 * @param array $parameters
	 */
	public function __construct(array $parameters) {
		$this->params = array();
		$this->sql = new \Sy\Template\PhpTemplate();
		$this->sql->setFile(__DIR__ . '/Select.tpl');
		$this->buildSql($parameters);
		parent::__construct($this->sql->getRender(), $this->params);
	}

	/**
	 * @param array $parameters
	 */
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

	/**
	 * @param string|array $select
	 */
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

	/**
	 * @param string|array $from
	 */
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

	/**
	 * @param string $join
	 */
	private function buildJoin($join) {
		$this->sql->setVar('JOIN', $join);
	}

	/**
	 * @param string|array|Where $where
	 */
	private function buildWhere($where) {
		if (!($where instanceof Where)) {
			$where = new Where($where);
		}
		$this->params = $where->getParams();
		$this->sql->setVar('WHERE', strval($where));
	}

	/**
	 * @param string $groupBy
	 */
	private function buildGroupBy($groupBy) {
		$this->sql->setVar('GROUP_BY', $groupBy);
	}

	/**
	 * @param string $having
	 */
	private function buildHaving($having) {
		$this->sql->setVar('HAVING', $having);
	}

	/**
	 * @param string $orderBy
	 */
	private function buildOrderBy($orderBy) {
		$this->sql->setVar('ORDER_BY', $orderBy);
	}

	/**
	 * @param string $k
	 */
	private function formatKey($k) {
		return '`' . implode('`.`', array_map(function($x) { return trim($x, "\"'`"); }, explode('.', $k))) . '`';
	}

}