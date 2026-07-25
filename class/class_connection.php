<?php 
	/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25) 
 * @version: 3.0 - (2015/04/7)
 * @version: 4.0 - (2025/03/06)
 * @PHP 5.5.0
 * @author Oscar Rojas
 * Examples and documentation @: http://red-sphere.com/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

#[\AllowDynamicProperties]
class connection
{
	public $dbhost;
	public $dbuser;
	public $dbpass;
	public $dbname;
	public $connection;
	public $selection;
	public $result;
	public $updateresult;
	public $deleteresult;
	public $insertresult;
	public $createresult;
	
	public function __construct($dbhost, $dbuser, $dbpass, $dbname)	{
		$this->dbhost=$dbhost;
		$this->dbuser=$dbuser;
		$this->dbpass=$dbpass;
		$this->dbname=$dbname;
		
		$this->connect();
	}
	
	public function connect()
	{
		$this->openConnection();
		//$this->selectDatabase();
	}
	
	public function openConnection()
	{
		$this->connection = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);
		if (!$this->connection) {
			error_log('Database connection failed: ' . mysqli_connect_error());
			die('Database connection failed.');
		}
		if (!mysqli_set_charset($this->connection, 'utf8mb4')) {
			error_log('Database charset initialization failed: ' . mysqli_error($this->connection));
			mysqli_close($this->connection);
			die('Database connection failed.');
		}
		// Check connection
		/*if (mysqli_connect_errno())
		  {
		  echo "Failed to connect to MySQL: " . mysqli_connect_error();
		  }
		  else
		  echo 'connected';*/

	}
	
	public function selectDatabase()
	{
		$this->selection = mysqli_select_db($this->connection, $this->dbname);
		if (!$this->selection) {
			$this->fail('select database');
		}
	}
	
	public function query($query)
	{
		$this->result = mysqli_query($this->connection, $query);
		if ($this->result === false) {
			$this->fail('query');
		}
		return $this->result;
	}
	
	public function update($query)
	{
		$this->updateresult = mysqli_query($this->connection,$query);
		if ($this->updateresult === false) {
			$this->fail('update');
		}
		return mysqli_affected_rows($this->connection);
	}
	
	public function delete($query)
	{
		$this->deleteresult = mysqli_query($this->connection,$query);
		if ($this->deleteresult === false) {
			$this->fail('delete');
		}
		return mysqli_affected_rows($this->connection);
	}
	
	public function insert($query)
	{
		$this->insertresult = mysqli_query($this->connection,$query);
		if ($this->insertresult === false) {
			$this->fail('insert');
		}
		return mysqli_affected_rows($this->connection);
	}
	
	public function create($query)
	{
		$this->createresult = mysqli_query($this->connection,$query);
		if ($this->createresult === false) {
			$this->fail('create');
		}
		return mysqli_affected_rows($this->connection);
	}
	
	function close() {
        
        mysqli_close($this->connection);
    }

	private function fail($operation)
	{
		error_log('Database ' . $operation . ' failed: ' . mysqli_error($this->connection));
		die('Database query failed.');
	}
	
}


?>
