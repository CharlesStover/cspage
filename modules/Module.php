<?php

class CSPage_Module {

	private
		$parent  = null,
		$version = 1;



	// Debugger
	public function debug($debug) {
		$this->parent()->debug($debug);

		// return $this, not $this->parent()
		return $this;
	}



	// Hook
	public function hook($id, $callback = null) {
		return $this->parent()->hook($id, $callback);
	}



	// Access another module.
	public function module($id, $index = 0) {
		return $this->parent()->module($id, $index);
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
