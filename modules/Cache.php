<?php

class CSPage_Cache extends CSPage_Module {

	private
		$base          = '//',
		$dir           = 'cache/files/',
		$disabled      = array(),
		$ext_shorthand = array(
			'jpeg' => 'jpg'
		);



	// Constructor
	public function __construct($index = 0, $parent = null) {
		$this->parent($parent);

		// Find the default cache directory by iterating parent directories.
		$cwd  = getcwd();
		$cwdd = '';
		$cwdi = $cwd . '/';
		while (
			!is_dir($cwdi . $this->dir()) &&
			$cwdi != '/' &&
			!preg_match('/^\w\:\/$/', $cwdi)
		) {
			$cwdd = '../' . $cwdd;
			chdir('..');
			$cwdi = getcwd();
		}
		chdir($cwd);
		if (is_dir($cwdd . $this->dir()))
			$this->dir($cwdd . $this->dir());

		// Set defaults.
		return $this
			->base('//i.' . preg_replace('/^(?:i|www)\./', '', $_SERVER['HTTP_HOST']))
			->version(0.1);
	}



	// Get or set base URL.
	public function base($url = null) {

		// Get base URL of cached files.
		if (is_null($url))
			return $this->base;

		$this->base = $url . '/';
		$this->debug(array('Set cache base URL.', $this->base));
		return $this;
	}



	// Get or set directory of cached files.
	public function dir($dir = null) {

		// Get
		if (is_null($dir))
			return $this->dir;

		// Set
		$this->dir = realpath(getcwd() . '/' . $dir) . '/';
		$this->debug(array('Set cache directory.', $this->dir()));
		return $this;
	}



	// Disable caching of the page or a file type.
	public function disable($type = null) {

		// Disable caching for this page.
		if (is_null($type)) {
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', false);
			header('Pragma: no-cache');
		}

		// Disable caching of a file type.
		else
			$this->disabled[$type] = true;
		return $this;
	}



	// Check if caching is disabled on this page for a particular file type.
	public function disabled($type) {
		return array_key_exists($type, $this->disabled) ? $this->disabled[$type] : false;
	}



