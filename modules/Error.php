<?php

class CSPage_Error {

	private
		$debug_enabled     = false,
		$debug_log         = array(),
		$errnos            = array(
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
			E_WARNING           => 'Warning'),
		$errors            = array(),
		$parent            = null,
		$version           = 0.1;



	// Constructor
	public function __construct($parent) {
		$this->debug('Initializing: Error');

		// Error handling.
		error_reporting(E_ALL);
		set_error_handler(
			array($this, 'handler'),
			E_ALL
		);

		// Set defaults.
		return $this
			->debugEnabled(array_key_exists('debug', $_GET))
			->setParent($parent);
	}



	// Number of errors.
	// Faster at determining if errors exist than returning entire array.
	public function count() {
		return count($this->errors);
	}



	// Debugger
	public function debug($str) {
		array_push(
			$this->debug_log,
			is_array($str) ?
			json_encode($str) :
			$str
		);
		return $this->parent;
	}



	// Enable or disable debugging.
	public function debugEnabled($bool = null) {
		if (is_null($bool))
			return $this->debug_enabled;
		$this->debug_enabled = $bool;
		return $this;
	}



	// Error Handler
	public function handler($no, $str, $file = null, $line = null, $context = null) {

		// Missing parameters.
		if (is_null($file))
			$file = 'Unknown File';
		if (is_null($line))
			$line = 'N/A';

		// Store in error log.
		array_push(
			$this->errors,
			array(
				$file . ' (line ' . $line . '): ',
				$str . (

					// Make error number human-readable.
					$no === null ? '' :
					' (' . (
						array_key_exists($no, $this->errnos) ?
						$this->errnos[$no] :
						$no
					) . ')'
				)
			)
		);

		// Fatal Error: Output errors and terminate instructions.
		if (in_array($no, array(E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_USER_ERROR))) {
			$this->output();
			exit();
		}

		return $this;
	}



	// Output when there are errors
	public function output($return_only = false) {
		$count_errors = $this->count();

		// Get the max length of the filename and line number for padding.
		$max_length = 0;
		for ($x = 0; $x < $count_errors; $x++)
			$max_length = max(
				$max_length,
				strlen($this->errors[$x][0])
			);

		// Headers only if we are outputting.
		if (!$return_only) {
			header('Content-Language: en-us');
			header('Content-Type: text/plain; charset=utf-8');
		}

		// Generate human-readable log.
		ob_start(
			$this->debugEnabled() ||
			$return_only ?
			null :
			'ob_gzhandler'
		);
		echo $count_errors, ' Error' . ($count_errors != 1 ? 's' : '') . ":\r\n";
		for ($x = 0; $x < $count_errors; $x++)
			echo "\r\n", str_pad($this->errors[$x][0], $max_length) . $this->errors[$x][1];
		echo "\r\n\r\nDebug Information:\r\n" . implode("\r\n", $this->debug_log);

		// Output or return text.
		if ($return_only) {
			$ogc = ob_get_contents();
			ob_end_clean();
			return $ogc;
		}
		ob_end_flush();
		return $this;
	}



	// Set Parent
	public function setParent($parent) {
		$this->parent = $parent;
		return $this;
	}



	// Version
	public function version() {
		return $this->version;
	}

}

?>
