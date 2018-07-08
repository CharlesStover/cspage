<?php

/*
move CSS media=print to foot of <body>
*/



// Don't define twice.
if (class_exists('CSPage'))
	return;



// Include traits.
foreach (array('Cache', 'Compress', 'CSS', 'DB', 'Getters', 'JS', 'RegExp', 'Setters') as $trait)
	require_once('traits/CSPage' . $trait . '.php');
require_once('traits/MimeTypes.php');



// CSPage
class CSPage {

	use CSPageCache;
	use CSPageCompress;
	use CSPageCSS;
	use CSPageDB;
	use CSPageGetters;
	use CSPageJS;
	use CSPageRegExp;
	use CSPageSetters;
	use MimeTypes;

	private
		$debug_enabled   = false,
		$debug_log       = array(),
		$html_vars       = array(), // HTML Variables, e.g. {{title}}
		$html_vars_temp  = array(), // Temporary HTML Variables, e.g. {{array-value}}
		$localhost       = false,
		$start_microtime = 0;

	public
		$errors    = array(), // Error log
		$version   = 0.1;



	public function __construct() {
		error_reporting(E_ALL);
		set_error_handler(array($this, 'errorHandler'), E_ALL);

		// Start the timer.
		$this->start_microtime = microtime(true);

		// Cache URL: i.domain.com
		$this->cacheGlobal('//i.' . preg_replace('/^(?:i|www)\./', '', $_SERVER['HTTP_HOST']));

		// Check parent directories for the local cache directory.
		if (!is_dir($this->cache_local)) {

			// Set number of iterations to number of / in cache_local?
			for ($x = 0; $x < 16; $x++) {
				if (is_dir(str_repeat('../', $x) . $this->cache_local)) {
					$this->cache_local = str_repeat('../', $x) . $this->cache_local;
					break;
				}
			}
		}

		// Enable debug.
		if (array_key_exists('debug', $_GET))
			$this->debug_enabled = true;

		// Are we on a localhost?
		$this->localhost = preg_match('/^localhost/', $_SERVER['HTTP_HOST']);

		// Viewport
		$this->set('meta-viewport', 'width=device-width, initial-scale=1.0');
		return $this;
	}



	public function __destruct() {
		if ($this->db)
			unset($this->db);
	}



	// debugger
	public function debug($txt) {
		array_push(
			$this->debug_log,
			is_array($txt) ?
			json_encode($txt) :
			$txt
		);
		return $this;
	}



	public function errorHandler($no, $str, $file, $line, $context = null) {
		array_push(
			$this->errors,
			array(
				$file . ' (line ' . $line . '): ',
				$str . ($no === null ? '' : ' (' . $no . ')')
			)
		);
	}



	/*
	iterator(
		nested array,                     // array(test => array(key => value))
		function(array of keys, value),   // array(test, key), value
		return value passed by reference,
		parent keys used internally
	)
	*/
	public function iterator($array, $func, &$ret = false, $keys = array()) {
		foreach ($array as $key => $value) {
			$am = array_merge($keys, array($key));
			if (is_array($value))
				$this->iterator($value, $func, $ret, $am);
			else
				$ret = $func($am, $value, $ret);
		}
		return $ret;
	}



