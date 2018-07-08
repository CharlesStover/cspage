<?php

class CSPage_Css extends CSPage_Module {

	private
		$inline_byte_limit = 1024,                    // number of bytes below which we don't bother writing to file
		$queue             = array(                   // CSS files to concatenate before output
			'head' => array(),
			'foot' => array()
		);



	// Call print().
	public function __call($name, $arguments) {

		if ($name == 'print')
			return $this->add($arguments[0], 'foot', 'print', array_key_exists(1, $arguments) ? $arguments[1] : 'global');
		return $this;
	}



	// Constructor
	public function __construct($id = 0, $parent = null) {
		$parent->hook(
			'head',
			function() { return $this->html('head'); }
		);
		$parent->hook(
			'foot',
			function() { return $this->html('foot'); }
		);
		return $this;
	}



	// Add CSS to the queue.
	public function add($css, $loc = 'head', $media = 'all', $scope = 'global') {
		if (
			$this->parent()->external($css) ||
			file_exists($css)
		)
			$this->debug(array('Queuing CSS file for ' . $media . ' / ' . $scope . ' in ' . $loc . '.', $css));
		else
			$this->debug('Queuing direct CSS for ' . $media . ' / ' . $scope . ' in ' . $loc . '.');

		// Prepare the media type.
		if (!array_key_exists($media, $this->queue[$loc]))
			$this->queue[$loc][$media] = array();

		// Prepare the scope.
		if (!array_key_exists($scope, $this->queue[$loc][$media]))
			$this->queue[$loc][$media][$scope] = array();

		// Queue this file.
		$filepath = getcwd() . '/' . $css;
		array_push(
			$this->queue[$loc][$media][$scope],
			file_exists($filepath) ? realpath($filepath) : $css
		);
		return $this;
	}



	// Add a Google Font.
	public function GoogleFont($google_font, $media = 'all', $scope = 'multiple') {
		return $this->add(
			'https://fonts.googleapis.com/css?family=' . str_replace(' ', '+', $google_font) .
			(array_key_exists('HTTP_USER_AGENT', $_SERVER) ? '&HTTP_USER_AGENT=' . rawurlencode($_SERVER['HTTP_USER_AGENT']) : ''),
			'head', $media, $scope
		);
	}



	// Add multiple Google Fonts.
	public function GoogleFonts($google_fonts, $media = 'all', $scope = 'multiple') {
		foreach ($google_fonts as $google_font)
			$this->GoogleFont($google_font, $media, $scope);
		return $this;
	}



