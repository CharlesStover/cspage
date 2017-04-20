<?php

class CSPage_Html extends CSPage_Module {

	private
		// Regular Expression for ATTRIBUTE="value"
		// *? means not to include data- if data- is explicitly listed afterward.
		// This allows for if-html_var-data-x="" matching if-$attr_var_regex-data-$attr_var_regex
		$attr_var_regex = '[\d\w]+(?:\-[\d\w]+)*',
		$doctype        = 'html',
		$var_divider    = '-',
		$vars           = array();

	// Constructor
	public function __construct($index = 0, $parent = null) {

		// Viewport
		return $this->set(array('meta', 'viewport'), 'width=device-width, initial-scale=1.0');
	}



	// Regular Express for attribute="VALUE"
	// $b is the back reference # for the quotation mark
	// +1 for type|"value" if type and return;   +1 for quote;   +1 for value if return
	private function attrValRegex($b, $options = array()) {

		// Single-option
		if (!is_array($options))
			$options = array($options);
		return '(?:\=(' .
			(
				in_array('return', $options) &&
				in_array('type',   $options) ?
				'' :
				'?:'
			) .
			(in_array('type', $options) ? 'ARRAY|NULL|' : '') .
			'([\\\'\\"])' .
				(in_array('return', $options) ? '(' : '') .
				'(?:(?!\\g' . $b . ').)*' .
				(in_array('return', $options) ? ')' : '') .
			'\\g' . $b .
		'))?';
	}



	/**
	* 
	* Gets or sets the HTML in the <body>.
	*
	* @param string $html Either a file path or direct HTML for the <body>'s contents.
	* @return self
	*/
	public function body($html = null) {

		// Get <body>.
		if (is_null($html)) {
			$body   = $this->get('body');
			$foot = $this->hook('foot');
			return $body ?
				str_replace(
					'</body>',
					$foot . '</body>',
					$body
				) :
				$this->element('body', null, $foot);
		}

		// Set <body>.
		// Check if $html is a string or a file name.
		// File name checks can't include \0, or else they give an error.
		if (
			strpos($html, "\0") === false &&
			file_exists($html)
		) {
			$this->debug('Setting <body> via file contents: ' . $html);
			$html = file_get_contents($html);
		}
		else
			$this->debug('Setting body with direct HTML.');

		// Prepare <body>
		$html = trim($html);
		if (!preg_match('/^\<body/', $html))
			$html = $this->element('body', null, $html);
		return $this->set('body', $html);
	}



	/**
	* 
	* Sets the page's meta description.
	*
	* @param string $description the meta description for the page
	* @return self
	*/
	public function description($description) {
		return $this->meta('description', $description);
	}



	// Get or set the <!DOCTYPE>
	public function docType($doctype = null) {

		// Get the DOCTYPE
		if (is_null($doctype))
			return '<!DOCTYPE ' . $this->doctype . '>';
		$this->doctype = $doctype;
		return $this;
	}



	// Create HTML markup for an element.
	public function element($element, $attributes = null, $content = null) {

		// opening tag
		$html = '<' . $element;

		// array('class' => 'name')
		if (is_array($attributes)) {
			foreach ($attributes as $attribute => $value) {
				if (
					!is_null($value) &&
					$value !== false
				)
					$html .= ' ' . $attribute . ($value === true ? '' : '="' . $value . '"');
			}
		}

		// class="name"
		else if (!is_null($attributes))
			$html .= ' ' . $attributes;

		// closing tag
		return $html . (is_null($content) ? ' />' :  '>' . $content . '</' . $element . '>');
	}



	/**
	* 
	* Sets the favicon for the page.
	*
	* @param string $href the path to the favicon file
	* @param string $type the file type of the favicon file
	* @return self
	*/
	public function favicon($href, $type = null) {
		if (is_null($type))
			$type = $this->module('mime type')->file2type($href);
		$ext = is_null($type) ? null : $this->module('mime type')->type2ext($type);

		// Cache it to the static subdomain.
		$href = $this->module('cache')
			->update($href, $ext, true)
			->url($href);

		// Convert to <link /> and place in <head>.
		return $this
			->debug(array('Setting favicon.', $href . ' (' . $type . ')'))
			->set(
				array('head', 'favicon'),
				$this->element(
					'link',
					array(
						'href' => $href,
						'rel'  => 'icon',
						'type' => $type ? $type : false
					)
				)
			);
	}



