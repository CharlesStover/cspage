<?php

class CSPage_Db {

	public function __destruct() {
		if ($this->db)
			unset($this->db);
	}

}

?>
