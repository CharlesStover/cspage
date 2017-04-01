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
		$debug         = array(),
		$debug_enabled = false,
		$dev_machine   = false,
		$errors      = array(),
		$modules     = array(),
		$time_start  = 0,
		$version     = 0.1;

	private static
		$errnos = array(
			E_ALL               => 'All',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_DEPRECATED        => 'Deprecated',
			E_ERROR             => 'Error',
			E_NOTICE            => 'Notice',
			E_PARSE             => 'Parse',
			E_RECOVERABLE_ERROR => 'Recoverable Error',
			E_STRICT            => 'Strict',
			E_USER_DEPRECATED   => 'User Deprecated',
			E_USER_ERROR        => 'User Error',
			E_USER_NOTICE       => 'User Notice',
			E_USER_WARNING      => 'User Warning',
			E_WARNING           => 'Warning'
		);




	// Constructor
	public function __construct() {

		// Start the timer.
		$this->time_start = microtime(true);

		// Error Handling
		error_reporting(E_ALL);
		set_error_handler(array($this, 'errorHandler'), E_ALL);

		// Module class
		include 'modules/Module.php';

		// Default settings
		$this
			->debugEnabled(array_key_exists('debug', $_GET))
			->devMachine(preg_match('/^localhost/', $_SERVER['HTTP_HOST']));

		return $this;
	}



	// Pads a 2D array's values to the same length vertically.
	// Used in errorOutput to stylize plain text.
	// [Value 1] [Value 2] [Value 3]
	// [Val1]    [Val2]    [Val3]
	public function arrayPadColumn($array) {

		// Get the max length of each section for padding.
		$count_array = count($array);
		$max_length = array();
		for ($x = 0; $x < $count_array; $x++) {
			$count_array_x = count($array[$x]);
			for ($y = 0; $y < $count_array_x; $y++) {
				if (!array_key_exists($y, $max_length))
					$max_length[$y] = 0;
				$lines = explode(PHP_EOL, $array[$x][$y]);
				$count_lines = count($lines);
				for ($z = 0; $z < $count_lines; $z++)
					$max_length[$y] = max(
						$max_length[$y],
						strlen($lines[$z])
					);
			}
		}

		// Set padding.
		for ($x = 0; $x < $count_array; $x++) {
			$count_array_x = count($array[$x]);
			for ($y = 0; $y < $count_array_x; $y++) {
				$lines = explode(PHP_EOL, $array[$x][$y]);
				$count_lines = count($lines);
				$lines[$count_lines - 1] = str_pad($lines[$count_lines - 1], $max_length[$y]);
				$array[$x][$y] = implode(PHP_EOL, $lines);
			}
		}
		return $array;
	}



	// Content-Type header
	public function contentType($type, $charset = 'utf-8') {
		header('Content-Type: ' . $type . '; charset=' . $charset);
		return $this;
	}



	// Number of errors.
	// Faster at determining if errors exist than returning entire array.
	public function countErrors() {
		return count($this->errors);
	}



	// Debugger
	public function debug($debug) {

		// Convert to array.
		if (!is_array($debug))
			$debug = array($debug);

		// Get caller(s).
		$caller          = array();
		$backtrace       = debug_backtrace();
		$count_backtrace = count($backtrace);
		for ($x = $count_backtrace - 1; $backtrace[$x]['function'] != 'debug'; $x--)
			array_push($caller, $backtrace[$x]['class'] . '->' . $backtrace[$x]['function'] . ' (' . $backtrace[$x]['line'] . ')');
		array_unshift($debug, implode(PHP_EOL, $caller));
		array_push($this->debug, $debug);
		return $this;
	}



	// Enable or disable debugging.
	public function debugEnabled($bool = null) {
		if (is_null($bool))
			return $this->debug_enabled;
		$this->debug_enabled = $bool;
		return $this;
	}



	// Are we on development machine?
	public function devMachine($bool = null) {
		if (is_null($bool))
			return $this->dev_machine;
		$this->dev_machine = $bool;
		return $this;
	}



	// Convert error numbers to human-readable text.
	public function errno($no) {
		return
			is_null($no) ?
			'N/A' :
			(
				array_key_exists($no, self::$errnos) ?
				self::$errnos[$no] :
				$no
			);
	}



	// Error Handler
	public function errorHandler($no, $str, $file = null, $line = null, $context = null) {

		// Missing parameters.
		if (is_null($file))
			$file = 'Unknown File';
		if (is_null($line))
			$line = 'N/A';

		// Store in error log.
		array_push(
			$this->errors,
			array($this->errno($no), $file, 'Line ' . $line, $str)
		);

		// Fatal Error: Output errors and terminate instructions.
		if (in_array($no, array(E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_USER_ERROR))) {
			$this->errorOutput();
			exit();
		}

		return $this;
	}



	// Load a module.
	private function loadModule($module) {
		$this->debug(array('Loading module.', $module));

		// If the module doesn't exist, create it.
		if (!array_key_exists($module, $this->modules)) {

			// Make sure the file exists.
			$module_file = $this->moduleFileName($module);
			if (!file_exists($module_file))
				trigger_error('Could not read module file: ' . $module_file, E_USER_ERROR);

			// Make sure the class exists.
			include $module_file;
			$module_class = $this->moduleClassName($module);
			if (!class_exists($module_class))
				trigger_error('Could not find class: ' . $module_class, E_USER_ERROR);

			// Create the array for module instances.
			$this->modules[$module] = array();
		}
		return $this;
	}



	// Load or access a module.
	public function module($module, $index = 0) {

		// If this module doesn't exist, load it and try again.
		if (!array_key_exists($module, $this->modules))
			return $this->loadModule($module)->module($module, $index);

		// true = new module
		if ($index === true)
			$index = count($this->modules);

		// false = last module
		else if ($index === false)
			$index = count($this->modules) - 1;

		// If the instance doesn't exist, create it.
		if (!array_key_exists($index, $this->modules[$module])) {
			$module_class = $this->moduleClassName($module);

			// Instantiate module.
			$this->debug(array('Instantiating module.', $module . ' #' . $index));
			$this->modules[$module][$index] = new $module_class($index, $this);

			// Set the parent if this module extents CSPage_Module
			if (method_exists($this->modules[$module][$index], 'parent'))
				$this->modules[$module][$index]->parent($this);
		}

		return $this->modules[$module][$index];
	}



	// Convert module ID to class name.
	public function moduleClassName($id) {
		return 'CSPage_' . ucwords($id);
	}



	// Check if a module has been loaded.
	public function moduleExists($module, $index = 0) {
		return
			array_key_exists($module, $this->modules) &&
			array_key_exists($index, $this->modules[$module]);
	}



	// Convert module ID to file name.
	public function moduleFileName($id) {
		return __DIR__ . '/modules/' . ucwords($id) . '.php';
	}



	// Output when there are errors
	public function outputErrors($return_only = false) {

		// Headers only if we are outputting.
		if (!$return_only) {
			header('Content-Language: en-us');
			$this->contentType('text/plain');
		}

		// Generate human-readable log.
		ob_start(
			$this->debugEnabled() ||
			$return_only ?
			null :
			'ob_gzhandler'
		);

		// Error log
		$count_errors = $this->countErrors();
		if ($count_errors)
			echo $count_errors, ' Error', ($count_errors != 1 ? 's' : ''), ':', PHP_EOL,
				 implode(
				 	PHP_EOL,
				 	array_map(
				 		function($v) {
				 			return '* ' . implode('   ', $v);
				 		},
				 		$this->arrayPadColumn($this->errors)
				 	)
				 );
		else
			echo 'No errors.';
		echo PHP_EOL, PHP_EOL;

		// Debug log
		if (count($this->debug))
			echo 'Debug Information:', PHP_EOL, PHP_EOL,
				 implode(
				 	PHP_EOL . PHP_EOL,
				 	array_map(
				 		function($v) {
				 			return implode('   ', $v);
				 		},
				 		$this->arrayPadColumn($this->debug)
				 	)
				 );
		else
			echo 'No debug information.';
		echo PHP_EOL, PHP_EOL, $this->time(), ' seconds';

		// Output or return text.
		if ($return_only) {
			$ogc = ob_get_contents();
			ob_end_clean();
			return $ogc;
		}
		ob_end_flush();
		return $this;
	}



	// Execution time thus far.
	public function time() {
		return microtime(true) - $this->time_start;
	}



	// Get version number.
	public function version() {
		return $this->version;
	}

}

?>
