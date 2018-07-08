<?php

trait CSPageCache {

	private
		$cache_global = null,              // URL of static domain
		$cache_local  = 'cache/files/',    // Local path to static storage
		$disabled_cache = array(),         // file types not to cache
		$external_cache_duration = 432000; // default cache duration for downloads is 5 days



	// Cache $file with extension $ext
	public function cache($id, $contents = false, $ext = false, $expires = false, $dependencies = false) {
		$cache_filepath = $this->cacheFilepath($id, $ext);
		$id_r = $this->id_r($id);

		// Save list of dependencies.
		if (
			$dependencies &&
			!empty($dependencies)
		) {
			$this->debug($id_r . ' depends on ' . is_array($dependencies) ? implode(', ', $dependencies) : $dependencies);
			file_put_contents(
				$cache_filepath . '.dependencies',
				is_array($dependencies) ? implode(PHP_EOL, $dependencies) : $dependencies
			);
		}

		// Save expiration date, even if the $contents haven't changed for an existing file (the expiration date still might).
		if ($expires) {
			$this->debug($id_r . ' expires on ' . date('Y-m-d H:i:s', $expires) . '.');
			file_put_contents(
				$cache_filepath . '.expires',
				base_convert($expires, 10, 36)
			);
		}

		// File passed by reference.
		if (file_exists(str_replace('\0', '', $contents)))
			$contents = $this->compress(file_get_contents($contents), $ext);

		// If the cache already contains this content, don't write it again.
		// Writing it redundantly will update filemtime and force the user to redownload it.
		if (
			$this->cached($id, $ext) &&
			file_get_contents($cache_filepath) == $contents
		) {
			$this->debug($id_r . ' already cached and unchanged.');
			return $this;
		}

		// The cache has new content.
		file_put_contents($cache_filepath, $contents);
		$this->debug('Cached ' . $id_r . ' to ' . $this->cacheFilename($id, $ext));
		return $this;
	}



	// Check if an identifier is cached.
	public function cached($id, $ext = false) {
		return file_exists($this->cacheFilepath($id, $ext));
	}



	// Generate filename of cache file.
	public function cacheFilename($id, $ext = false) {
		if (!is_array($id))
			$id = array($id);

		// Standardize file paths.
		$count_id = count($id);
		for ($x = 0; $x < $count_id; $x++) {

			// Give local files their full path.
			$cwd_path = getcwd() . '/' . $id[$x];
			if (file_exists($cwd_path))
				$id[$x] = $cwd_path;

			// Windows environment may link to C:\file.jpg or C:/file.jpg
			// Standardize this so that the same filename/filepath is generated in each instance.
			$id[$x] = str_replace('\\', '/', $id[$x]);
		}
		sort($id);
		$id = implode(', ', $id);

		// Store the file in /files/xx/yy/
		$md5 = md5($id);
		$dir1 = substr($md5,  0, 2);

		// ad is a reserved directory to stop adblockers from erroneously blocking the file
		if ($dir1 == 'ad')
			$dir1 = 'ae';
		$dir  = $this->cache_local . $dir1;
		if (!is_dir($dir)) {
			mkdir($dir);
			chmod($dir, 0777);
		}
		$dir2 = '/' . substr($md5, 30);

		// ad is a reserved directory to stop adblockers from erroneously blocking the file
		if ($dir2 == '/ad')
			$dir2 = '/ae';
		$dir .= $dir2;
		if (!is_dir($dir)) {
			mkdir($dir);
			chmod($dir, 0777);
		}
		return $dir1 . $dir2 . '/' . hash('sha256', $id) . ($ext ? '.' . $ext : '');
	}



	// Generate filepath of cache file.
	public function cacheFilepath($id, $ext = false) {
		return $this->cache_local . $this->cacheFilename($id, $ext);
	}



	// Set the cache URL.
	public function cacheGlobal($global) {
		$this->cache_global = $global . '/';
		return $this;
	}



	// Set the cache directory.
	public function cacheLocal($local) {
		$this->cache_local = getcwd() . '/' . $local . '/';
		return $this;
	}



	// Cache modification time.
	public function cachemtime($id, $ext = false) {
		return filemtime($this->cacheFilepath($id, $ext));
	}



	// Cache only if it doesn't exist or is expired.
	public function cacheNew($id, $contents = false, $ext = false, $expires = false) {
		$this->debug('Cache, If New: ' . $id);
		if (
			!$this->cached($id, $ext) ||
			$this->expired($id, $ext)
		)
			return $this->cache($id, $this->compress($contents, $ext), $ext, $expires);
		return $this;
	}



	// Get the cache URL.
	public function cacheUrl($id, $ext = false) {
		return
			$this->cache_global .
			$this->cacheFilename($id) . '.' .
			base_convert(
				filemtime($this->cacheFilepath($id, $ext)),
				10, 36
			) . '.' . $ext;
	}



	// Disable cache for a file type.
	public function disableCache($type = false) {
		if ($type)
			array_push($this->disabled_cache, $type);

		// Send disable cache headers for this page
		else {
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', false);
			header('Pragma: no-cache');
		}
		return $this;
	}