	// Get an HTML variable.
	public function get($key, $default = null) {

		// Convert array('x', 'y', 'z') to $vars['x']['y']['z']
		if (is_array($key)) {
			$vars = $this->vars;
			do {
				$index = array_shift($key);

				// 'x' => $vars['x']
				if (
					is_array($vars) &&
					array_key_exists($index, $vars)
				)
					$vars = $vars[$index];

				// Variable at this index does not exist.
				else {
					// trigger_error('Variable {{' . (is_array($key) ? implode('.', $key) : $key) . '}} does not exist.', E_WARNING);
					return $default;
				}
			}
			while (count($key));
			return $vars;
		}
		return array_key_exists($key, $this->vars) ? $this->vars[$key] : $default;
	}



	/**
	* 
	* Gets or sets the HTML in the <head>.
	*
	* @param string $html Either a file path or direct HTML for the <head>'s contents.
	* @return self
	*/
	public function head($html = null) {

		// Get <head>
		if (is_null($html))
			return
				$this->element(
					'head', null,
					$this->element(
						'foreach',
						array('head' => true),
						'{{head-value}}'
					) .
					$this->element(
						'foreach',
						array('meta' => true),
						$this->element(
							'meta',
							array(
								'name'    => '{{meta-key}}',
								'content' => '{{meta-value}}'
							)
						)
					) .
					$this->hook('head')
				);

		// Set <head>
		// Check if $html is a string or a file name.
		// File name checks can't include \0, or else they give an error.
		if (
			strpos($html, "\0") === false &&
			file_exists($html)
		) {
			$this->debug('Setting <head> via file contents: ' . $html);
			$html = file_get_contents($html);
		}
		else
			$this->debug('Setting <head> via direct HTML.');

		// Make sure <title> tag is not set twice (head.html and title()).
		while (preg_match('/\<title\>(.*?)\<\/title\>/', $html, $match)) {
			$html = str_replace($match[0], '', $html);
			$this->debug('Scraping title from HTML.');
			$this->title($match[1]);
		}

		// Make sure <meta /> tags are not set twice (head.html and meta()).
		if (preg_match_all('/\<meta\s+name=\"(.*?)\"\s*content=\"(.*?)\"(?: ?\/)?>/', $html, $matches)) {
			$count_matches = count($matches[1]);
			for ($x = 0; $x < $count_matches; $x++) {
				$html = str_replace($matches[0][$x], '', $html);
				$this->meta($matches[1][$x], $matches[2][$x]);
				$this->debug(array('Scraping meta data from HTML.', $matches[1][$x] . ' => ' . $matches[2][$x]));
			}
		}
		return $this->set(array('head', 'html'), $html);
	}



	/**
	* 
	* Gets or sets the HTML in the <body>.
	*
	* @param string $keywords either a string or array of keywords
	* @return self
	*/
	public function keywords($keywords) {

		// Convert arrays to CSL.
		return $this->meta(
			'keywords',
			is_array($keywords) ?

			// Filter empty strings and null values from the array.
			implode(', ', array_filter($keywords)) :
			$keywords
		);
	}



	// <meta />
	public function meta($name, $content) {
		return $this
			->debug(array(
				'Setting meta.',
				$name . ' => ' . $content
			))
			->set(
				array('meta', $name),
				$content
			);
	}



