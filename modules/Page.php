<?php

class CSPage_Page extends CSPage_Module {

	private
		$dir = './';

	public function __construct($id = 0, $parent = null) {
		return $this;
	}



	// Get and set directory of pages.
	public function dir($dir = null) {

		// Get
		if (is_null($dir))
			return $this->dir;

		// Set
		if ($dir == '')
			$dir = './';
		else if (!preg_match('/\/$/', $dir))
			$dir .= '/';
		$this->dir = $dir;
		$this->debug(array('Set page directory.', $this->dir));
		return $this;
	}



	// Load a page and instantiate its modules and files.
	public function load($id) {
		$dir = $this->dir . $id . '/';
		if (is_dir($dir)) {
			$index = $dir . 'index.php';
			if (file_exists($index))
				include $index;

			// For each file/directory in the page directory,
			foreach (new DirectoryIterator($dir) as $page_i) {

				// If it's a module,
				if (
					$page_i->isDir() &&
					!$page_i->isDot()
				) {

					// Load the module.
					$module_name = $page_i->getFilename();
					$this->debug(array('Found module in page.', $module_name));
					$module = $this->module($module_name);

					// For each file in the module directory,
					foreach (new DirectoryIterator($page_i->getPathname()) as $module_i) {
						if ($module_i->isFile()) {
							$filename = explode('.', $module_i->getFilename());
							$filename = array_shift($filename);
							if (
								method_exists($module, '__call') ||
								method_exists($module, $filename)
							) {
								$this->debug(array('Found module method in page.', $module_name . '->' . $filename));
								$module->$filename($module_i->getPathname());
							}
							else
								$this->debug(array('Cannot find module method in page.', $module_name . '->' . $filename));
						}
					}
				}
			}
			return $this;
		}
		$this->debug(array('Cannot find page.', $dir));
		return $this;
	}

}

?>