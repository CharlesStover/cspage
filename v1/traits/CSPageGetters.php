<?php

trait CSPageGetters {

	// HTML variable isset
	public function get($key) {
		if (array_key_exists($key, $this->html_vars_temp))
			return $this->html_vars_temp[$key];
		$keys   = explode('-', $key);
		$parent = $this->html_vars;
		foreach ($keys as $key) {
			if (!array_key_exists($key, $parent))
				return null;
			$parent = $parent[$key];
		}
		return $parent;
	}

}

?>