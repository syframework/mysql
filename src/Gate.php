<?php
namespace Sy\Db\MySql;

use \Sy\Db\PDOManager;
use \Sy\Db\Sql;

class Gate extends \Sy\Db\Gate {

	private $config;

	public function __construct(array $config = array()) {
		parent::__construct();
		$this->setConfig($config);
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
		if (empty($config)) return;

		$dsn = 'mysql:';

		if (isset($config['host'])) {
			$dsn .= 'host=' . $config['host'] . (isset($config['port']) ? ';port=' . $config['port'] : ''); 
		} elseif (isset($config['unix_socket'])) {
			$dsn .= 'unix_socket=' . $config['unix_socket'];
		} else {
			throw new ConfigException('Configuration settings error: host or unix_socket not defined');
		}

		$dsn .= isset($config['dbname']) ? ';dbname=' . $config['dbname'] : '';
		$dsn .= isset($config['charset']) ? ';charset=' . $config['charset'] : '';

		$username = isset($config['username']) ? $config['username'] : '';
		$password = isset($config['password']) ? $config['password'] : '';
		$options = isset($config['options']) ? $config['options'] : array();

		if (!isset($options[\PDO::MYSQL_ATTR_LOCAL_INFILE])) {
			$options[\PDO::MYSQL_ATTR_LOCAL_INFILE] = true;
		}

		$pdo = PDOManager::getPDOInstance($dsn, $username, $password, $options);

		$this->setPdo($pdo);
		$this->config = $config;
	}

	public function getFields($table) {
		$sql = "SHOW COLUMNS FROM $table";
		$res = $this->queryAll($sql);
		return array_map(function ($a) {
			return $a['Field'];
		}, $res);
	}

	/**
	 * Replace a table row with specified data.
	 * Replace is a MySQL extension to the SQL stantard.
	 *
	 * @param string $table The table name.
	 * @param array $bind Column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function replace($table, array $bind) {
		$columns = array_keys($bind);
		$columns = '`' . implode('`,`', $columns) . '`';
		$values = array_values($bind);
		$v = array_fill(0, count($bind), '?');
		$v = implode(',', $v);
		$sql = new Sql("REPLACE INTO $table ($columns) VALUES ($v)", $values);
		return $this->execute($sql);
	}

	/**
	 * Execute MySQL command load data local in file
	 *
	 * @param string $file File to load full path
	 * @param string $table Table name in which file must be loaded
	 * @param int $ignore Number of line to ignore at the beginning of the file
	 * @param string $fieldEnd Field must be terminated by this string
	 * @param char $fieldEnclose Field must be enclosed by this character
	 * @param string $lineStart Line must start with this string
	 * @param string $lineEnd Line must end with this string
	 * @return int Return execution status. See PHP exec function
	 */
	public function loadDataLocal($file, $table, $ignore = 0, $fieldEnd = ',', $fieldEnclose = '"', $lineStart = '', $lineEnd = '\n') {
		$sql = "
			LOAD DATA LOCAL INFILE '$file'
			INTO TABLE `$table`
			FIELDS
				TERMINATED BY '$fieldEnd'
				ENCLOSED BY '$fieldEnclose'
			LINES
				STARTING BY '$lineStart'
				TERMINATED BY '$lineEnd'
			IGNORE $ignore LINES;
		";
		$filename = tempnam('/tmp', 'my');
		file_put_contents($filename, $sql);
		exec('mysql --local-infile ' . $this->getCmdOptions() . ' -s < ' . $filename, $output, $return);
		unlink($filename);
		return $return;
	}

	/**
	 * Execute MySQL command to output select query in a file
	 * Be careful the field separator may cause output issue
	 *
	 * @param string $select SQL SELECT query
	 * @param string $outfile Output file full path
	 * @param string $fieldSeparator Field separator
	 * @param string $columnNames Write column names in results
	 * @return int Return execution status. See PHP exec function
	 */
	public function selectOutfile($select, $outfile, $fieldSeparator = ';', $columnNames = false) {
		$filename = tempnam('/tmp', 'my');
		file_put_contents($filename, $select);
		$silent = $columnNames ? '' : '-s';
		exec('mysql ' . $this->getCmdOptions() . " $silent -C < $filename | sed 's/\t/$fieldSeparator/g' > $outfile", $output, $return);
		unlink($filename);
		return $return;
	}

	/**
	 * Insert multiple rows in a table with specified data.
	 *
	 * @param string $table The table name.
	 * @param array $data array of array column-value pairs.
	 * @return int The number of affected rows.
	 * @throws \Sy\Db\ExecuteException
	 */
	public function insertMany($table, array $data) {
		$data = array_map(function($array) {
			return array_filter($array, 'strlen');
		}, $data);
		if (empty($data)) throw new \Sy\Db\Exception('Insert data is empty');
		$columns = '`' . implode('`,`', array_keys($data[0])) . '`';
		$values = [];
		array_walk_recursive($data, function($item) use (&$values) {
			$values[] = $item;
		});
		$r = '(' . rtrim(str_repeat('?,', count($data[0])), ',') . '),';
		$v = rtrim(str_repeat($r, count($data)), ',');
		$sql = new Sql("INSERT IGNORE INTO $table ($columns) VALUES $v", $values);
		return $this->execute($sql);
	}

	/**
	 * Return mysql command line options
	 *
	 * @throws ConfigException
	 * @return string
	 */
	private function getCmdOptions() {
		$config = $this->config;
		if (empty($config)) {
			throw new ConfigException('Configuration settings undefined');
		}

		$cmd = '';
		if (isset($config['host'])) {
			$cmd .= '-h ' . $config['host'] . (isset($config['port']) ? ' -P ' . $config['port'] : ''); 
		} elseif (isset($config['unix_socket'])) {
			$cmd .= '-S ' . $config['unix_socket'];
		} else {
			throw new ConfigException('Configuration settings error: host or unix_socket not defined');
		}

		$cmd .= isset($config['username']) ? ' -u ' . $config['username'] : '';
		$cmd .= isset($config['password']) ? ' -p ' . $config['password'] : '';
		$cmd .= isset($config['dbname']) ? ' -D ' . $config['dbname'] : '';

		return $cmd;
	}

}

class ConfigException extends \Sy\Db\Exception {}