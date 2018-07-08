<?php

// CSPage JavaScript
trait CSPageJS {

	private
		$ads_by_google        = array(),
		$ads_by_google_scope  = 'single', // scope for adsByGoogle()
		$js_inline_byte_limit = 1024,     // number of bytes below which we don't bother writing to file
		$js_queue             = array();  // JS files to be output.



	// Ads by Google
	public function adsByGoogle($key = false, $value = null, $scope = 'single') {

		// Alternative form: adsByGoogle(array('key' => 'value'))
		if (is_array($key)) {
			foreach ($key as $k => $v)
				$this->adsByGoogle($k, $v, $value); // $value is $scope when $key=>value are paired in an array
			return $this;
		}

		// Include the ad library once.
		if (empty($this->ads_by_google))
			$this->set(
				'head-adsbygoogle',
				$this->htmlElement(
					'script',
					array(
						'async' => true,
						'src'   => '//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
						'type'  => 'text/javascript'
					),
					''
				)
			);
			/*$this->js(
				'http://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
				'head-async',
				$scope
			);*/

		if ($key)
			$this->ads_by_google[$key] = $value;

		$this->ads_by_google_scope = $scope;
		return $this;
	}



	// Google Analytics
	public function analytics($id) {
		$this->debug('Google Analytics: ' . $id);
		return $this->js(
			'(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,"script","https://www.google-analytics.com/analytics.js","ga");
			ga("create", "' . $id . '", "auto");
			ga("send", "pageview");',
			'body',
			'multiple'
		);
	}



	// JS file
	public function js($js, $loc = 'body', $scope = 'global') {

		// Create the scope if it doesn't exist.
		if (!array_key_exists($loc, $this->js_queue))
			$this->js_queue[$loc] = array();
		if (!array_key_exists($scope, $this->js_queue[$loc]))
			$this->js_queue[$loc][$scope] = array();

		// Queue this file.
		$local = getcwd() . '/' . $js;
		array_push(
			$this->js_queue[$loc][$scope],
			file_exists($local) ? $local : $js
		);
		return $this;
	}



	// JS HTML to output
	public function jsHtml($loc = 'body') {
		if (
			$loc == 'body' &&
			!empty($this->ads_by_google)
		)
			$this->js(
				'(adsbygoogle = window.adsbygoogle || []).push(' . json_encode($this->ads_by_google) . ');',
				'body',
				$this->ads_by_google_scope
			);

		// No scripts exist in this location.
		if (!array_key_exists($loc, $this->js_queue))
			return '';

		$html = '';
		$inline = array();

		// For each scope (global, multiple, single, ...) in the queue,
		foreach ($this->js_queue[$loc] as $scope => $js) {
			$scope_mod_time = 0;

			// For each JS file in this scope,
			$count_js = count($js);
			for ($x = 0; $x < $count_js; $x++) {

				// Check external files for cache/expiration.
				if (preg_match('/^.+\:\/\/.+$/', $js[$x])) {

					// If it hasn't been cached or has expired, update it.
					if (
						!$this->cached($js[$x], 'js') ||
						$this->expired($js[$x], 'js')
					) {
						$download = $this->download($js[$x]); // 0 => contents, 1 => expiration
						$this->cache($js[$x], $this->compress($download[0], 'js'), 'js', $download[1]);
						$scope_mod_time = time();
					}
				}

				// Check local files for updates.
				else
					$scope_mod_time = max($scope_mod_time, filemtime($js[$x]));
			}

			// If the cache doesn't exist, or the dependencies have been updated, or the scope has been modified since last cache.
			if (
				!$this->cached($js, 'js') ||
				$this->expired($js, 'js') ||
				$this->cachemtime($js, 'js') < $scope_mod_time
			) {
				$this->debug(
					'JS scope ' . $loc . ' ' . $scope . ' outdated: ' .
					(
						!$this->cached($js, 'js') ?
						'cache does not exist (' . $this->cacheFilename($js, 'js') . ')' :
						'modified on ' . date('Y-m-d H:i:s', $scope_mod_time) . ' or dependency is outdated.'
					)
				);

				// Cancatenate this scope.
				$concat_js = array();
				for ($x = 0; $x < $count_js; $x++)
					array_push(
						$concat_js,
						preg_match('/^.+\:\/\/.+$/', $js[$x]) ?
						file_get_contents($this->cacheFilepath($js[$x], 'js')) :
						(
							file_exists($js[$x]) ?
							file_get_contents($js[$x]) :
							$js[$x]
						)
					);
				$concat_js = implode('', $concat_js);

				// Compress & Cache
				$this->debug('Caching JS scope ' . $loc . ' ' . $scope . ': ' . implode(', ', $js));
				$this->cache($js, $this->compress($concat_js, 'js'), 'js');
			}

			// Get the JS to check for inline.
			$cache_filepath = $this->cacheFilepath($js, 'js');
			$compressed_js = file_get_contents($cache_filepath);
			if (strlen($compressed_js) > $this->js_inline_byte_limit)
				$html .=
					$this->htmlElement(
						'script',
						array(
							'async' => substr($loc, -5) == 'async',
							'id'    => $this->debug_enabled ? 'js-' . $loc . '-' . $scope : false,
							'src'   => $this->cacheUrl($js, 'js'),
							'type'  => 'text/javascript'
						),
						''
					);

			// Short JS is included directly.
			else
				array_push($inline, /*'/* ' . $this->cacheFilename($js) . ' *' . '/ ' . */$compressed_js);
		}

		// inline JS
		if (!empty($inline))
			$html .=
				$this->htmlElement(
					'script',
					array(
						'type' => 'text/javascript'
					),
					implode('', $inline)
				);

		return $html;
	}

	public function prettify($scope = 'multiple') {
		return $this->js('http://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js', 'body', $scope);
	}
}

?>