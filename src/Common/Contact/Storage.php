<?php
namespace Common\Contact;

use PDO;
use PDOStatement;
use Throwable;
class Storage
{
	public $pdo = NULL;
	public $config = [];
	/**
	 * @param array $config : /src/config/config.php => 'STORAGE'
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
		$dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'];
		$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
		try {
			$this->pdo = new PDO($dsn, $config['db_user'], $config['db_pwd'], $opts);
		} catch (Throwable $t) {
			error_log(__METHOD__ . ':' . $t->getMessage());
		}
	}
	/**
	 * Stores info into $table
	 *
	 * @param string $table : table name
	 * @param array $inputs : usually from $_POST
	 * @return bool
	 */
	public function save(string $table, array $inputs)
	{
		$result = FALSE;
		$inputs = $this->filter($table, $inputs);
		$fields = array_keys($this->config['tables'][$table]);
		$sql = 'INSERT INTO ' . $table . ' (`'
			 . implode('`,`', $fields)
			 . '`) VALUES (:'
			 . implode(',:', $fields)
			 . ')';
		$data = [];
		foreach ($fields as $key)
			$data[$key] = $inputs[$key] ?? NULL;
		error_log(__METHOD__ . ':' . $sql);
		error_log(__METHOD__ . ':' . var_export($data, TRUE));
		try {
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($data);
			$result = $stmt->rowCount();
		} catch (Throwable $t) {
			error_log(__METHOD__ . ':' . $t->getMessage());
		}
		return $result;
	}
	/**
	 * Filters data for $table
	 *
	 * @param string $table : table name
	 * @param array $inputs : usually from $_POST
	 * @return array $inputs : filtered
	 */
	public function filter(string $table, array $inputs)
	{
		$callbacks = $this->config['tables'][$table] ?? [];
		$temp = [];
		foreach ($callbacks as $key => $func)
			$temp[$key] = $func($inputs[$key]);
		return $temp;
	}
}
