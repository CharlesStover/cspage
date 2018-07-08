<?php

class CSSS {

	private $cache_dir      = 'static/'; // directory for storing cached files
	private $cache_duration = 432000;                     // default cache duration for downloads is 5 days
	private $inline_limit   = 1024;                       // number of bytes below which we don't bother writing to file
	private $url            = 'i.charlesstover.com/';     // url directory (mod_rewritten)

	private $GoogleFonts = array(); // Google Fonts to include
	private $inline      = array(); // CSS strings to display inline (keys = media types)
	private $queue       = array(); // CSS files to concatenate at the end

	public function __construct() {

		// localhost vs. live
		if (preg_match('/^localhost/', $_SERVER['HTTP_HOST']))
			$this->url = '//localhost.' . $this->url;
		else
			$this->url = '//' . $this->url;

		// find the storage directory
		while (!is_dir($this->cache_dir))
			$this->cache_dir = '../' . $this->cache_dir;
	}



	// add a file to the compressor
	public function add($file, $media = 'all', $scope = 'global') {

		// external files
		if (strpos($file, '://')) {
			$cache_filepath = $this->cache_filepath($file);
			$expires_filepath = $cache_filepath . '.expires';

			// If the cache doesn't exist, or it is expired,
			if (
				!file_exists($cache_filepath) ||
				time() > filemtime($cache_filepath) + (
					file_exists($expires_filepath) ?
					base_convert(
						file_get_contents($expires_filepath),
						36, 10
					) :
					$this->cache_duration
				)
			) {

				// Download and cache the file.
				$ch = curl_init($file);
				curl_setopt_array(
					$ch,
					array(
						CURLOPT_HEADER         => true,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_SSL_VERIFYPEER => false // fix for self-signed SSL certificates
					)
				);
				$response = curl_exec($ch);

				// Check for errors.
				if ($error = curl_error($ch))
					$response = '/* cURL Error: ' . $error . ' */';
				else {
					$response = explode("\r\n\r\n", $response);

					// Check for expiration.
					preg_match('/max\-age=(\d+)/', $response[0], $expires);
					if ($expires)
						file_put_contents(
							$expires_filepath,
							base_convert($expires[1], 10, 36)
						);
					$response = $this->compress_css($response[1]);
				}
				curl_close($ch);
				file_put_contents(
					$cache_filepath,
					$response
				);
			}
		}

		// Locally hosted files
		else {
			$file = getcwd() . '/' . $file;
			$cache_filepath = $this->cache_filepath($file);

			// If this file hasn't been cached yet, or the cache is outdated,
			if (
				!file_exists($cache_filepath) ||
				filemtime($file) > filemtime($cache_filepath)
			)

				// save a compressed cache of this file
				file_put_contents(
					$cache_filepath,
					$this->compress_css(file_get_contents($file))
				);
		}

		// File has been prepped, queue it for concatenation.
		// Use file name instead of cache_filepath so that url() can use its own algorithm.
		$this->queue($file, $scope, $media);
		return true;
	}



	// local path given an ID
	public function cache_filepath($file) {
		return $this->cache_dir . 'css/' . md5($file) . '-' . sha1($file) . '.css';
	}



	// set cache directories
	function chdir($global, $local = false) {
		$this->url = $global . '/';
		if ($local)
			$this->cache_dir = getcwd() . '/' . $local . '/';
		if (!is_dir($this->cache_dir . 'css')) {
			mkdir($this->cache_dir . 'css');
			chmod($this->cache_dir . 'css', 0777);
		}
	}



	// compress CSS
	public function compress_css($css) {
		$compressed =
			file_get_contents(
				'http://cssminifier.com/raw',
				false,
				stream_context_create(array(
					'http' => array(
						'method'  => 'POST',
						'header'  => 'Content-type: application/x-www-form-urlencoded',
						'content' => http_build_query(array(
							'input' => $css
						))
					)
				))
			);
		return $compressed; // '/* ' . (strlen($compressed) - strlen($css)) . 'b */'
	}



	// add a Google Font
	public function GoogleFont($name) {
		array_push($this->GoogleFonts, $name);
	}



	// The HTML
	public function html() {
		$html = '';

		// Google Fonts
		// implode('|', $this->GoogleFonts)
		foreach ($this->GoogleFonts as $google_font)
			$this->add(
				'https://fonts.googleapis.com/css?family=' . str_replace(' ', '+', $google_font),
				'all',
				'multiple'
			);

		// Concatenate all same-scope files.
		foreach ($this->queue as $scope => $medias) {
			foreach ($medias as $media => $files) {
				sort($files);
				$concat_id = implode('|', $files);

				// concatenate
				$concat      = '';
				$count_files = count($files);
				$lastmod     = 0;
				for ($x = 0; $x < $count_files; $x++) {
					$cache_filepath = $this->cache_filepath($files[$x]);
					$filemtime      = filemtime($cache_filepath);
					if ($filemtime > $lastmod)
						$lastmod = $filemtime;
					$concat .= file_get_contents($cache_filepath);
				}

				// Long CSS goes to a file,
				if (strlen($concat) > $this->inline_limit) {

					// If any file has been updated, update the concatenated cache.
					$cache_filepath = $this->cache_filepath($concat_id);
					if (
						!file_exists($cache_filepath) ||
						$lastmod > filemtime($cache_filepath)
					)
						file_put_contents($cache_filepath, $concat);
					$html .= '<link href="' . $this->url($concat_id) . '" media="' . $media . '" rel="stylesheet" type="text/css" />';
				}

				// Short CSS is included directly.
				else {
					if (!array_key_exists($media, $this->inline))
						$this->inline[$media] = '';
					$this->inline[$media] .= $concat;
				}
			}
		}

		// inline CSS
		foreach ($this->inline as $media => $css)
			$html .= '<style media="' . $media . '" type="text/css">' . $css . '</style>';

		return $html;
	}



	// Queue a file for concatenation.
	private function queue($file, $scope = 'multiple', $media = 'screen') {
		if (!array_key_exists($scope, $this->queue))
			$this->queue[$scope] = array();
		if (!array_key_exists($media, $this->queue[$scope]))
			$this->queue[$scope][$media] = array();
		array_push($this->queue[$scope][$media], $file);
	}



	// online path for a file
	// appends filemtime
	public function url($file) {
		return $this->url . md5($file) . '-' . sha1($file) . '.' . base_convert(filemtime($this->cache_filepath($file)), 10, 36) . '.css';
	}
}

?>