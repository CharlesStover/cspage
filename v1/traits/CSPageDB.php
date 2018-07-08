<?php

trait CSPageDB {

	public
		$db = null;

	public function PDO($dbname, $username, $password, $host = 'localhost') {
		try {
			$this->db = new PDO('mysql:host=' . $host . '; dbname=' . $dbname, $username, $password);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e) {
			$this->errorHandler(null, $e->getMessage(), __FILE__, __LINE__);
			$this->db = null;
			$this->output();
			exit();
		}
		return $this->db;
	}

	public function query($prepare, $execute = null) {
		if ($execute) {
			$statement = $this->db->prepare($prepare);
			$statement->execute($execute);
		}
		else
			$statement = $this->db->query($prepare);
		return $statement;
	}

}

?>