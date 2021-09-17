<?php
namespace Sy\Db\MySql;

use Psr\SimpleCache\CacheInterface;
use Sy\Db\Sql;
use Sy\Db\MySql\Select;
use Sy\Db\MySql\Where;

class Crud {

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var CacheInterface
	 */
	private $cache;

	/**
	 * @var Gate Database gateway
	 */
	protected $db;

	/**
	 * @param string $table
	 */
	public function __construct($table) {
		$this->table = $table;
		$this->cache = null;
		$this->db    = null;
	}

	/**
	 * Set database connection settings
	 *
	 * Example:
	 *
	 * $config = [
	 *     'host' => 'localhost',
	 *     'port' => '3306',
	 *     'dbname' => 'database_name',
	 *     'unix_socket' => '/tmp/mysql.sock', // should not be used with host/port
	 *     'charset' => 'utf8',
	 *     'username' => 'username',
	 *     'password' => 'password',
	 *     'options' => [
	 *         \PDO::MYSQL_ATTR_LOCAL_INFILE => true
	 *     ]
	 * ];
	 *
	 * @param array $config
	 * @throws ConfigException
	 * @return void
	 */
	public function setConfig(array $config) {
		if (is_null($this->db)) {
			$this->db = new Gate($config);
		} else {
			$this->db->setConfig($config);
		}
	}

	/**
	 * @param CacheInterface $cache
	 * @return void
	 */
	public function setCacheEngine($cache) {
		$this->cache = $cache;
	}

	/**
	 * Add a row with specified data.
	 *
	 * @param array $fields Column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function create(array $fields) {
		$res = $this->db->insert($this->table, $fields);

		// Clear cache
		$this->clearCache();

		return $res;
	}

	/**
	 * Add multiple rows with specified data.
	 *
	 * @param array $data array of array column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function createMany(array $data) {
		$res = $this->db->insertMany($this->table, $data);

		// Clear cache
		$this->clearCache();

		return $res;
	}

	/**
	 * Retrieve a row by primary key.
	 *
	 * @param array $pk Column-value pairs.
	 * @return array
	 */
	public function retrieve(array $pk) {
		$pk = array_map(fn($x) => strval($x), $pk);

		// Cache hit
		$hash = $this->getCache($this->getCacheKey('key', $pk));
		if (!empty($hash)) {
			$res = $this->getCache($this->getCacheKey('retrieve', $hash));
			if (!empty($res)) return $res;
		}

		// Cache miss
		$res = $this->db->queryOne(new Select([
			'FROM'  => $this->table,
			'WHERE' => $pk,
		]), \PDO::FETCH_ASSOC);
		if ($res === false) return [];

		$this->setCache($this->getCacheKey('key', $pk), md5(json_encode($res)));
		$this->setCache($this->getCacheKey('retrieve', md5(json_encode($res))), $res);
		return $res;
	}

	/**
	 * Return all rows.
	 *
	 * @param array $parameters Select parameters like: FROM, WHERE, LIMIT, OFFSET...
	 * @return array
	 */
	public function retrieveAll(array $parameters = []) {
		// Cache hit
		$key = $this->getCacheKey('retrieveAll', $parameters);
		$res = $this->getCache($key);
		if (!empty($res)) return $res;

		// Cache miss
		$parameters['FROM'] = $this->table;
		$res = $this->db->queryAll(new Select($parameters), \PDO::FETCH_ASSOC);
		$this->setCache($key, $res);
		return $res;
	}

	/**
	 * Return a PDOStatement in order to do an iteration.
	 *
	 * @param array $parameters Select parameters like: FROM, WHERE, LIMIT, OFFSET...
	 * @return \PDOStatement
	 */
	public function retrieveStatement(array $parameters = []) {
		$parameters['FROM'] = $this->table;
		$res = $this->db->query(new Select($parameters));
		return $res;
	}

