<?php

class CSPage_Module {

	private
		$parent  = null,
		$version = 1;



	// Debugger
	public function debug($debug) {
		$this->parent()->debug($debug, get_class($this));
		return $this;
	}



	// Set the parent object.
	public function parent($parent = null) {
		if (is_null($parent))
			return $this->parent;
		$this->parent = $parent;
		return $this;
	}



	// Version
	public function version($version = null) {
		if (is_null($version))
			return $this->version;
		$this->version = $version;
		return $this;
	}

}

?>
