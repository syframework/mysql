<?php
namespace Sy\Db\MySql;

class Expr {

	/**
	 * @var string
	 */
	private $expression;

	public function __construct($expression) {
		$this->expression = $expression;
	}

	public function __toString() {
		return $this->expression;
	}

}