	/**
	 * Update a row by primary key.
	 *
	 * @param array $pk Column-value pairs.
	 * @param array $bind Column-value pairs or array of string
	 * @return int The number of affected rows.
	 */
	public function update(array $pk, array $bind) {
		$pk = array_map(fn($x) => strval($x), $pk);
		$row = $this->retrieve($pk);

		$where = new Where($pk);
		if (array_values($bind) !== $bind) { // is assoc
			$s = array_map(function($k) use(&$bind) {
				if ($bind[$k] instanceof Expr) {
					$v = $bind[$k]->__toString();
					unset($bind[$k]);
					return '`' . implode('`.`', explode('.', $k)) . '` = ' . $v;
				} else {
					return '`' . implode('`.`', explode('.', $k)) . '` = ?';
				}
			}, array_keys($bind));
			$set = implode(',', $s);
		} else {
			$set = implode(',', $bind);
			$bind = [];
		}
		$sql = new Sql("
			UPDATE $this->table
			SET $set
			WHERE $where
		", array_merge(array_values($bind), $where->getParams()));
		$res = $this->db->execute($sql);

		// Clear cache
		$this->clearRowCache($row);

		return $res;
	}

	/**
	 * Delete a row by primary key.
	 *
	 * @param array $pk Column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function delete(array $pk) {
		$pk = array_map(fn($x) => strval($x), $pk);
		$row = $this->retrieve($pk);

		$where = new Where($pk);
		$sql = new Sql("DELETE FROM $this->table WHERE $where", $where->getParams());
		$res = $this->db->execute($sql);

		// Clear cache
		if ($row) $this->clearRowCache($row);

		return $res;
	}

	/**
	 * Insert or update a row with specified data.
	 *
	 * @param array $fields Column-value pairs.
	 * @param array $bind Column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function change(array $fields, array $bind = []) {
		$columns = array_keys($fields);
		$columns = '`' . implode('`,`', $columns) . '`';
		$values = array_values($fields);
		$v = array_fill(0, count($fields), '?');
		$v = implode(',', $v);

		$ignore = '';
		$action = '';
		$cache  = [];

		if (empty($bind)) {
			$ignore = 'IGNORE';
		} else {
			$s = array_map(function($k) use(&$bind) {
				if ($bind[$k] instanceof Expr) {
					$v = $bind[$k]->__toString();
					unset($bind[$k]);
					return '`' . implode('`.`', explode('.', $k)) . '` = ' . $v;
				} else {
					return '`' . implode('`.`', explode('.', $k)) . '` = ?';
				}
			}, array_keys($bind));
			$set = implode(',', $s);
			$action = "ON DUPLICATE KEY UPDATE $set";
			$cache = ['retrieve'];
		}

		$sql = new Sql("INSERT $ignore INTO $this->table ($columns) VALUES ($v) $action", array_merge($values, array_values($bind)));
		$res = $this->db->execute($sql);

		// Clear all cache
		$this->clearCache($cache);

		return $res;
	}

	/**
	 * Return row count.
	 *
	 * @param mixed $where array or string.
	 * @return int
	 */
	public function count($where = null) {
		$parameters['SELECT'] = 'count(*)';
		$parameters['FROM']   = $this->table;
		$parameters['WHERE']  = $where;
		$sql = new Select($parameters);
		$res = $this->db->queryOne($sql);
		return $res[0];
	}

	/**
	 * Return columns informations.
	 *
	 * @return array
	 */
	public function getColumns() {
		return $this->db->queryAll("SHOW FULL COLUMNS FROM $this->table");
	}

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 *
	 * @param string|null $name Name of the sequence object from which the ID should be returned.
	 * @return string
	 */
	public function lastInsertId($name = null) {
		return $this->db->getPdo()->lastInsertId($name);
	}

	/**
	 * Run a function as a transaction
	 *
	 * @param callable $fn
	 * @return mixed
	 * @throws Exception
	 */
	public function transaction($fn) {
		try {
			$this->db->beginTransaction();
			$res = call_user_func($fn);
			$this->db->commit();
			return $res;
		} catch(\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	protected function getCacheKey($label, $parameter = null) {
		if (is_array($parameter)) {
			ksort($parameter);
			$parameter = md5(json_encode($parameter));
		}
		return $label . (is_null($parameter) ? '' : '/' . $parameter);
	}

	protected function getCache($key) {
		if (is_null($this->cache)) return;
		return $this->cache->get('db/' . $this->table . '/' . $key);
	}

	protected function setCache($key, $value) {
		if (is_null($this->cache)) return;
		return $this->cache->set('db/' . $this->table . '/' . $key, $value);
	}

	public function clearCache(array $keys = []) {
		if (is_null($this->cache)) return;
		$this->cache->delete('db/' . $this->table . '/retrieveAll');
		foreach ($keys as $key) {
			$this->cache->delete('db/' . $this->table . '/' . $key);
		}
	}

	public function clearRowCache($row) {
		$this->clearCache([$this->getCacheKey('retrieve', md5(json_encode($row)))]);
	}

}