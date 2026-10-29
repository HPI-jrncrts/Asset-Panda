<?php
/**
 * One look for a post's "key" content (QA 2026-10-29, Joseph): Key Takeaways, Quick Answer, Key Summary, TL;DR, The
 * Bottom Line and the like were written three different ways across the posts — a bold label at the start of a
 * paragraph, the same paragraph inside a quote box (which rendered the whole answer bold), or a heading followed by
 * a list — and so looked three different ways. They now all take the Key Takeaways style.
 *
 * "Do not change content. Just the style needs to follow." The text and the element structure stay exactly as
 * written; this filter only adds classes the stylesheet (assets/ap-content.css, "Key content") hooks:
 *   - <p><strong>Label:</strong> text</p>         → p.ap26-key.ap26-key--para, the <strong> gets .ap26-key__title
 *   - <blockquote><p><strong>Label:</strong> …   → blockquote.ap26-key.ap26-key--quote, same on its <strong>
 *   - <h2–h6>Label</h…> (+ the list after it)     → the heading gets .ap26-key__title (and loses the design pass's
 *                                                   .ap26x-minihead); CSS styles the list that follows it
 *   - the post's first heading reading "Summary"  → the same as a heading label (an opening key summary)
 *   - <h2>…FAQ(s)… / Frequently Asked Questions   → .ap26-faq__head; CSS styles the questions and answers after it
 * Nothing in the database changes; the filter only runs on single resources, press releases and posts.
 *
 * @package AssetPandaContent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Labels that mark key content. Heading form excludes the bare "Summary" (often a closing section of its own).
 *
 * @param bool $heading Whether the label is a heading.
 * @return string Regex alternation.
 */
function ap_content_key_labels( $heading = false ) {
	$labels = array(
		'quick answers?',
		'the short answer',
		'short answer',
		'key takeaways?',
		'main takeaways?',
		'takeaways?',
		'key summary',
		'key points',
		'tl;?\s?dr',
		'in short',
		'at a glance',
		'the bottom line',
		'bottom line',
		'what you need to know',
	);
	if ( ! $heading ) {
		$labels[] = 'in summary';
		$labels[] = 'summary';
	}
	return implode( '|', $labels );
}

/**
 * Adds classes to an opening tag's class attribute (creating it when absent), removing any listed in $drop.
 *
 * @param string $attrs Attribute string of the tag (after the tag name).
 * @param string $add   Classes to add.
 * @param array  $drop  Classes to remove.
 * @return string
 */
function ap_content_key_classes( $attrs, $add, $drop = array() ) {
	if ( preg_match( '#\bclass="([^"]*)"#', $attrs, $cm ) ) {
		$classes = preg_split( '/\s+/', trim( $cm[1] ) );
		$classes = array_diff( $classes, $drop );
		$classes = array_unique( array_merge( $classes, preg_split( '/\s+/', $add ) ) );
		return str_replace( $cm[0], 'class="' . trim( implode( ' ', $classes ) ) . '"', $attrs );
	}
	return $attrs . ' class="' . $add . '"';
}

