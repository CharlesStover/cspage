# CSPage (PHP)
_CSPage (PHP)_ is a PHP framework designed to automate webpage optimization. It caches, compresses, concatenates, and otherwise optimizes HTML, CSS, JavaScript, and static file content distribution.

This repository contains an obsolete version that was never intended to be distributed. Future versions will be maintained in NodeJS.

## Supports
* Caching: automated ETag generation, dependencies, permacaching
* Compressing: YUI compressor
* CSS: variables
* HTML: &lt;if&gt;/&lt;else&gt; and &lt;foreach&gt; tags, variables
* ... and countless shortcuts to decrease page development time

## To-Do
* Compressing: Optipng
* CSS: LESS/SASS, move media=print to bottom of &lt;body&gt; element

## Documentation

* Methods
	* module($module)
		<p>Initializes and/or returns a module to use its methods.</p>
	* devMachine()
		<p>Returns whether or not the running machine is a development machine.</p>
	* devMachine($boolean)
		<p>Sets whether or not the program is operating on a development machine.</p>
* Modules
	* Cache
		* getDir()
			<p>Gets the directory of cache files.</p>
		* getUrl()
			<p>Gets the base URL for cached files.</p>
		* getUrl($id[, $ext])
			<p>Gets the static URL of a cached file.</p>
		* setDir($dir)
			<p>Sets the directory in which to store cache files.</p>
		* setUrl($url)
			<p>Sets the base URL for cached files.</p>
	* Error
		* count()
			<p>Returns the number of errors in the error log.</p>
		* critical($str)
			<p>Adds $str to the error log, outputs the errors, and terminates the program.</p>
		* debug($str)
			<p>Adds $str to the debug logger.</p>
		* debugEnabled()
			<p>Returns whether debugging should be treated as enabled.</p>
		* debugEnabled($bool)
			<p>Enables or disables debugging output.</p>
		* handler($no, $str[, $file, $line, $context])
			<p>Default error handler. Adds $str to the error log.</p>
		* output($return_only)
			<p>Sets the page output to a plaintext display of the error log.</p>
			<p>If $return_only flag is true, returns the text log instead of outputting it.</p>
	* HTML
		* body($html)
			<p>A file that contains or the HTML contents of the &lt;body&gt;.</p>
		* description($description)
			<p>Sets the meta description of the page.</p>
		* element($name, $attributes, $contents)
			<p>Returns the markup of an element with the given attributes and contents.</p>
		* keywords($keywords)
			<p>Sets the meta keywords of the page.</p>
		* meta($name, $content)
			<p>Sets a meta tag.</p>
		* output($return_only)
			<p>Outputs an optimized HTML document.</p>
			<p>If $return_only flag is true, returns the document instead of outputting it.</p>
		* themeColor($color)
			<p>Sets the mobile color for the webpage.</p>
	* MimeType
		* get($file)
			<p>Returns the mime type for a given file name.</p>
