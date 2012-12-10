<?php
/*
	Ce fichier contient les fonctions qui sont extraites des CMS pour formater certain texte. Elles sont utiliser dans le cas ou le CMS ne stocque pas tout le HTML dans la base de donnée.
*/

/*
	Fonction de wordpress qui convertit les doubles retour à la ligne (\n\n) en paragraphe (<p>).
*/
function wpautop($pee, $br = true) {
	$pre_tags = array();

	if ( trim($pee) === '' )
		return '';

	$pee = $pee . "\n"; // just to make things a little easier, pad the end

	if ( strpos($pee, '<pre') !== false ) {
		$pee_parts = explode( '</pre>', $pee );
		$last_pee = array_pop($pee_parts);
		$pee = '';
		$i = 0;

		foreach ( $pee_parts as $pee_part ) {
			$start = strpos($pee_part, '<pre');

			// Malformed html?
			if ( $start === false ) {
				$pee .= $pee_part;
				continue;
			}

			$name = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

			$pee .= substr( $pee_part, 0, $start ) . $name;
			$i++;
		}

		$pee .= $last_pee;
	}

	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
	$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
	if ( strpos($pee, '<object') !== false ) {
		$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
		$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
	}
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	// make paragraphs, including one at the end
	$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
	$pee = '';
	foreach ( $pees as $tinkle )
		$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
	$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
	if ( $br ) {
		$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee);
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	if ( !empty($pre_tags) )
		$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

	return $pee;
}
?>