	// Output
	public function output($return_only = false) {
		$this->debug('Generating HTML.');

		// Don't display HTML if there are errors on a development machine.
		if (
			$this->parent()->devMachine() &&
			$this->parent()->countErrors()
		)
			return $this->parent()->outputErrors($return_only);

		$cache_module   = $this->module('cache');
		$vars_json = json_encode($this->vars);

		// If we have already cached this page, display the cache.
		if (
			!$cache_module->disabled('html') &&
			!$cache_module->expired($vars_json, 'html', true)
		) {
			$cache_filepath = $cache_module->filepath($vars_json, 'html');

			// Set and check the ETag
			if (!$return_only)
				$cache_module->eTag($cache_filepath);

			// Output the HTML from the cache file instead of generating it.
			$this->debug(array('HTML loaded from cache.', $cache_filepath));
			$html = file_get_contents($cache_filepath);
		}

		// If we don't have a cached version of this page, generate the optimized HTML.
		else {

			// Template
			$html = $this->docType() .
				$this->element(
					'html',
					array('lang' => 'en'),
					$this->head() .
					$this->body()
				);

			// Custom markup.
			$html = $this->parseForeach($html);
			$html = $this->parseIf($html);
			$html = $this->parseAttributes($html);

			// HTML Variables
			$this->debug('Replacing HTML variables.');
			$var_divider_regex = preg_quote($this->var_divider, '/');

			// Replace {{array-key}} and {{array-value}} if "key/value" are not actual indexes.
			preg_match_all('/{{(' . $this->attr_var_regex . ')' . $var_divider_regex . '(key|value)}}/', $html, $matches);
			$count_matches = count($matches[0]);
			for ($x = 0; $x < $count_matches; $x++) {
				$var = explode($this->var_divider, $matches[1][$x]);
				array_push($var, $matches[2][$x]);
				if (is_null($this->get($var))) {

					// Replace {{path-to-variable-key}} with "variable"
					if ($matches[2][$x] == 'key') {
						$replace = explode($this->var_divider, $matches[1][$x]);
						$replace = array_pop($replace);
					}

					// Replace {{path-to-variable-value}} with {{path-to-variable}}
					else
						$replace = '{{' . $matches[1][$x] . '}}';
					$html = str_replace($matches[0][$x], $replace, $html);
				}
			}

			// Replace the HTML variables with their values.
			preg_match_all('/{{(' . $this->attr_var_regex . ')' . $var_divider_regex . 'value}}/', $html, $matches);
			$count_matches = count($matches);
			do {
				$before = $html;
				$this->varsIterator(
					$this->vars,

					// Foreach HTML Variable, replace {{VAR}} with value.
					function($keys, $value, $ret) {
						return str_replace('{{' . implode($this->var_divider, $keys) . '}}', $value, $ret);
					},

					$html
				);
				$after = $html;
			}
			while ($before != $after);

			// If caching HTML isn't disabled, cache our result.
			if (!$cache_module->disabled('html'))
				$cache_module->store($vars_json, $html, 'html');
		}

		$this->debug(($return_only ? 'Returning' : 'Outputting') . ' HTML.');

		// Headers only if we are outputting.
		if (!$return_only) {
			header('Content-Language: en-us');
			$this->parent()->contentType('text/html');
		}

		// gzip if we are outputting and not debugging.
		ob_start(
			$return_only ||
			$this->parent()->debugEnabled() ?
			null :
			'ob_gzhandler'
		);

		// Replace these variables post-cache so that they don't each get their own cache file.
		echo $this->module('compress')->html(str_replace(
			array('{{ENCODED_REQUEST_URI}}',                 '{{HTTP_HOST}}',       '{{REQUEST_URI}}'),
			array(htmlspecialchars($_SERVER['REQUEST_URI']), $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']),
			$html
		));

		// Debug information.
		if ($this->parent()->debugEnabled())
			echo
				PHP_EOL, '<!--', PHP_EOL, PHP_EOL,
				$this->parent()->outputErrors(true),
				$this->hook('debug'),
				PHP_EOL, PHP_EOL, '-->';

		// Return the HTML.
		if ($return_only) {
			$ogc = ob_get_contents();
			ob_end_clean();
			return $ogc;
		}

		// Output the HTML.
		ob_end_flush();
		return $this;
	}



	// Replace <element if-variable-attr="value"> with <element attr="value">
	public function parseAttributes($html) {
		$this->debug('Parsing if/else attributes.');

		// For each if/else attribute, replace it.
		while (
			preg_match(

				// <element if-var-attribute="value">
				'/\<\w+(?:\s+' . $this->attr_var_regex . $this->attrValRegex(1) . ')*' .
				'(\s+(else|if)\-(' . $this->attr_var_regex . ')' . $this->attrValRegex(5, 'return') . ')' .
				'(?:\s+' . $this->attr_var_regex . $this->attrValRegex(7) . ')*\>/',
				$html,
				$matches
			)
		) {
			$statement  = $matches[2];
			$if_else    = $matches[3];
			$attr       = $matches[4];
			$attr_quote = $matches[5];
			$value      = $matches[6];

			// If ? isn't used after attr_var_regex, then it thinks -data- is a part of the variable name.
			// Questionable: Using *? to match as few hyphens as possible, then manually defining attributes that contain hyphens (only data?), is the best option?
			// Possible alternative solution: explode('data')?
			preg_match('/^(' . $this->attr_var_regex . '?)\-((?:data\-)?[\d\w]+)$/', $attr, $attr);
			$attr_var = $attr[1];
			$attr     = $attr[2];

			$replace =
				($if_else == 'if'   &&  $this->get($attr_var)) ||
				($if_else == 'else' && !$this->get($attr_var));

			$this->debug(array(
				'Generating attribute for variable.',
				'if (' . ($if_else == 'else' ? '!' : '') . $this->parent()->truncate($attr_var, 15) . ') ' .
				$attr . ' = ' . $this->parent()->truncate($value, 15) . ' (' . ($replace ? 'true' : 'false') . ')'
			));

			// Replace <element if-variable-attr="value"> with <element attr="value"> or <element>
			$html = str_replace(
				$matches[0],
				str_replace(
					$statement,
					$replace ?
					' ' . $attr . '=' . $attr_quote . $value . $attr_quote :
					'',
					$matches[0]
				),
				$html
			);
		}
		return $html;
	}



	// Replace <foreach> HTML variables.
	private function parseForeach($html) {
		$this->debug('Parsing <foreach> tags.');
		$var_divider_regex = preg_quote($this->var_divider, '/');

		// While <foreach>s exist, replace them with repeated HTML.
		while (preg_match('/\<foreach\s+(' . $this->attr_var_regex . ')' . $this->attrValRegex(2, 'return') . '\>(.*)\<\/foreach\>/s', $html, $matches)) {
			$attr_var   = $matches[1]; // <foreach VAR="val">
			$attr_quote = $matches[2]; // ' or "
			$attr_value = $matches[3]; // <foreach var="VAL">
			$innerHTML  = $matches[4]; // <foreach>innerHTML</foreach>

			// Clean up.
			$var       = $this->get($attr_var);
			$tag_html  = $this->tagShift('foreach', $matches[0], $innerHTML); // Removing anything after the ending of this particular <foreach>.
			$outerHTML = $tag_html[0];
			$innerHTML = $tag_html[1];

			if (!is_array($var) || empty($var)) {
				$this->debug(array('Generating HTML for each ' . $attr_var . '.', 'zero children'));
				$outerHTML_new = '';
			}
			else {
				$this->debug(array('Generating HTML for each ' . $attr_var . '.', count($var) . ' children'));

				// Prepare the variables.
				$var_regex = preg_quote($attr_value == null ? $attr_var : $attr_value, '/');

				// Generate the new HTML.
				$outerHTML_new = '';
				foreach ($var as $key => $value) {

					// The HTML for this element of the array.
					$innerHTML_new = $innerHTML;

					// {{array-value}} => each array item's value
					//$vars_temp[$attr_var . $this->var_divider . $key . $this->var_divider . 'key']   = $key;
					//$vars_temp[$attr_var . $this->var_divider . $key . $this->var_divider . 'value'] = $value;

					// Find all <if> tags, so that we can check if this variable is within its attributes.
					preg_match_all(
						'/\<if(?:\s+' . $this->attr_var_regex . $this->attrValRegex(1, 'type') . ')+\>/',
						$innerHTML_new, $ifs
					);

					// For each <if>, replace {{variable}} with {{path-to-variable-key}}.
					$count_ifs = count($ifs[0]);
					for ($x = 0; $x < $count_ifs; $x++)
						$innerHTML_new = str_replace(
							$ifs[0][$x],
							preg_replace(
								'/\s+' . $var_regex . '((?:' . $var_divider_regex . $this->attr_var_regex . ')?' . $this->attrValRegex(2, 'type') . ')/s',
								' ' . $attr_var . $this->var_divider . $key . '$1',
								$ifs[0][$x]
							),
							$innerHTML_new
						);

					// Replace if-{{variable}}-attribute with if-{{path-to-variable-key}}-attribute
					// While loop in case the element has multiple if-{{variable}}-attributes.
					do {
						$previous = $innerHTML_new;
					}
					while (
						($innerHTML_new = preg_replace(

							// $1 - tag name
							'/\<(\w+)' .

							// $2 - attributes before if/else
							// $3 - quote backreference
							'(\s+' . $this->attr_var_regex . $this->attrValRegex(3) . ')*' .

							// $4 - start of if/else attribute
							'(\s+(?:else|if)\-)' .

							$var_regex .

							// $5 - end of if/else attribute
							// $6 - quote backreference for if/else attribute
							// Removed 'type' FROM regex(6) because it doesn't need to be there?
							'(\-' . $this->attr_var_regex . $this->attrValRegex(6) . ')' .

							// $7 - attributes after if/else
							'(\s+' . $this->attr_var_regex . $this->attrValRegex(8) . ')*\>/',

							// Replace with:
							'<$1$2$4' . $attr_var . $this->var_divider . $key . '$5$7>',
							$innerHTML_new
						)) &&
						$innerHTML_new != $previous
					);

					// Replace <foreach {{variable}}> with <foreach {{path-to-variable-key}}>
					$innerHTML_new =
						preg_replace(
							'/\<foreach\s+' . $var_regex . '(' . $var_divider_regex . $this->attr_var_regex . ')?(' . $this->attrValRegex(3) . ')\>/',
							'<foreach ' . $attr_var . $this->var_divider . $key . '$1$2>',
							$innerHTML_new
						);

					// Replace {{variable-subvar}} with {{path-to-variable-key-subvar}}
					$innerHTML_new =
						preg_replace(
							'/{{' . $var_regex . '(' . $var_divider_regex . $this->attr_var_regex . ')?}}/',
							'{{' . $attr_var . $this->var_divider . $key . '$1}}',
							$innerHTML_new
						);

					// Append this altered innerHTML to the grander outerHTML.
					$outerHTML_new .= $innerHTML_new;
				}
			}
			$html = str_replace($outerHTML, $outerHTML_new, $html);
		}
		return $html;
	}



	// Parse custom <if> HTML
	private function parseIf($html) {
		$this->debug('Parsing <if> tags.');

		// While <if>s exist, replace them with HTML.
		while (preg_match('/\<if((?:\s+' . $this->attr_var_regex . $this->attrValRegex(2, 'type') . ')+)\>(.*)\<\/if\>/s', $html, $matches)) {
			$attributes   = $matches[1];
			$attr_quote   = $matches[2];
			$if_innerHTML = $matches[3];

			// Clean up.
			$if_html   = $this->tagShift('if', $matches[0], $if_innerHTML);
			$if_outerHTML = $if_html[0];
			$if_innerHTML = $if_html[1];
			$outerHTML    = $if_outerHTML;

			// Check for <else>content</else>
			preg_match('/' . preg_quote($outerHTML, '/') . '(\s*\<else\>(.*)\<\/else\>)?/s', $html, $else_matches);

			// If <else> exists.
			if ($else_matches[1]) {
				$else_html = $this->tagShift('else', $else_matches[1], $else_matches[2]);
				$else_innerHTML = $else_html[1];
				$else_outerHTML = $else_html[0];

				// Include <else>etc</else> as part of the string to replace.
				$outerHTML .= $else_outerHTML;
			}
			else {
				$else_innerHTML = '';
				$else_outerHTML = '';
			}

			// Get all the variables from the <if> tag.
			preg_match_all('/\s+(' . $this->attr_var_regex . ')' . $this->attrValRegex(3, array('return', 'type')) . '/s', $attributes, $attributes);
			$count_attributes = count($attributes[0]);
			$attr_vars   = $attributes[1];
			$attr_types  = $attributes[2];
			$attr_quotes = $attributes[3];
			$attr_values = $attributes[4];

			// Determine whether to replace it with the innards of <if> or <else>.
			$debug       = array();
			$replacement = true;
			for ($x = 0; $x < $count_attributes; $x++) {
				$var = $this->get($attr_vars[$x]);

				// <if var=ARRAY>
				if ($attr_types[$x] == 'ARRAY') {
					if (!is_array($var))
						$replacement = false;
				}

				// <if var=NULL>
				else if ($attr_types[$x] == 'NULL') {
					if (!is_null($var))
						$replacement = false;
				}

				// <if var>
				else if ($attr_values[$x] == '') {
					if (!$var)
						$replacement = false;
				}

				// <if var="etc">
				else if ($var != $attr_values[$x])
					$replacement = false;

				array_push(
					$debug,
					$this->parent()->truncate($attr_vars[$x], 15) . ' ' . (
						$attr_values[$x] == '' ?
						'exists' :
						'== ' . $attr_types[$x]
					) . ' (' . ($replacement ? 'true' : 'false') . ')'
				);
			}
			$this->debug(array('Generating HTML for if statement.', implode(' && ', $debug)));
			$html = str_replace($outerHTML, $replacement ? $if_innerHTML : $else_innerHTML, $html);
		}
		return $html;
	}



	// Set an HTML variable.
	public function set($keys, $value) {
		if (!is_array($keys))
			$keys = array($keys);
		$count_keys = count($keys);

		// Convert array('x', 'y', 'z') into array('x' => array('y' => array('z' => 'value')))
		$var = $value;
		for ($x = $count_keys - 1; $x >= 0; $x--)
			$var = array($keys[$x] => $var);
		$this->vars = array_merge_recursive($this->vars, $var);
		return $this;
	}



	// Shift the first <tag> off a list of <tag>s.
	private function tagShift($tag, $outer, $inner) {

		// Find the ending of this particular tag, then remove anything after it.
		$outer_tags     = explode('</' . $tag . '>', $outer);
		$inner_tags     = explode('</' . $tag . '>', $inner);
		$closing_tags   = count($inner_tags);
		$tag_outer      = '';
		$tag_inner      = array();

		// Count the number of open tags, and keep appending content until you've reached the same number of closing tags.
		$open = 0;
		for ($x = 0; $x < $closing_tags; $x++) {

			// Use strictly "<else>" when $tag is else? Or allow for <else var="val">?
			preg_match_all(
				$tag == 'else' ?
				'/\<else\>/' :
				(
					$tag == 'foreach' ?
					'/\<foreach\s+' . $this->attr_var_regex . $this->attrValRegex(1) . '\>/' :
					'/\<' . $tag . '(?:\s+' . $this->attr_var_regex . $this->attrValRegex(1) . ')+\>/'
				),
				$inner_tags[$x],
				$subs
			);
			$open += count($subs[0]);

			$tag_outer .= $outer_tags[$x] . '</' . $tag . '>';
			array_push($tag_inner, $inner_tags[$x]);

			if ($open == $x)
				break;
		}
		return array($tag_outer, implode('</' . $tag . '>', $tag_inner));
	}



	// meta theme-color
	public function themeColor($color) {
		return $this
			->debug(array('Setting theme color.', $color))
			->meta('msapplication-navbutton-color', $color)
			->meta('theme-color', $color);
	}



	// <title>
	public function title($title) {
		return $this
			->debug(array('Setting title.', $title))
			->set(
				array('head', 'title'),
				$this->element('title', null, $title)
			);
	}



	/*
	Used to replace all HTML variables with their values.
	iterator(
		nested array,                     // array(test => array(key => value))
		function(array of keys, value),   // array(test, key), value
		return value passed by reference,
		parent keys used internally
	)
	*/
	private function varsIterator($array, $func, &$ret = false, $keys = array()) {
		foreach ($array as $key => $value) {
			$am = array_merge($keys, array($key));
			if (is_array($value))
				$this->varsIterator($value, $func, $ret, $am);
			else
				$ret = $func($am, $value, $ret);
		}
		return $ret;
	}
}

?>
