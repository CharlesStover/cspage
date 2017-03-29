<?php

/*
TO-DO:
	Compress:
		optipng
	CSS:
		LESS and SASS
	HTML:
		move CSS media=print to foot of <body>
*/



// Don't define twice.
if (class_exists('CSPage'))
	return;

/**
 * Automated webpage optimization utility that caches, compresses, concatenates, and otherwise optimizes HTML, CSS, JavaScript, and static file content distribution.
 *
 * Supports HTML and CSS variables, custom <if>/<else> and <foreach> HTML tags, automated ETag generation, YUI compressor, permanent static file caching, dependency-based caching, and countless shortcuts to decrease page development time.
 *
 * @author     Charles Stover <cspage@charlesstover.com>
 * @copyright  2016-2017 Charles Stover
 * @license    https://creativecommons.org/licenses/by-nc-nd/4.0/  Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International
 * @link       https://github.com/CharlesStover/cspage
 * @version    0.1
 */

class CSPage {

	private
		$dev_machine     = false,
		$modules         = array(),
		$start_microtime = 0,
		$version         = 0.1;



	// Constructor
	public function __construct() {

		// Start the timer.
		$this->start_microtime = microtime(true);

		// Initialize the error-handling module.
		$this->loadModule('error');

		// Are we on a localhost?
		$this->devMachine(preg_match('/^localhost/', $_SERVER['HTTP_HOST']));

		return $this;
	}



	// Are we on development machine?
	public function devMachine($bool = null) {
		if (is_null($bool))
			return $this->dev_machine;
		$this->dev_machine = $bool;
		return $this;
	}



	// Load a module.
	private function loadModule($module) {
		if ($module != 'error')
			$this->module('error')->debug('Loading module: ' . $module);

		// If the module already exists, don't do anything.
		if (array_key_exists($module, $this->modules))
			return $this;

		// Make sure the file exists.
		$module_id   = ucwords($module);
		$module_file = __DIR__ . '/modules/' . $module_id . '.php';
		if (!file_exists($module_file))
			trigger_error('Could not read module file: ' . $module_file, E_USER_ERROR);

		// Make sure the class exists.
		include $module_file;
		$class_name = 'CSPage_' . $module_id;
		if (!class_exists($class_name))
			trigger_error('Could not find class: ' . $class_name, E_USER_ERROR);

		// Create the module.
		$this->modules[$module] = new $class_name($this);
		if (method_exists($this->modules[$module], 'setParent'))
			$this->modules[$module]->setParent($this);

		return $this;

	}



	// Load or access a module.
	public function module($module) {

		// If this module exists, return it.
		if (array_key_exists($module, $this->modules))
			return $this->modules[$module];

		// If the module doesn't exist, create it.
		// OPTIMIZE? This may result in an infinite loop if an update is poorly written, especially for module('error').
		return $this->loadModule($module)->module($module);
	}



	// Get version number.
	public function version() {
		return $this->version;
	}

}

?>
