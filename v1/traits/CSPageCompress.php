<?php



// INSERT OPTIPNG to compress()



trait CSPageCompress {

	private
		$compressed = array();   // bytes saved through compression

	// compress
	public function compress($data, $type) {

		// YUICompressor: CSS, JS
		if (
			$type == 'css' ||
			$type == 'js'
		) {

			// Don't bother compressing localhost files.
			if ($this->localhost)
				return $data;

			$compressed = $this->yuiCompressor($data, $type);
		}

		// HTML
		else if ($type == 'html')
			$compressed = $this->compressHtml($data);

		// Other
		else
			return $data;

		// Debug Information
		if (!array_key_exists($type, $this->compressed))
			$this->compressed[$type] = 0;
		$this->compressed[$type] += strlen($data) - strlen($compressed);
		return $compressed;
	}



	// HTML compression
	public function compressHtml($html) {
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



	// YUI Compressor
	public function yuiCompressor($data, $type) {
		$this->cache($data, $data, 'tmp');
		$ret = shell_exec('yui-compressor ' . getcwd() . '/' . $this->cacheFilepath($data, 'tmp') . ' --type ' . $type);
		unlink($this->cacheFilepath($data, 'tmp'));

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

}

?>