	// Download an external file.
	public function download($url) {
		$this->debug(array('Downloading file.', $url));
		$ch = curl_init($url);
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_HEADER         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false, // fix for self-signed SSL certificates
				CURLOPT_USERAGENT      => array_key_exists('HTTP_USER_AGENT', $_SERVER) ?
				                          $_SERVER['HTTP_USER_AGENT'] :
				                          __METHOD__ . ' (www.charlesstover.com)'
			)
		);
		$response = curl_exec($ch);

		// Check for errors.
		$expires = false;
		if ($error = curl_error($ch)) {
			$this->debug(array('cURL Error: ' . $error, $url));
			trigger_error($error, E_USER_ERROR);
			$response = '/' . '* cURL Error: ' . $error . ' for ' . $url . ' *' . '/';
			$expires  = time() + 60;
		}

		// No error.
		else {
			$delimiter = "\r\n\r\n";
			$response  = explode($delimiter, $response);
			$expires   = array_shift($response);

			// Check for expiration.
			preg_match('/max\-age=(\d+)/', $expires, $expires);
			if ($expires)
				$expires = time() + $expires[1];
			else
				$expires = null;
			$response = implode($delimiter, $response);
			$this->debug(array(
				'Downloaded ' . $url,
				$expires ? 'Expires ' . date('Y-m-d H:i:s', $expires) :
				'No expiration.'
			));
		}
		curl_close($ch);
		return array(
			'contents' => $response,
			'expires'  => $expires
		);
	}



	// Enable caching of a file type.
	public function enable($type) {
		$this->disabled[$type] = false;
		return $this;
	}



	// ETag
	public function eTag($etag) {

		// Only set and check ETags for existing files.
		if (file_exists($etag))
			$etag = base_convert(filemtime($etag), 10, 36);

		// Not updated since last requested.
		if (
			array_key_exists('If-Modified-Since', $_SERVER) &&
			$_SERVER['If-Modified-Since'] == $etag
		) {
			header('HTTP/1.1 304 Not Modified');
			header('Connection: close');
			exit();
		}
		$this->debug(array('Setting ETag', $etag));
		header('ETag: ' . $etag);
		return $this;
	}



	// Check if a cache exists for this file/ID.
	public function exists($id, $ext = null) {
		return file_exists($this->filepath($id, $ext));
	}



	// Check expiration of a cache.
	public function expired($file, $ext = null, $not_exist = null) {
		$this->debug(array('Checking cache expiration.', $this->filename($file, $ext)));

		// Make sure the cache exists before testing if it's expired.
		if ($this->exists($file, $ext)) {
			$this->debug('Expiration check: File is cached.');

			// The framework has been updated after the cache was set.
			if ($this->parent()->mtime() > $this->mtime($file, $ext)) {
				$this->debug('Expiration check: Framework has been updated.');
				$this->debug(array('Expiration Result', 'EXPIRED'));
				return true;
			}

			// If it's a local file,
			if (
				is_string($file) &&
				file_exists($file)
			) {
				$this->debug('Expiration check: File is locally hosted.');

				// The local file has been updated after the cache has.
				if (filemtime($file) > $this->mtime($file, $ext)) {
					$this->debug(array('Expiration Result', 'EXPIRED'));
					return true;
				}
			}

			// If the file has dependencies, make sure none of them have been updated.
			$filepath         = $this->filepath($file, $ext);
			$depends_filepath = $filepath . '.dependencies';
			if (file_exists($depends_filepath)) {
				$this->debug('Expiration check: Dependencies found.');
				$dependencies       = array_map('rtrim', file($depends_filepath));
				$count_dependencies = count($dependencies);
				for ($x = 0; $x < $count_dependencies; $x++) {

					// If a cache exists of this dependency, check if it has expired.
					if ($this->exists($dependencies[$x])) {
						if ($this->expired($dependencies[$x])) {
							$this
								->debug(array('Dependency is outdated.', $dependencies[$x]))
								->debug(array('Expiration Result', 'EXPIRED'));
							return true;
						}
						$this->debug(array('Dependency is valid.', $dependencies[$x]));
					}

					// This dependency is not in cache.
					else {

						// If a cache doesn't exist, check local files.
						if (file_exists($dependencies[$x])) {
							if (filemtime($dependencies[$x]) > filemtime($filepath)) {
								$this
									->debug(array('Dependent file has been updated.', $dependencies[$x]))
									->debug(array('Expiration Result', 'EXPIRED'));
								return true;
							}
							$this->debug(array('Dependency is valid.', $dependencies[$x]));
						}
						else
							trigger_error('Cannot find dependent file: ' . $dependencies[$x]);
					}
				}
			}

			// If an expiration date was defined, make sure it has not passed.
			$expires_filepath = $filepath . '.expires';
			if (file_exists($expires_filepath)) {
				$expiration = base_convert(file_get_contents($expires_filepath), 36, 10);
				$this->debug(array('Expiration date found.', date('Y-m-d H:i:s', $expiration)));

				// If the current time is after the expiration date, it's expired.
				if (time() > $expiration) {
					$this->debug(array('Expiration Result', 'EXPIRED'));
					return true;
				}
			}

			$this->debug(array('Expiration Result', 'UP TO DATE'));
			return false;
		}
		$this->debug(array('Expiration Check', 'Cache doesn\'t exist. ' . ($not_exist ? 'Expired' : 'Not expired') . ' by default.'));
		return $not_exist;
	}



	// Pop extension off filename.
	public function extension($filename) {
		$ext = explode('.', $filename);
		$ext = $ext[count($ext) - 1];
		if (array_key_exists($ext, $this->ext_shorthand))
			$ext = $this->ext_shorthand[$ext];
		return strlen($ext) < 6 ? $ext : null;	
	}



	// Check if a cache exists for this file/ID.
	// TODO?: Switch to MurmurHash or xxhash64 for file names?
	public function filename($id, $ext = null) {

		// Convert ID to array of IDs.
		if (!is_array($id))
			$id = array($id);

		// Standardize file paths, so that /path/to/file and /path/not/../to/file point to the same cache file.
		$count_id = count($id);
		for ($x = 0; $x < $count_id; $x++) {

			// Give local files their full, stnadard path.
			$cwd_path = getcwd() . '/' . $id[$x];
			if (file_exists($cwd_path))
				$id[$x] = realpath($cwd_path);

			// Windows environment may link to C:\file.jpg or C:/file.jpg
			// Standardize this so that the same filename/filepath is generated in each instance.
			$id[$x] = str_replace('\\', '/', $id[$x]);
		}
		sort($id);
		$id = implode(', ', $id);

		// Attempt to rip extension from file name.
		if (is_null($ext))
			$ext = $this->extension($id);

		// Generate directory and file name of cache file.
		$sha1 = base_convert(sha1($id), 16, 36);
		$dir  = substr($sha1, 0, 2);

		// ad is a reserved directory to stop adblockers from erroneously blocking the file
		if ($dir == 'ad')
			$dir = 'ae';

		// Make sure the directories exist.
		$mkdir = $this->dir() . $dir;
		if (!is_dir($mkdir)) {
			mkdir($mkdir);
			chmod($mkdir, 0777);
		}

		// Return the file name.
		return
			$dir . '/' .
			substr($sha1, 2) .
			(is_null($ext) || !$ext ? '' : '.' . $ext);
	}



	// Check if a cache exists for this file/ID.
	public function filepath($id, $ext = null) {
		return $this->dir() . $this->filename($id, $ext);
	}



	// Cached file modification time.
	public function mtime($file, $ext = null) {
		return $this->exists($file, $ext) ? filemtime($this->filepath($file, $ext)) : 0;
	}



	// Cache data
	// Store $contents to filepath($id) with $options
	public function store($id, $contents, $options = array()) {

		// Non-array options defaults to extension.
		if (!is_array($options))
			$options = array('ext' => $options);

		// Attempt to get extension from file name.
		if (!array_key_exists('ext', $options))
			$options['ext'] = $this->extension($id);

		$ext      = $options['ext'];
		$filepath = $this->filepath($id, $ext);

		// Save list of dependencies.
		if (
			array_key_exists('dependencies', $options) &&
			!empty($options['dependencies'])
		) {
			$this->debug(array(
				'Dependency provided for file being cached.',
				is_array($options['dependencies']) ?
				implode(', ', $options['dependencies']) :
				$options['dependencies']
			));
			file_put_contents(
				$filepath . '.dependencies',
				is_array($options['dependencies']) ?
				implode(PHP_EOL, $options['dependencies']) :
				$options['dependencies']
			);
		}

		// Save expiration date, even if the $contents haven't changed for an existing file (the expiration date still might).
		if (array_key_exists('expires', $options)) {
			$this->debug(array('Expiration for file being cached.', date('Y-m-d H:i:s', $options['expires'])));
			file_put_contents(
				$filepath . '.expires',
				base_convert($options['expires'], 10, 36)
			);
		}

		// Only write the data if it is changed so that the client doesn't redownload the same content with a new file name.
		$contents = is_null($options['ext']) ? $contents : $this->module('compress')->$ext($contents);
		if (
			!$this->exists($filepath) ||
			$contents != file_get_contents($filepath)
		) {
			$this->debug(array('Writing cache.', $filepath));
			file_put_contents($filepath, $contents);
			return $this;
		}
		$this->debug('Cache already exists and is unchanged.');
		return $this;
	}



	// Cache the file if it has been updated.
	public function update($file, $options = array(), $not_exist = null) {

		// Non-array parameter defaults to extension.
		if (!is_array($options))
			$options = array('ext' => $options);

		// File extension by default.
		$ext = array_key_exists('ext', $options) ? $options['ext'] : null;

		// Check if the file needs updating.
		// If the file doesn't exist, return $not_exist.
		if ($this->expired($file, $ext, $not_exist)) {
			$this->debug(array('Updating cache of expired file.', $file));

			// Download external file.
			if ($this->parent()->external($file)) {
				$this->debug(array('Storing external file.', $file));
				$download           = $this->download($file);
				$contents           = $download['contents'];
				$options['expires'] = $download['expires'];
			}

			// Store local file.
			else {
				$this->debug(array('Storing local file.', $file));
				$contents = file_get_contents($file);
			}

			if (!is_null($ext))
				$contents = $this->module('compress')->$ext($contents);
			return $this->store($file, $contents, $options);
		}
		return $this;
	}



	// Get URL of cached files.
	public function url($id, $ext = null) {
		if (is_null($ext))
			$ext = $this->extension($id);
		return
			$this->base() .
			$this->filename($id, false) . '.' .
			base_convert(
				$this->mtime($id, $ext),
				10, 36
			) .
			(is_null($ext) ? '' : '.' . $ext);
	}

}

?>
