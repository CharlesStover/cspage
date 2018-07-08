<?php

// CSPage CSS
trait CSPageCSS {

	private
		$css_inline_byte_limit = 1024,                    // number of bytes below which we don't bother writing to file
		$css_queue             = array(),                 // CSS files to concatenate before output
		$css_vars              = array(array(), array()); // CSS variables, @var: #value



	// Add CSS to the queue.
	public function css($css, $media = 'all', $scope = 'global') {

		// Prepare the media type.
		if (!array_key_exists($media, $this->css_queue))
			$this->css_queue[$media] = array();

		// Prepare the scope.
		if (!array_key_exists($scope, $this->css_queue[$media]))
			$this->css_queue[$media][$scope] = array();

		// Queue this file.
		$local = getcwd() . '/' . $css;
		array_push(
			$this->css_queue[$media][$scope],
			file_exists($local) ? $local : $css
		);
		return $this;
	}



	// Output the CSS as HTML. <link />
	public function cssHtml() {
		$html = '';
		$inline = array();

		// For each media (all, print, screen, ...) in the queue,
		foreach ($this->css_queue as $media => $scopes) {

			// For each scope (single, multiple, global, ...) in the media,
			foreach ($scopes as $scope => $css) {
				$scope_mod_time = 0;

				// For each CSS file in this scope,
				$count_css = count($css);
				for ($x = 0; $x < $count_css; $x++) {

					// Check external files for cache/expiration.
					if (preg_match('/^.+\:\/\/.+$/', $css[$x])) {

						// If it hasn't been cached or has expired, update it.
						if (
							!$this->cached($css[$x], 'css') ||
							$this->expired($css[$x], 'css')
						) {
							$download = $this->download($css[$x]); // 0 => contents, 1 => expiration
							$this->cache($css[$x], $this->compress($download[0], 'css'), 'css', $download[1]);
							$scope_mod_time = time();
						}
					}

					// Check local files for updates.
					else
						$scope_mod_time = max($scope_mod_time, filemtime($css[$x]));
				}

				// If the cache doesn't exist, or the dependencies have been updated, or the scope has been modified since last cache.
				if (
					!$this->cached($css, 'css') ||
					$this->expired($css, 'css') ||
					$this->cachemtime($css, 'css') < $scope_mod_time
				) {
					$this->debug(
						'CSS scope ' . $scope . ' outdated: ' .
						(
							!$this->cached($css, 'css') ?
							'cache does not exist (' . $this->cacheFilename($css, 'css') . ')' :
							'modified on ' . date('Y-m-d H:i:s', $scope_mod_time) . ' or dependency is outdated.'
						)
					);

					// Cancatenate this scope.
					$concat_css   = array();
					$dependencies = array();
					for ($x = 0; $x < $count_css; $x++) {

						// Turn external file to local file.
						if (preg_match('/^.+\:\/\/.+$/', $css[$x]))
							$local = file_get_contents($this->cacheFilepath($css[$x], 'css'));

						else
							$local = file_exists($css[$x]) ?
								file_get_contents($css[$x]) :
								$css[$x];

						// Grab external file references to store them in the static directory.
						preg_match_all('/url\((\'|\")?([^\'\"]+?)\1\)/', $local, $urls);
						$count_urls = count($urls[2]);
						for ($y = 0; $y < $count_urls; $y++) {
							$url = $urls[2][$y];

							// File extension
							$ext = explode('.', $url);
							if (count($ext)) {
								$ext = $ext[count($ext) - 1];

								// Too long of an extension may be part.of-the-filepath
								// Also coincides with .htaccess in /cache/ directory which redirects to the file.
								if (strlen($ext) > 5)
									$ext = false;
							}
							else
								$ext = false;

							// Only cache/compress local files.
							if (strpos($url, '//')) {

								// Whitelisted CDNs
								if (strpos($url, '.gstatic.com/') === false) {
									array_push($dependencies, $url);
									$this->downloadCacheNew($url, $ext);

									// Replace the uncompressed, non-static link with the newly generated one.
									$local = str_replace($urls[0][$y], 'url("' . $this->cacheUrl($url, $ext) . '")', $local);
								}
							}
							else {
								$url = dirname($css[$x]) . '/' . $url;

								// If we can find the file being referenced,
								if (file_exists($url)) {
									array_push($dependencies, $url);

									// If it isn't already cached, cache and compress this file.
									$this->cacheNew($url, $url, $ext);

									// Replace the uncompressed, non-static link with the newly generated one.
									$local = str_replace($urls[0][$y], 'url("' . $this->cacheUrl($url, $ext) . '")', $local);
								}
								else
									$this->debug('Cannot find CSS URL: ' . $url);
							}
						}
						array_push($concat_css, $local);
					}

					// Check for and replace CSS Variables.
					$concat_css = $this->cssVars(implode('', $concat_css));

					// Compress & Cache
					$this->debug('Caching CSS scope ' . $scope . ': ' . implode(', ', $css));
					$this->cache($css, $this->compress($concat_css, 'css'), 'css', false, $dependencies);
				}

				// Get the CSS to check for inline.
				$cache_filepath = $this->cacheFilepath($css, 'css');
				$compressed_css = file_get_contents($cache_filepath);
				if (strlen($compressed_css) > $this->css_inline_byte_limit)
					$html .=
						$this->htmlElement(
							'link',
							array(
								'href'  => $this->cacheUrl($css, 'css'),
								'id'    => $this->debug_enabled ? 'css-' . $media . '-' . $scope : false,
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
			sort($css);
			$html .=
				$this->htmlElement(
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



	// Store CSS Variables from a file to be used later, or replace all CSS Variables in a file with their value.
	public function cssVars($file) {

		// Get the CSS variables from the file.
		preg_match_all(
			'/(\@[\d\w\-]+)\:\s*(.+?)\;/',
			file_exists($file) ?
			file_get_contents($file) :
			$file,
			$css_vars
		);

		// Store them if it was sent by file name ($page->cssVars).
		if (file_exists($file)) {
			$this->css_vars[0] = array_merge($this->css_vars[0], $css_vars[1]);
			$this->css_vars[1] = array_merge($this->css_vars[1], $css_vars[2]);
			return $this;
		}

		return

			// Replace @var with #value for all global CSS vars and the ones just found.
			str_replace(
				array_merge($this->css_vars[0], $css_vars[1]),
				array_merge($this->css_vars[1], $css_vars[2]),

				// Remove all instances of @var: #value.
				str_replace($css_vars[0], '', $file)
			);
	}



	// Add a Google Font.
	public function GoogleFont($google_font, $media = 'all', $scope = 'multiple') {
		$this->css(
			'https://fonts.googleapis.com/css?family=' . str_replace(' ', '+', $google_font) .
			(array_key_exists('HTTP_USER_AGENT', $_SERVER) ? '&HTTP_USER_AGENT=' . rawurlencode($_SERVER['HTTP_USER_AGENT']) : ''),
			$media, $scope
		);
		return $this;
	}



	// Add multiple Google Fonts.
	public function GoogleFonts($google_fonts, $media = 'all', $scope = 'multiple') {
		foreach ($google_fonts as $google_font)
			$this->GoogleFont($google_font, $media, $scope);
		return $this;
	}

}

?>