<?php

class CSPage_Compress extends CSPage_Module {

	private
		$method_synonyms = array(
		);



	// Method Overloading
	public function __call($name, $arguments) {

		// Redirect JPEG to JPG
		if (array_key_exists($name, $this->method_synonyms)) {
			$synonym = $this->method_synonyms[$name];
			$this->debug(array('Redirecting compression request.', $name . ' => ' . $synonym));
			return $this->$synonym($arguments[0]);
		}

		// Ignore attempts to compress file types that aren't supported.
		$this->debug('No compression algorithm found for ' . $name);
		return $arguments[0];
	}



	// Constructor
	public function __construct($index = 0, $parent = null) {
		$this->version(0.1);
	}



	// CSS compression
	public function css($css) {
		$this->debug('Compressing CSS.');
		return $this->yuiCompressor($css, 'css');
	}



	// HTML compression
	public function html($html) {
		$this->debug('Compressing HTML.');
		$compressed = $html;

		// Get pre-formatted code, so that we don't alter the spacing in it.
		preg_match_all('/<(pre|script)(?:\s+[\w\-]+=\"[^\"]*\")*>.*?<\/\1>/s', $html, $pre);
		$count_pre = count($pre[1]);

		// Use placeholder {{!PRE@id#}} to keep track of where the <pre> tags were.
		for ($x = 0; $x < $count_pre; $x++)
			$compressed = str_replace($pre[0][$x], '{{!PRE@' . $x . '#}}', $compressed);

		$compressed =

			// strip HTML comments
			preg_replace(
				'/\<\!\-\-.*?\-\-\>/', '',

				// strip white space (spaces)
				preg_replace(
					'/ +/', ' ',

					// strip white space (special)
					str_replace(
						array("\n", "\r", "\t"), '',
						$compressed
					)
				)
			);

		// Replace pre-formatted code.
		for ($x = $count_pre - 1; $x >= 0; $x--)
			$compressed = str_replace('{{!PRE@' . $x . '#}}', $pre[0][$x], $compressed);

		return $compressed;
	}



	// JS compression
	public function js($js) {
		$this->debug('Compressing JS.');
		return $this->yuiCompressor($js, 'js');
	}



	// YUI Compressor
	public function yuiCompressor($data, $type) {
		if (!$this->parent()->devMachine()) {
			$this->debug(array('YUI Compressing', $type));
			$this->module('cache')->store($data, $data, 'tmp');
			$this->debug(array('Executing shell command.', 'yui-compressor ' . $this->module('cache')->filepath($data, 'tmp') . ' --type ' . $type));
			$ret = shell_exec('yui-compressor ' . $this->module('cache')->filepath($data, 'tmp') . ' --type ' . $type);
			unlink($this->module('cache')->filepath($data, 'tmp'));

			// Fix calc(100%+40px) => calc(100% + 40px)
			if ($type == 'css')
				$ret = preg_replace('/(\d+(?:\.\d+)?(?:\%|[a-z]{2}|vmin))(\+|\-|\*|\/)(\d+(?:\.\d+)?(?:\%|[a-z]{2}|vmin))/', '\\1 \\2 \\3', $ret);

			// If the compressor returns a blank or longer file, return the original file.
			if (
				!$ret ||
				strlen($ret) > strlen($data)
			)
				return $data;
			return $ret;
		}
		return $data;
	}

}

?>
