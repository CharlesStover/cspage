<?php

// add append and prepend for set

// CSPage Setters
trait CSPageSetters {

	// link apple-touch-icon
	public function appleTouchIcons($icons) {
		if (is_array($icons)) {
			$html = '';
			foreach ($icons as $key => $icon)
				$html .=
					$this->htmlElement(
						'link',
						array(
							'href'  => $icon,
							'rel'   => 'apple-touch-icon',
							'sizes' => preg_match('/^\d+$/', $key) ? $key : false
						)
					);
		}
		else
			$html =
				$this->htmlElement(
					'link',
					array(
						'href' => $icons,
						'rel' => 'apple-touch-icon'
					)
				);
		return $this->set('head-appleTouchIcons', $html);
	}



	// <body>
	public function body($file) {

		// Check if $file is a string or a file name.
		// File name checks can't include \0, or else they give an error.
		$body = strpos($file, "\0") === false &&
			file_exists($file) ?
			file_get_contents($file) :
			$file;

		// Get <title> tag.
		if (preg_match('/\<title\>(.*?)\<\/title\>/', $body, $match)) {
			$this->set('title', $match[1]);
			$body = str_replace($match[0], '', $body);
		}

		// Get <meta /> tags.
		if (preg_match_all('/\<meta name=\"(.*?)\"\s*content=\"(.*?)\"(?: ?\/)?>/', $body, $matches)) {
			$count_matches = count($matches[1]);
			for ($x = 0; $x < $count_matches; $x++) {
				$this->meta($matches[1][$x], $matches[2][$x]);
				$body = str_replace($matches[0][$x], '', $body);
			}
		}

		// Prepare <body>
		$body = trim($body);
		if (!preg_match('/^\<body/', $body))
			$body = $this->htmlElement('body', false, $body);
		return $this->set('body', $body);
	}



	// cache duration
	public function cacheDuration($seconds) {
		$this->external_cache_duration = $seconds;
		return $this;
	}



	// copyright
	public function copyright($year, $owner = false) {
		$date_Y = date('Y');
		return $this->set(
			'copyright',
			'&copy; ' .
				$year .
				($date_Y > $year ? '-' . $date_Y : '') .
				($owner ? ' ' . $owner : '')
		);
	}



	// meta description
	public function description($desc) {
		return $this->meta('description', $desc);
	}



	// favicon   <link rel="icon" />
	public function favicon($href, $type = false) {
		if (!$type)
			$type = $this->getMimeType($href);

		// If it's a local file, cache it to the static subdomain.
		$local = getcwd() . '/' . $href;
		if (
			!preg_match('/favicon\.ico$/', $href) &&
			file_exists($local)
		) {
			preg_match('/^image\/(\w+)$/', $type, $ext);
			$ext = $ext ? $ext[1] : 'ico';
			$this->cacheNew($local, $local, $ext);
			$href = $this->cacheUrl($local, $ext);
		}
		return $this->set(
			'head-favicon',
			$this->htmlElement(
				'link',
				array(
					'href' => $href,
					'rel'  => 'icon',
					'type' => $type ? $type : false
				)
			)
		);
	}



	// Return HTML markup
	public function htmlElement($element, $attributes, $content = false) {
		$html = '<' . $element;
		if (is_array($attributes)) {
			foreach ($attributes as $attribute => $value) {
				if ($value !== false)
					$html .= ' ' . $attribute . ($value === true ? '' : '="' . $value . '"');
			}
		}
		return $html . ($content === false ? ' />' :  '>' . $content . '</' . $element . '>');
	}



	// meta keywords
	public function keywords($k) {

		// Convert arrays to CSL.
		return $this->meta(
			'keywords',
			is_array($k) ?
			implode(
				', ',

				// Filter empty strings and null values from the array.
				array_filter($k)
			) :
			$k
		);
	}



	// <meta />
	public function meta($name, $content) {
		return $this->set(array('meta', $name), $content);
	}



	// HTML variable setter
	public function set($key, $value, $temp = false) {
		$keys = explode('-', is_array($key) ? $key[0] : $key);
		if (is_array($key))
			array_push($keys, $key[1]);
		$html_var = &$this->html_vars;
		foreach ($keys as $k) {
			if (!array_key_exists($k, $html_var))
				$html_var[$k] = array();
			$html_var = &$html_var[$k];
		}
		$html_var = $value;
		return $this;
	}



	// meta theme-color
	public function themeColor($color) {
		return $this
			->meta('msapplication-navbutton-color', $color)
			->meta('theme-color', $color);
	}



	// <title>
	public function title($title) {
		return $this->set('title', $title);
	}

}

?>