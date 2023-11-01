<?php
namespace Sy\Db\MySql;

use Psr\SimpleCache\CacheInterface;
use Sy\Db\Sql;
use Sy\Db\MySql\Select;
use Sy\Db\MySql\Where;

class Crud {

	/**
	 * @var Gate Database gateway
	 */
	protected $db;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var CacheInterface
	 */
	private $cache;

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
	 * @param  array $config
	 * @throws ConfigException
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
	 */
	public function setCacheEngine($cache) {
		$this->cache = $cache;
	}

	/**
	 * @param Gate $db
	 */
	public function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Add a row with specified data.
	 *
	 * @param  array $fields Column-value pairs.
	 * @return string|false The last inserted id.
	 * @throws Exception
	 */
	public function create(array $fields) {
		try {
			$this->db->insert($this->table, $fields);
			$this->clearCache();
			return $this->lastInsertId();
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Add multiple rows with specified data.
	 *
	 * @param  array $data array of array column-value pairs.
	 * @return int The number of affected rows.
	 * @throws Exception
	 */
	public function createMany(array $data) {
		try {
			$res = $this->db->insertMany($this->table, $data);
			$this->clearCache();
			return $res;
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Retrieve a row by primary key.
	 *
	 * @param  array $pk Column-value pairs.
	 * @return array
	 * @throws Exception
	 */
	public function retrieve(array $pk) {
		try {
			return $this->executeRetrieve($pk, new Select([
				'FROM'  => $this->table,
				'WHERE' => $pk,
			]));
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Return all rows.
	 *
	 * @param  array $parameters Select parameters like: FROM, WHERE, LIMIT, OFFSET...
	 * @return array
	 * @throws Exception
	 */
	public function retrieveAll(array $parameters = []) {
		try {
			// Cache hit
			$key = $this->getCacheKey('retrieveAll', $parameters);
			$res = $this->getCache($key);
			if (!empty($res)) return $res;

			// Cache miss
			$parameters['FROM'] = $this->table;
			$res = $this->db->queryAll(new Select($parameters), \PDO::FETCH_ASSOC);
			$this->setCache($key, $res);
			return $res;
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Return a PDOStatement in order to do an iteration.
	 *
	 * @param  array $parameters Select parameters like: FROM, WHERE, LIMIT, OFFSET...
	 * @return \PDOStatement
	 * @throws Exception
	 */
	public function retrieveStatement(array $parameters = []) {
		try {
			$parameters['FROM'] = $this->table;
			$res = $this->db->query(new Select($parameters));
			return $res;
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Update a row by primary key.
	 *
	 * @param  array $pk Column-value pairs.
	 * @param  array $bind Column-value pairs or array of string
	 * @return int The number of affected rows.
	 * @throws Exception
	 */
	public function update(array $pk, array $bind) {
		try {
			$pk = array_map(fn($x) => is_scalar($x) || (is_object($x) && method_exists($x, '__toString')) ? strval($x) : $x, $pk);
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
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Delete a row by primary key.
	 *
	 * @param  array $pk Column-value pairs.
	 * @return int The number of affected rows.
	 * @throws Exception
	 */
	public function delete(array $pk) {
		try {
			$pk = array_map(fn($x) => is_scalar($x) || (is_object($x) && method_exists($x, '__toString')) ? strval($x) : $x, $pk);
			$row = $this->retrieve($pk);

			$where = new Where($pk);
			$sql = new Sql("DELETE FROM $this->table WHERE $where", $where->getParams());
			$res = $this->db->execute($sql);

			// Clear cache
			if ($row) $this->clearRowCache($row);

			return $res;
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Insert or update a row with specified data.
	 *
	 * @param  array $fields Column-value pairs.
	 * @param  array $bind Column-value pairs.
	 * @return int The number of affected rows.
	 * @throws Exception
	 */
	public function change(array $fields, array $bind = []) {
		try {
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
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Return row count.
	 *
	 * @param  mixed $where array or string.
	 * @return int
	 * @throws Exception
	 */
	public function count($where = null) {
		try {
			$parameters['SELECT'] = 'count(*)';
			$parameters['FROM']   = $this->table;
			$parameters['WHERE']  = $where;
			$sql = new Select($parameters);
			$res = $this->db->queryOne($sql);
			return $res[0];
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Return columns informations.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getColumns() {
		try {
			return $this->db->queryAll("SHOW FULL COLUMNS FROM $this->table");
		} catch (\Sy\Db\Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 *
	 * @param  string|null $name Name of the sequence object from which the ID should be returned.
	 * @return string
	 */
	public function lastInsertId($name = null) {
		return $this->db->getPdo()->lastInsertId($name);
	}

	/**
	 * Run a function as a transaction
	 *
	 * @param  callable $fn
	 * @return mixed
	 * @throws \Exception
	 */
	public function transaction($fn) {
		try {
			$this->db->beginTransaction();
			$res = call_user_func($fn);
			$this->db->commit();
			return $res;
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
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

	/**
	 * Retrieve one row using the sql query in parameter
	 * Will use the cache engine
	 *
	 * @param  array $pk
	 * @param  Sql $sql
	 * @return array
	 */
	protected function executeRetrieve(array $pk, Sql $sql) {
		$pk = array_map(fn($x) => is_scalar($x) || (is_object($x) && method_exists($x, '__toString')) ? strval($x) : $x, $pk);

		// Cache hit
		$hash = $this->getCache($this->getCacheKey('key', $pk));
		if (!empty($hash)) {
			$res = $this->getCache($this->getCacheKey('retrieve', $hash));
			if (!empty($res)) return $res;
		}

		// Cache miss
		$res = $this->db->queryOne($sql, \PDO::FETCH_ASSOC);
		if ($res === false) return [];

		$this->setCache($this->getCacheKey('key', $pk), md5(json_encode($res)));
		$this->setCache($this->getCacheKey('retrieve', md5(json_encode($res))), $res);
		return $res;
	}

	/**
	 * Retrieve rows using the sql query in parameter
	 * Will use the cache engine
	 *
	 * @param  array $parameters
	 * @param  Sql $sql
	 * @return array
	 */
	protected function executeRetrieveAll(array $parameters, Sql $sql) {
		// Cache hit
		$key = $this->getCacheKey('retrieveAll', $parameters);
		$res = $this->getCache($key);
		if (!empty($res)) return $res;

		// Cache miss
		$parameters['FROM'] = $this->table;
		$res = $this->db->queryAll($sql, \PDO::FETCH_ASSOC);
		$this->setCache($key, $res);
		return $res;
	}

	/**
	 * @param string $label
	 * @param array|null $parameter
	 */
	protected function getCacheKey($label, $parameter = null) {
		if (is_array($parameter)) {
			ksort($parameter);
			$parameter = md5(json_encode($parameter));
		}
		return $label . (is_null($parameter) ? '' : '/' . $parameter);
	}

	/**
	 * @param string $key
	 */
	protected function getCache($key) {
		if (is_null($this->cache)) return;
		return $this->cache->get('db/' . $this->table . '/' . $key);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	protected function setCache($key, $value) {
		if (is_null($this->cache)) return;
		return $this->cache->set('db/' . $this->table . '/' . $key, $value);
	}

	/**
	 * @param \Sy\Db\Exception $e
	 * @throws Exception
	 */
	protected function handleException(\Sy\Db\Exception $e) {
		switch ($e->getCode()) {
			case 1062:
				throw new DuplicateEntryException($e->getMessage(), $e->getCode(), $e);

			default:
				throw new Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

}