/**
 * The content filter.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function ap_content_key_blocks( $content ) {
	if ( ! is_string( $content ) || '' === $content || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}
	if ( ! is_singular( array( 'resource', 'newsroom', 'post' ) ) ) {
		return $content;
	}
	$labels = ap_content_key_labels();
	if ( ! preg_match( '/' . $labels . '|\bFAQs?\b|frequently\s+asked/i', $content ) ) {
		return $content;
	}
	$original = $content;
	try {
		// 0. The FAQ section's heading (an H2 reading "FAQ", "… FAQs", "Frequently Asked Questions"): the stylesheet
		//    gives the questions and answers that follow it the Figma FAQ look. Class only; text untouched.
		$content = preg_replace_callback(
			'#<h2\b([^>]*)>(.*?)</h2>#is',
			function ( $m ) {
				$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $m[2] ) ) );
				if ( '' === $text || strlen( $text ) > 120 || ! preg_match( '/\bFAQs?\b|^frequently\s+asked\s+questions\b/i', $text ) ) {
					return $m[0];
				}
				return '<h2' . ap_content_key_classes( $m[1], 'ap26-faq__head' ) . '>' . $m[2] . '</h2>';
			},
			$content
		);
		if ( ! is_string( $content ) ) {
			return $original;
		}

		// 0b. A post that opens with a "Summary" heading (the converted compare guides: "Summary" + one paragraph
		//     before anything else) — a key summary, so it takes the Key Takeaways look like the labels below. Only
		//     the first heading of the post qualifies; a closing "Summary" section stays a normal heading.
		if ( preg_match( '#<h([2-6])\b[^>]*>.*?</h\1>#is', $content, $first, PREG_OFFSET_CAPTURE )
			&& preg_match( '#^<h([2-6])\b([^>]*)>(\s*(?:<(?:strong|b|span|em)\b[^>]*>\s*)*(?:executive\s+)?summary\s*:?\s*(?:</(?:strong|b|span|em)>\s*)*)</h\1>$#is', $first[0][0], $sm ) ) {
			$marked  = '<h' . $sm[1] . ap_content_key_classes( $sm[2], 'ap26-key__title ap26-key__head', array( 'ap26x-minihead' ) ) . '>' . $sm[3] . '</h' . $sm[1] . '>';
			$content = substr_replace( $content, $marked, $first[0][1], strlen( $first[0][0] ) );
		}

		if ( ! is_string( $content ) || ! preg_match( '/' . $labels . '/i', $content ) ) {
			return is_string( $content ) ? $content : $original;
		}

		// \g{-3} = this fragment's own (strong|b) group, wherever the fragment sits in the larger pattern.
		$label_strong = '<(strong|b)\b([^>]*)>(\s*(?:' . $labels . ')\s*:?\s*)</\g{-3}>';

		// 1. A quote box whose first paragraph opens with a bold label.
		$content = preg_replace_callback(
			'#<blockquote\b([^>]*)>(\s*<p\b[^>]*>\s*)' . $label_strong . '#is',
			function ( $m ) {
				return '<blockquote' . ap_content_key_classes( $m[1], 'ap26-key ap26-key--quote' ) . '>' . $m[2]
					. '<' . $m[3] . ap_content_key_classes( $m[4], 'ap26-key__title' ) . '>' . $m[5] . '</' . $m[3] . '>';
			},
			$content
		);

		// 2. A paragraph that opens with a bold label (one inside a marked quote box keeps its quote marking only).
		$content = preg_replace_callback(
			'#<p\b([^>]*)>(\s*)' . $label_strong . '#is',
			function ( $m ) {
				if ( false !== strpos( $m[4], 'ap26-key__title' ) ) {
					return $m[0];
				}
				return '<p' . ap_content_key_classes( $m[1], 'ap26-key ap26-key--para' ) . '>' . $m[2]
					. '<' . $m[3] . ap_content_key_classes( $m[4], 'ap26-key__title' ) . '>' . $m[5] . '</' . $m[3] . '>';
			},
			$content
		);

		// 3. A heading that is a label.
		$content = preg_replace_callback(
			'#<h([2-6])\b([^>]*)>(\s*(?:<(?:strong|b|span|em)\b[^>]*>\s*)*(?:' . ap_content_key_labels( true ) . ')\s*:?\s*(?:</(?:strong|b|span|em)>\s*)*)</h\1>#i',
			function ( $m ) {
				return '<h' . $m[1] . ap_content_key_classes( $m[2], 'ap26-key__title ap26-key__head', array( 'ap26x-minihead' ) ) . '>' . $m[3] . '</h' . $m[1] . '>';
			},
			$content
		);
	} catch ( \Throwable $e ) {
		return $original;
	}
	// A regex that gave up (backtrack limit) returns null: keep the post as it was rather than print nothing.
	return is_string( $content ) ? $content : $original;
}
add_filter( 'the_content', 'ap_content_key_blocks', 30 );
