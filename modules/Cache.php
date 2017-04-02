<?php

class CSPage_Cache extends CSPage_Module {

	private
		$dir = 'cache/files/',
		$url = '//';



	// Constructor
	public function __construct($index = 0, $parent = null) {
		$this
			->version(0.1)
			->parent($parent);

		// Find the default cache directory by iterating parent directories.
		$cwd = getcwd();
		$cwdi = $cwd;
		while (
			!is_dir($cwdi . '/' . $this->dir()) &&
			$cwdi != '/'
		) {
			chdir('..');
			$cwdi = getcwd();
		}
		chdir($cwd);
		if (is_dir($cwdi . '/' . $this->dir()))
			$this->dir($cwdi . '/' . $this->dir());

		// Set defaults.
		return $this->url('//i.' . preg_replace('/^(?:i|www)\./', '', $_SERVER['HTTP_HOST']));
	}



	// Get or set directory of cached files.
	public function dir($dir = null) {
		if (is_null($dir))
			return $this->dir;

		// Append trailing slash.
		if (substr($dir, -1) != '/')
			$dir .= '/';
		$this->dir = $dir;
		$this->debug(array('Set cache directory.', $dir));
		return $this;
	}



	// Get URL of cached files.
	public function url($id = null, $ext = null) {

		// Get base URL of cached files.
		if (is_null($id))
			return $this->url;

		// Set base URL of cached files.
		if (is_null($ext)) {
			$this->url = $id . '/';
			$this->debug(array('Set cache URL.', $this->url));
			return $this;
		}

		// hash($id) . filemtime . $ext
		return
			$this->url() .
			$this->filename($id) . '.' .
			base_convert(
				filemtime($this->filepath($id, $ext)),
				10, 36
			) .
			($ext ? '.' . $ext : '');
	}

}

?>