	// Return the CSS as HTML. <link />
	public function html($loc) {
		$this->debug('Generating HTML for CSS in ' . $loc . '.');
		$cache_module = $this->module('cache');
		$html         = '';
		$inline       = array();

		// For each media (print, screen, ...) in the queue,
		foreach ($this->queue[$loc] as $media => $scopes) {

			// For each scope (single, multiple, global, ...) in the media type,
			foreach ($scopes as $scope => $css) {
				$scope_mod_time = 0;

				// For each CSS file in this scope,
				$count_css = count($css);
				for ($x = 0; $x < $count_css; $x++) {

					// Check external files for cache/expiration.
					if ($this->parent()->external($css[$x])) {

						// If it has expired or doesn't exist, update it.
						$cache_module->update($css[$x], 'css', true);
						$scope_mod_time = max($scope_mod_time, $cache_module->mtime($css[$x], 'css'));
					}

					// Check local files for updates.
					else if (file_exists($css[$x]))
						$scope_mod_time = max($scope_mod_time, filemtime($css[$x]));
				}

				// If the cache doesn't exist, or the dependencies have been updated, or the scope has been modified since last cache.
				if (
					$cache_module->expired($css, 'css', true) ||
					$cache_module->mtime($css, 'css') < $scope_mod_time
				) {
					$this->debug(array(
						'Cache ' . ($cache_module->exists($css, 'css') ? 'outdated' : 'does not exist') . ' for CSS scope.',
						$media . ' / ' . $scope
					));

					// Cancatenate this scope.
					$concat       = array();
					$dependencies = array();
					for ($x = 0; $x < $count_css; $x++) {

						$contents =

							// Get contents of cached copy of external files.
							$this->parent()->external($css[$x]) ?
							file_get_contents($cache_module->filepath($css[$x], 'css')) : (

								// Get contents of actual copy of local files.
								file_exists($css[$x]) ?
								file_get_contents($css[$x]) :

								// Direct CSS
								$css[$x]
							);

						// Grab file references to store them in the static directory.
						// may need to replace [^\1] with [^\'\"]
						preg_match_all('/url\((\'|\")?([^\1\n\r]+?)\1\)/', $contents, $urls);
						$count_urls = count($urls[2]);
						for ($y = 0; $y < $count_urls; $y++) {
							$url = $urls[2][$y];

							// File extension
							$ext = $this->module('mime type')->file2ext($url);

							// External files.
							if ($this->parent()->external($url)) {

								// Whitelisted CDNs (these files should already be cached from other websites)
								if (strpos($url, '.gstatic.com/') === false) {
									array_push($dependencies, $url);
									$cache_module->update($url, $ext, true);

									// Replace the uncompressed, non-static link with the newly generated one.
									$contents = str_replace($urls[0][$y], 'url("' . $cache_module->url($url, $ext) . '")', $contents);
									$this->debug(array('Replaced external CSS dependency with cache URL.', $url));
								}
								else
									$this->debug(array('Not caching whitelisted CDN.', $url));
							}

							// Local files.
							else {

								// Direct CSS may contain file references from the current working directory.
								// Otherwise, the files are located relative to the CSS file itself.
								$dirname = dirname($css[$x]);
								if (!is_dir($dirname))
									$dirname = getcwd();
								$url = $dirname . '/' . $url;

								// If we can find the file being referenced,
								if (file_exists($url)) {
									array_push($dependencies, $url);

									// If it isn't already cached, cache and compress this file.
									$cache_module->update($url, $ext, true);

									// Replace the uncompressed, non-static link with the newly generated one.
									$contents = str_replace($urls[0][$y], 'url("' . $cache_module->url($url, $ext) . '")', $contents);
									$this->debug('Replaced ' . $url . ' local CSS dependency with cache URL.');
								}
								else
									$this->debug(array('Cannot find CSS URL in scope ' . $media . ' / ' . $scope . '.', $url));
							}
						}
						array_push($concat, $contents);
					}

					// Compress & Cache
					$this->debug('Caching CSS scope ' . $media . ' / ' . $scope . '.');
					$cache_module->store(
						$css, implode(PHP_EOL, $concat),
						array(
							'dependencies' => $dependencies,
							'ext'          => 'css'
						)
					);
				}

				// Get the CSS to check for inline.
				$cache_filepath = $cache_module->filepath($css, 'css');
				$compressed_css = file_get_contents($cache_filepath);

				// Long CSS is linked to a file.
				if (strlen($compressed_css) > $this->inline_byte_limit)
					$html .=
						$this->module('html')->element(
							'link',
							array(
								'href'  => $cache_module->url($css, 'css'),
								'id'    => $this->parent()->debugEnabled() ? 'css-' . $media . '-' . $scope : false,
								'media' => $media,
								'rel'   => 'stylesheet',
								'type'  => 'text/css'
							)
						);

				// Short CSS is included directly.
				else {
					if (!array_key_exists($media, $inline))
						$inline[$media] = array();
					array_push($inline[$media], /*'/* ' . $this->cacheFilename($css) . ' *' . '/ ' . */$compressed_css);
				}
			}
		}

		// inline CSS
		foreach ($inline as $media => $css) {
			// sort($css); // relic of v1, don't remember why it's here
			$html .=
				$this->module('html')->element(
					'style',
					array(
						'media' => $media,
						'type'  => 'text/css'
					),
					implode('', $css)
				);
		}
		return $html;
	}



	// Shortcuts
	public function    all($css, $scope = 'global') { return $this->add($css, 'head', 'all',    $scope); }
	// public function  print($css, $scope = 'global') { return $this->add($css, 'foot', 'print',  $scope); }
	public function screen($css, $scope = 'global') { return $this->add($css, 'head', 'screen', $scope); }
	public function speech($css, $scope = 'global') { return $this->add($css, 'foot', 'speech', $scope); }

	public function foot($css, $media = 'all', $scope = 'global') { return $this->add($css, 'foot', $media, $scope); }
	public function head($css, $media = 'all', $scope = 'global') { return $this->add($css, 'head', $media, $scope); }

}

?>