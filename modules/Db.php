<?php

class CSPage_Db extends CSPage_Module {
	private
		$db = null;

	public function __destruct() {
		if ($this->db)
			unset($this->db);
	}

}

?>
