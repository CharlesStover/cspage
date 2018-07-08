<?php

trait CSPageRegExp {

	// Regular Expression for ATTRIBUTE="value"
	// *? means not to include data- if data- is explicitly listed afterward.
	// This allows for if-html_var-data-x="" matching if-$attr_var_regex-data-$attr_var_regex
	private $attr_var_regex = '[\d\w]+(?:\-[\d\w]+)*';

	// Regular Express for attribute="VALUE"
	// $b is the back reference # for the quotation mark
	// +1 for type|"value" if type and return
	// +1 for quote
	// +1 for value if return
	function attr_val_regex($b, $options = array()) {

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

	// Strip excess data from the end of <tag> tags </tag>
	function tag_outer_inner($tag, $outer, $inner) {

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
					'/\<foreach\s+' . $this->attr_var_regex . $this->attr_val_regex(1) . '\>/' :
					'/\<' . $tag . '(?:\s+' . $this->attr_var_regex . $this->attr_val_regex(1) . ')+\>/'
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

}

?>