	// output the page
	public function output($return_only = false) {

		// Error Handling
		if (!empty($this->errors)) {
			$count_errors = count($this->errors);

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

			// Filename (line): error
			ob_start(
				$this->debug_enabled ||
				$return_only ?
				null :
				'ob_gzhandler'
			);
			echo $count_errors, ' Error' . ($count_errors != 1 ? 's' : '') . ":\r\n";
			for ($x = 0; $x < $count_errors; $x++)
				echo "\r\n", str_pad($this->errors[$x][0], $max_length) . $this->errors[$x][1];

			if ($return_only) {
				$ogc = ob_get_contents();
				ob_end_clean();
				return $ogc;
			}
			ob_end_flush();
			return $this;
		}

		$this
			->set('body_js',  $this->jsHtml('body'))
			->set('head-css', $this->cssHtml())
			->set('head-js',  $this->jsHtml('head') . $this->jsHtml('head-async'));

		$html_vars_json = json_encode($this->html_vars);
		$cache_filepath = $this->cacheFilepath($html_vars_json, 'html');



		// Delete caches generated before this script was last updated.
		if (
			$this->cached($html_vars_json, 'html') &&
			filemtime(__FILE__) > filemtime($cache_filepath)
		)
			unlink($cache_filepath);



		// If we have already cached this page, and our page generation algorithm has not changed since.
		if (
			!in_array('html', $this->disabled_cache) &&
			$this->cached($html_vars_json, 'html')
		) {

			// Set and check the ETag
			$this->eTag($cache_filepath);

			// Output the HTML from the cache file instead of generating it.
			$this->debug('HTML from cache: ' . $this->cacheFilename($html_vars_json));
			$html = file_get_contents($cache_filepath);
		}



		// Generate the page.
		else {
			$this->debug('Generating HTML.');
			$html = '<!DOCTYPE html>' .
				'<html lang="en">' .
					'<head>' .
						'<title>{{title}}</title>' .
						'<foreach meta>' .
							'<meta name="{{meta-key}}" content="{{meta-value}}" />' .
						'</foreach>' .
						'<foreach head>' .
							'{{head-value}}' .
						'</foreach>' .
					'</head>' .
					str_replace(
						'</body>',
						'{{body_js}}</body>',
						array_key_exists('body', $this->html_vars) ?
						$this->html_vars['body'] :
						''
					) .
				'</html>';



			// While <foreach>s exist, replace them with HTML.
			while (preg_match('/\<foreach\s+(' . $this->attr_var_regex . ')' . $this->attr_val_regex(2, 'return') . '\>(.*)\<\/foreach\>/s', $html, $matches)) {
				$attr_var   = $matches[1];
				$attr_quote = $matches[2];
				$attr_val   = $matches[3];
				$tag_inner  = $matches[4];
				$html_var   = $this->get($attr_var); // The HTML associated with this variable.
				$tag_html   = $this->tag_outer_inner('foreach', $matches[0], $tag_inner); // Removing anything after the ending of this particular <foreach>.

				// Generate the new inner text.
				$replacement = '';
				foreach ($html_var as $key => $value) {

					// {{array-value}} => each array item's value
					$this->html_vars_temp[$attr_var . '-' . $key . '-key']   = $key;
					$this->html_vars_temp[$attr_var . '-' . $key . '-value'] = $value;

					$replacement_regex = preg_quote($attr_val == null ? $attr_var : $attr_val, '/');
					$tag_inner = $tag_html[1];

					// <if VALUE-SUB1="etc">   =>   <if ARRAY-KEY-0-SUB1="etc">
					preg_match_all(
						'/\<if(?:\s+' . $this->attr_var_regex . $this->attr_val_regex(1, 'type') . ')+\>/',
						$tag_inner,
						$ifs_before
					);
					$count_ifs = count($ifs_before[0]);
					if ($count_ifs) {
						$ifs_after = $ifs_before;
						for ($x = 0; $x < $count_ifs; $x++)
							$ifs_after[0][$x] = preg_replace(
								'/\s+' . $replacement_regex . '((?:\-' . $this->attr_var_regex . ')?' . $this->attr_val_regex(2, 'type') . ')/s',
								' ' . $attr_var . '-' . $key . '$1',
								$ifs_before[0][$x]
							);
						$tag_inner = str_replace($ifs_before[0], $ifs_after[0], $tag_inner);
					}

					// <element if-attr_var-sub-class="class if true">
					$prev_inner = $tag_inner;
					while (
						(
							$tag_inner = preg_replace(
								'/\<(\w+)(\s+' . $this->attr_var_regex . $this->attr_val_regex(3) . ')*(\s+(?:else|if)\-)' . $replacement_regex . '(\-' . $this->attr_var_regex . $this->attr_val_regex(6, 'type') . ')(\s+' . $this->attr_var_regex . $this->attr_val_regex(8) . ')*\>/',
								'<$1$2$4' . $attr_var . '-' . $key . '$5$7>',
								$tag_inner
							)
						) &&
						$tag_inner != $prev_inner
					)
						$prev_inner = $tag_inner;
					unset($prev_inner);

					// <foreach array>
					$replacement .=

						// <foreach VALUE-SUB1="etc"> => <foreach ARRAY-KEY-0-SUB1="etc">
						preg_replace(
							'/\<foreach\s+' . $replacement_regex . '(\-' . $this->attr_var_regex . ')?(' . $this->attr_val_regex(3) . ')?\>/',
							'<foreach ' . $attr_var . '-' . $key . '$1$2>',

							// {{VALUE-SUB1-SUB2}} => {{ARRAY-KEY-0-SUB1-SUB2}}
							preg_replace(
								'/{{' . $replacement_regex . '(\-' . $this->attr_var_regex . ')?}}/',
								'{{' . $attr_var . '-' . $key . '$1}}',
								$tag_inner
							)
						);
				}
				$html = str_replace($tag_html[0], $replacement, $html);
			}



			// While <if>s exist, replace them with HTML.
			while (preg_match('/\<if((?:\s+' . $this->attr_var_regex . $this->attr_val_regex(2, 'type') . ')+)\>(.*)\<\/if\>/s', $html, $matches)) {
				$attributes = $matches[1];
				$attr_quote = $matches[2];
				$tag_inner  = $matches[3];
				$tag_html   = $this->tag_outer_inner('if', $matches[0], $tag_inner);

				// Check for <else />
				preg_match('/' . preg_quote($tag_html[0], '/') . '(\s*\<else\>(.*)\<\/else\>)?/s', $html, $else_matches);

				// If <else> exists.
				if ($else_matches[1]) {
					$else_html = $this->tag_outer_inner('else', $else_matches[1], $else_matches[2]);

					// Include <else>etc</else> as part of the string to replace.
					$tag_html[0] .= $else_html[0];
				}
				else
					$else_html = array('', '');

				preg_match_all('/\s+(' . $this->attr_var_regex . ')' . $this->attr_val_regex(3, array('return', 'type')) . '/s', $attributes, $attributes);
				$count_attributes = count($attributes[0]);
				$attr_vars   = $attributes[1];
				$attr_types  = $attributes[2];
				$attr_quotes = $attributes[3];
				$attr_values = $attributes[4];

				// Determine whether to replace it with the innards of <if> or <else>.
				$replacement = true;
				for ($x = 0; $x < $count_attributes; $x++) {
					$html_var = $this->get($attr_vars[$x]);

					// <if var=ARRAY>
					if ($attr_types[$x] == 'ARRAY') {
						if (!is_array($html_var))
							$replacement = false;
					}

					// <if var=NULL>
					else if ($attr_types[$x] == 'NULL') {
						if ($html_var !== null)
							$replacement = false;
					}

					// <if var>
					else if ($attr_values[$x] == null) {
						if (!$html_var)
							$replacement = false;
					}

					// <if var="etc">
					else if ($html_var != $attr_values[$x])
						$replacement = false;
				}
				$html = str_replace($tag_html[0], $replacement ? $tag_html[1] : $else_html[1], $html);

			}

			// <element if-var-attribute="value">
			// If *? isn't used before -data-, then it thinks -data- is a part of the variable name.
			// Using *? to match as few - as possible, and manually defining attributes that contain - (only data?) is the best option?
			while (
				preg_match(
					'/\<\w+(?:\s+' . $this->attr_var_regex . $this->attr_val_regex(1) . ')*' .
					'(\s+(else|if)\-(' . $this->attr_var_regex . ')' . $this->attr_val_regex(5, 'return') . ')' .
					'(?:\s+' . $this->attr_var_regex . $this->attr_val_regex(7) . ')*\>/',
					$html,
					$matches
				)
			) {
				$statement  = $matches[2];
				$if_else    = $matches[3];
				$attr       = $matches[4];
				$attr_quote = $matches[5];
				$value      = $matches[6];
				preg_match('/^(' . $this->attr_var_regex . '?)\-((?:data\-)?[\d\w]+)$/', $attr, $attr); // explode('data')?
				$attr_var = $attr[1];
				$attr     = $attr[2];
				$html = str_replace(
					$matches[0],
					str_replace(
						$statement,
						(
							(
								$if_else == 'if' &&
								$this->get($attr_var)
							) ||
							(
								$if_else == 'else' &&
								!$this->get($attr_var)
							)
						) ?
						' ' . $attr . '=' . $attr_quote . $value . $attr_quote :
						'',
						$matches[0]
					),
					$html
				);
			}

			// HTML Variables
			// Replace variables until there are no changes left.
			$before = 0;
			$after  = 1;
			while ($before != $after) {
				$before = $html;
				$this->iterator(
					array_merge($this->html_vars, $this->html_vars_temp),

					// Foreach HTML Variable, replace {{VAR}} with value.
					function($keys, $value, $ret) {
						return str_replace('{{' . implode('-', $keys) . '}}', $value, $ret);
					},

					$html
				);
				$after = $html;
			}


			// Compress GIF, JPEG, MP3, and PNG; and make them static.
			preg_match_all('/<(?:img|source)(?:\s+[\w\-]+=\"[^\"]*\")*\s+src=(\'|\")?([^\"]+)\.(gif|jpe?g|mp3|png)\1(?:\s+[\w\-]+=\"[^\"]*\")*\s*\/?\>/', $html, $images1);
			preg_match_all('/background\-image\:\s*url\((\'|\")?([^\"]+)\.(gif|jpe?g|mp3|png)\1\)/', $html, $images2);
			$images = array_merge($images1[2], $images2[2]);
			$exts   = array_merge($images1[3], $images2[3]);
			$count_images = count($images);
			for ($x = 0; $x < $count_images; $x++) {

				// Ignore external images (or do we want to just host these too?)
				if (!preg_match('/\/\//', $images[$x])) {

					// See if we can find the file.
					$filename = $images[$x] . '.' . $exts[$x];
					if (file_exists($filename)) {
						$this->cacheNew($filename, $filename, $exts[$x]);
						$html = str_replace($filename, $this->cacheUrl($filename, $exts[$x]), $html);
					}
					else
						$this->debug('Cannot find image: ' . $filename);
				}
			}

			// Compress HTML
			$html = $this->compress($html, 'html');

			if (!in_array('html', $this->disabled_cache)) {
				$this->cache($html_vars_json, $html, 'html');

				// Send the ETag now that it exists.
				$this->eTag($cache_filepath);
			}
		}

		// Statistics for end-of-page comments.
		if ($this->debug_enabled) {
			foreach ($this->compressed as $type => $value) {
				if ($value) {
					$units = 'B';
					if ($value > 1024) {
						$value /= 1024;
						$units = 'KB';
						$value = round($value, 2);
					}
					$this->debug($type . ': -' . $value . ' ' . $units);
				}
			}
			$this->debug((microtime(true) - $this->start_microtime) . 's');
			if (!empty($this->debug_log))
				$html .= "\r\n<!--\r\n" . implode("\r\n", $this->debug_log) . "\r\n-->";
		}

		if ($return_only)
			return $html;

		header('Content-Language: en-us');
		header('Content-Type: text/html; charset=utf-8');
		ob_start($this->debug_enabled ? null : 'ob_gzhandler');

		// Replace these variables post-cache so that they don't each get their own cache file.
		echo str_replace(
			array('{{ENCODED_REQUEST_URI}}',                 '{{HTTP_HOST}}',       '{{REQUEST_URI}}'),
			array(htmlspecialchars($_SERVER['REQUEST_URI']), $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']),
			$html
		);
		ob_end_flush();
		return $this;
	}

}

?>