	// Download an external file.
	public function download($url) {
		$ch = curl_init($url);
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_HEADER         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false, // fix for self-signed SSL certificates
				CURLOPT_USERAGENT      => array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : 'CSPage.download (www.charlesstover.com)'
			)
		);
		$response = curl_exec($ch);

		// Check for errors.
		$expires = false;
		if ($error = curl_error($ch)) {
			$this->debug('cURL Error: ' . $error . ' for ' . $url);
			$response = '/' . '* cURL Error: ' . $error . ' for ' . $url . ' *' . '/';
			$expires  = time() + 60;
		}

		// No error.
		else {
			$response = explode("\r\n\r\n", $response);

			// Check for expiration.
			preg_match('/max\-age=(\d+)/', $response[0], $expires);
			if ($expires)
				$expires = time() + $expires[1];
			$response = $response[1];
			$this->debug('Downloaded ' . $url . ($expires ? ' (expires: ' . date('Y-m-d H:i:s', $expires) . ')' : ''));
		}
		curl_close($ch);
		return array($response, $expires);
	}



	// Download and cache a file.
	public function downloadCache($url, $ext = false) {
		$this->debug('Download & Cache: ' . $url);
		$download = $this->download($url);
		return $this->cache($url, $this->compress($download[0], $ext), $ext, $download[1]);
	}



	// Download and cache a file, only if it's new.
	public function downloadCacheNew($url, $ext = false) {
		$this->debug('Download & Cache, If New: ' . $url);
		if (
			!$this->cached($url, $ext) ||
			$this->expired($url, $ext)
		) {
			$this->debug('New: ' . $url);
			return $this->downloadCache($url, $ext);
		}
		$this->debug('Not New: ' . $url);
		return $this;
	}



	// ETag
	public function eTag($filename) {

		// Only set and check ETags for existing files.
		if (file_exists($filename)) {
			$etag = base_convert(filemtime($filename), 10, 36);

			// Not updated since last requested.
			if (
				array_key_exists('If-Modified-Since', $_SERVER) &&
				$_SERVER['If-Modified-Since'] == $etag
			)
			{
				header('HTTP/1.1 304 Not Modified');
				header('Connection: close');
				exit();
			}
			header('ETag: ' . $etag);
			$this->debug('ETag: ' . $etag);
		}
		else
			$this->debug('Cannot ETag non-existent file: ' . $filename);
		return $this;
	}



	// Check expiration of a cache.
	public function expired($id, $ext = false) {
		$debug = 'Expiration check: ' . $this->id_r($id);

		// It's only expired if a cache exists in the first place.
		// Some files (e.g. local files) won't be cached.
		if ($this->cached($id, $ext)) {
			$debug .= ' is cached';
			$cache_filepath  = $this->cacheFilepath($id, $ext);

			// If $id is a file, make sure it's not out of date.
			if (file_exists($id)) {
				$debug .= ', is a file';
				if (filemtime($id) > filemtime($cache_filepath)) {
					$this->debug($debug . ', and has been updated since last cache.');
					return true;
				}
			}

			// If an expiration date was defined, make sure it has not passed.
			$expires_filepath = $cache_filepath . '.expires';
			if (file_exists($expires_filepath)) {
				$expiration = base_convert(file_get_contents($expires_filepath), 36, 10);
				$debug .= ', has an expiration date of ' . date('Y-m-d H:i:s', $expiration);
				if (time() > $expiration) {
					$this->debug($debug . ', and has expired.');
					return true;
				}
			}

			// If the file has dependencies, make sure none of them have been updated.
			$depends_filepath = $cache_filepath . '.dependencies';
			if (file_exists($depends_filepath)) {
				$this->debug('Expiration check: ' . $this->id_r($id) . ' has dependencies.');
				$dependencies = array_map('rtrim', file($depends_filepath));
				$count_dependencies = count($dependencies);
				for ($x = 0; $x < $count_dependencies; $x++) {
					$ext = explode('.', $dependencies[$x]);
					$ext = $ext[count($ext) - 1];
					if (strlen($ext) > 5)
						$ext = false;

					// If a cache exists of this dependency, check if it has expired.
					if ($this->cached($dependencies[$x], $ext)) {
						if ($this->expired($dependencies[$x], $ext)) {
							$this->debug('Dependency check: ' . $dependencies[$x] . ' is outdated.');
							return true;
						}
						$this->debug('Dependency check: ' . $dependencies[$x] . ' is up-to-date in cache.');
					}
					else {

						// If a cache doesn't exist, check local files.
						if (file_exists($dependencies[$x])) {
							if (filemtime($dependencies[$x]) > filemtime($cache_filepath)) {
								$this->debug('Dependency check: ' . $dependencies[$x] . ' is outdated locally.');
								return true;
							}
							$this->debug('Dependency check: ' . $dependencies[$x] . ' is up-to-date locally.');
						}
						else
							$this->debug('Dependency check: ' . $dependencies[$x] . ' is not a cached or local file, assuming valid.');	
					}
				}
			}
			$this->debug($debug . ', and has not expired.');
		}
		else
			$thus->debug($debug . ' is not cached, thus not expired.');
		return false;
	}



	// ID readable, for debugging
	public function id_r($id) {
		return str_replace(array("\n", "\r", "\t"), ' ', substr(is_array($id) ? implode(', ', $id) : $id, 0, 256));
	}



	// Permanent cache headers
	public function permacache($filename = false) {
		if ($filename)
			$this->eTag($filename);
		header('Cache-Control: public, max-age=31536000'); // 1 year
		return $this;
	}

}

?>