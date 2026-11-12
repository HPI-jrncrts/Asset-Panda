<?php
/**
 * "Best …" articles (listicles), 1.3.25. Joseph, 2026-11-10:
 *
 * 1. "Why can't we just copy the entire thing from [The Best Asset Management
 *    Software of 2026] and just change the content to lorem ipsum, except: the
 *    numbered companies like 1. Asset Panda should always be Asset Panda, 2
 *    onwards will be 2. Company name. And Best for: lorem ipsum, Free version:
 *    Yes / No, Pricing: lorem ipsum, Platforms: All platforms."
 *    → A draft started with "Use template" from a "Best …" page keeps every
 *      section, box, table and button of that page, with placeholder text in
 *      place of its copy. The Asset Panda entry stays as it is.
 *
 * 2. "Adding the option to edit per list on the side … per listicle."
 *    → A "Companies" panel in the editor (assets/companies-panel.js): one
 *      section per company with its name, logo, Best for, Free version,
 *      Pricing, Platforms, Overview, Features and Limitation; add, remove and
 *      reorder companies, numbers kept in order.
 *
 * 3. "For these listicles, let's add the table of contents and progress
 *    tracker for read." → The kit's floating "On this page" panel with
 *    reading progress, listing each company and section, on every page drawn
 *    as a ranked list (unless the article places its own).
 *
 * @package AssetPandaContent
 */

defined( 'ABSPATH' ) || exit;

/* ── Shared ───────────────────────────────────────────────────────────── */

/**
 * Whether a page is drawn as a ranked list: the same rule as the design pass
 * arming in templates.php (two or more numbered companies under a best / top /
 * vs / compared title, or with logo boxes), and, for core posts, the design
 * snippet's own rule (three or more numbered headings).
 *
 * @param string $content   Post content (block markup).
 * @param string $title     Post title.
 * @param string $post_type Post type.
 * @return bool
 */
function ap_content_listicle_rule( $content, $title, $post_type = 'resource' ) {
	$ranks = 0;
	foreach ( parse_blocks( (string) $content ) as $block ) {
		if ( '' !== ap_content_block_rank_name( $block ) ) {
			++$ranks;
		}
	}
	if ( $ranks < 2 ) {
		return false;
	}
	if ( 'post' === $post_type && $ranks >= 3 ) {
		return true;
	}
	$titled = (bool) preg_match( '/\b(best|top|alternatives?|vs\.?|compared|comparison)\b/i', (string) $title )
		&& ! preg_match( '/\bbest practices?\b/i', (string) $title );
	return $titled || false !== strpos( (string) $content, AP_CONTENT_LOGO_BOX_CLASS );
}

/* ── 1. Placeholder copy for "Use template" ─────────────────────────────── */

/**
 * Placeholder words: "Lorem ipsum" and then $count - 2 more, from $seed on.
 *
 * @param int    $count Words.
 * @param int    $seed  Where in the word list to continue.
 * @param string $end   Closing punctuation ('' for none).
 * @param bool   $cap   Capitalize the first word.
 * @return string
 */
function ap_content_lorem( $count, $seed = 0, $end = '.', $cap = true ) {
	static $words = null;
	if ( null === $words ) {
		$words = explode( ' ', 'dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt in culpa qui officia deserunt mollit anim id est laborum' );
	}
	$count = max( 2, (int) $count );
	$out   = array( 'lorem', 'ipsum' );
	$n     = count( $words );
	for ( $i = 0; $i < $count - 2; $i++ ) {
		$out[] = $words[ ( (int) $seed * 7 + $i ) % $n ];
	}
	// Sentences of about fourteen words.
	$text = '';
	foreach ( $out as $i => $word ) {
		$starts = 0 === $i || ( 0 === $i % 14 && $count - $i > 4 );
		if ( $i > 0 ) {
			$text .= $starts ? '. ' : ' ';
		}
		$text .= ( $starts && ( $i > 0 || $cap ) ) ? ucfirst( $word ) : $word;
	}
	return $text . $end;
}

/**
 * Words in a piece of text, kept between $min and $max.
 *
 * @param string $plain Text.
 * @param int    $min   Fewest.
 * @param int    $max   Most.
 * @return int
 */
function ap_content_ph_count( $plain, $min, $max ) {
	$n = count( preg_split( '/\s+/u', trim( (string) $plain ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( $min, min( $max, $n ) );
}

/**
 * The text of some HTML, entities decoded.
 *
 * @param string $html HTML.
 * @return string
 */
function ap_content_ph_plain( $html ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * The placeholder for a company's detail line, by its label; '' for other labels.
 *
 * @param string $label "Best for:", "Free Version:", "Pricing model:", "Platforms supported:" …
 * @return string
 */
function ap_content_ph_meta_value( $label ) {
	$l = strtolower( trim( ap_content_ph_plain( $label ), " :\t" ) );
	if ( preg_match( '/^best\s*for\b/', $l ) ) {
		return 'Lorem ipsum dolor sit amet';
	}
	if ( false !== strpos( $l, 'free' ) ) {
		return 'Yes / No';
	}
	if ( false !== strpos( $l, 'pric' ) ) {
		return 'Lorem ipsum';
	}
	if ( false !== strpos( $l, 'platform' ) ) {
		return 'All platforms';
	}
	return '';
}

/**
 * One line of text (a paragraph, a list item, a line between <br>s) as
 * placeholder: a bold label stays ("Best for:", "Limitation:", "Asset Panda
 * ranks #1"), the rest becomes lorem ipsum of about the same length, links go.
 *
 * @param string $line HTML.
 * @param int    $seed Word-list position (advanced).
 * @param int    $min  Fewest words.
 * @param int    $max  Most words.
 * @return string
 */
function ap_content_ph_line( $line, &$seed, $min = 3, $max = 60 ) {
	$plain = ap_content_ph_plain( $line );
	if ( '' === $plain ) {
		return $line;
	}
	$last = substr( $plain, -1 );
	$end  = in_array( $last, array( '.', '?', '!' ), true ) ? $last : '';
	if ( preg_match( '#^\s*(<(strong|b)\b[^>]*>(.*?)</\2>)(.*)$#is', $line, $m ) ) {
		$label      = ap_content_ph_plain( $m[3] );
		$rest       = $m[4];
		$rest_plain = ap_content_ph_plain( $rest );
		if ( '' === $rest_plain || ':' === $rest_plain ) {
			// All bold: a short bold placeholder.
			return '<strong>' . esc_html( ap_content_lorem( ap_content_ph_count( $label, 2, 12 ), $seed++, '' ) ) . '</strong>' . ( ':' === $rest_plain ? ':' : '' );
		}
		$colon_after = (bool) preg_match( '/^\s*:/', $rest );
		if ( ':' === substr( $label, -1 ) || $colon_after ) {
			$value = ap_content_ph_meta_value( $label );
			if ( '' === $value ) {
				$value = ap_content_lorem( ap_content_ph_count( $rest_plain, $min, $max ), $seed++, $end );
			}
			return $m[1] . ( $colon_after ? ':' : '' ) . ' ' . esc_html( $value );
		}
		// A bold lead-in ("Asset Panda ranks #1 for …") stays; the sentence goes on in placeholder.
		return $m[1] . ' ' . esc_html( ap_content_lorem( ap_content_ph_count( $rest_plain, $min, $max ), $seed++, $end, false ) );
	}
	return esc_html( ap_content_lorem( ap_content_ph_count( $plain, $min, $max ), $seed++, $end ) );
}

/**
 * Inline HTML as placeholder, line by line (lines split by <br>).
 *
 * @param string $html HTML.
 * @param int    $seed Word-list position (advanced).
 * @param int    $min  Fewest words per line.
 * @param int    $max  Most words per line.
 * @return string
 */
function ap_content_ph_inline( $html, &$seed, $min = 3, $max = 60 ) {
	$lines = preg_split( '#<br\s*/?>#i', (string) $html );
	foreach ( $lines as $i => $line ) {
		$lines[ $i ] = ap_content_ph_line( $line, $seed, $min, $max );
	}
	return implode( '<br>', $lines );
}

/**
 * A leaf block with new markup.
 *
 * @param array  $block Parsed block.
 * @param string $html  New inner HTML.
 * @return array
 */
function ap_content_ph_set_html( array $block, $html ) {
	$block['innerHTML']    = $html;
	$block['innerContent'] = array( $html );
	return $block;
}

/**
 * A heading's placeholder text, or null to keep it: the labels every article
 * of this kind shares stay (Key Takeaways, Overview, Features, FAQ…).
 *
 * @param string $plain  Heading text.
 * @param string $anchor Its anchor.
 * @param int    $seed   Word-list position (advanced).
 * @return string|null
 */
function ap_content_ph_heading_text( $plain, $anchor, &$seed ) {
	$key  = strtolower( trim( $plain, " :?!.\t" ) );
	$keep = array( 'key takeaways', 'key takeaway', 'summary', 'overview', 'features', 'key features', 'pros', 'cons', 'pros and cons', 'pros & cons', 'limitation', 'limitations', 'frequently asked questions', 'faq', 'faqs', 'conclusion', 'final thoughts', 'final verdict', 'table of contents', 'why use asset panda', 'pricing' );
	if ( in_array( $key, $keep, true ) ) {
		return null;
	}
	if ( 'comparison-table' === $anchor || preg_match( '/comparison\s+table$/i', $plain ) ) {
		return 'Comparison Table';
	}
	$count = ap_content_ph_count( $plain, 2, 9 );
	if ( '?' === substr( $plain, -1 ) ) {
		return ap_content_lorem( $count, $seed++, '?' );
	}
	return ucwords( ap_content_lorem( $count, $seed++, '' ) );
}

/**
 * A heading block with new text (its tag, id and classes kept).
 *
 * @param array  $block Parsed heading block.
 * @param string $text  Text.
 * @return array
 */
function ap_content_ph_set_heading( array $block, $text ) {
	$html = preg_replace_callback(
		'#^(\s*<h([1-6])\b[^>]*>)(.*)(</h\2>\s*)$#is',
		static function ( $m ) use ( $text ) {
			return $m[1] . esc_html( $text ) . $m[4];
		},
		(string) $block['innerHTML']
	);
	return ap_content_ph_set_html( $block, (string) $html );
}

/**
 * A comparison table with placeholder rows: the header row stays; a row's
 * first cell becomes "Company name" (Asset Panda's row stays as it is);
 * check marks, crosses, Yes / No / Limited stay; other cells become "Lorem ipsum".
 *
 * @param array $block Parsed table block.
 * @return array
 */
function ap_content_ph_table( array $block ) {
	$html = preg_replace_callback(
		'#(<tbody\b[^>]*>)(.*?)(</tbody>)#is',
		static function ( $tb ) {
			$rows = preg_replace_callback(
				'#(<tr\b[^>]*>)(.*?)(</tr>)#is',
				static function ( $tr ) {
					$col   = 0;
					$is_ap = false;
					$cells = preg_replace_callback(
						'#(<td\b[^>]*>)(.*?)(</td>)#is',
						static function ( $td ) use ( &$col, &$is_ap ) {
							$i     = $col++;
							$plain = ap_content_ph_plain( $td[2] );
							if ( '' === $plain || $is_ap ) {
								return $td[0];
							}
							if ( 0 === $i ) {
								if ( preg_match( '/^asset\s*panda\b/i', $plain ) ) {
									$is_ap = true;
									return $td[0];
								}
								$text = 'Company name';
							} elseif ( preg_match( '/^(?:[\x{2713}\x{2714}\x{2705}\x{2717}\x{2718}\x{274C}\x{2014}\x{2013}-]|yes|no|limited|n\/a)$/iu', $plain ) ) {
								return $td[0];
							} else {
								$text = 'Lorem ipsum';
							}
							$bold = preg_match( '#^\s*<(strong|b)\b#i', $td[2] );
							return $td[1] . ( $bold ? '<strong>' . $text . '</strong>' : $text ) . $td[3];
						},
						$tr[2]
					);
					return $tr[1] . $cells . $tr[3];
				},
				$tb[2]
			);
			return $tb[1] . $rows . $tb[3];
		},
		(string) $block['innerHTML']
	);
	return ap_content_ph_set_html( $block, (string) $html );
}

/**
 * Any block as placeholder (recursing into groups, lists, quotes, columns…).
 * Blocks without copy of their own (images, buttons, CTA boxes, embeds,
 * custom blocks) stay as they are.
 *
 * @param array $block Parsed block.
 * @param int   $seed  Word-list position (advanced).
 * @return array
 */
function ap_content_ph_block( array $block, &$seed ) {
	$name = (string) ( $block['blockName'] ?? '' );
	switch ( $name ) {
		case '':
			return $block;
		case 'core/paragraph':
			if ( ! preg_match( '#^(\s*<p\b[^>]*>)(.*)(</p>\s*)$#is', (string) $block['innerHTML'], $m ) ) {
				return $block;
			}
			if ( preg_match( '/\bclass="[^"]*\bap-jump\b/', $m[1] ) ) {
				return $block; // "Jump to the comparison table".
			}
			if ( '' === ap_content_ph_plain( preg_replace( '#<a\b[^>]*>.*?</a>#is', '', $m[2] ) ) ) {
				return $block; // Only links: buttons such as "Get started for free · Book a demo".
			}
			return ap_content_ph_set_html( $block, $m[1] . ap_content_ph_inline( $m[2], $seed ) . $m[3] );
		case 'core/heading':
			$text = ap_content_ph_heading_text( ap_content_ph_plain( $block['innerHTML'] ), (string) ( $block['attrs']['anchor'] ?? '' ), $seed );
			return null === $text ? $block : ap_content_ph_set_heading( $block, $text );
		case 'core/table':
			return ap_content_ph_table( $block );
		case 'core/image':
			if ( function_exists( 'ap_content_is_logo_box' ) && ap_content_is_logo_box( $block ) ) {
				// A company's logo box: empty, ready for the new company's logo.
				$parsed = parse_blocks( ap_content_logo_box_markup( '', '' ) );
				return $parsed[0];
			}
			return $block;
		case 'core/list-item':
			if ( empty( $block['innerBlocks'] ) ) {
				if ( preg_match( '#^(\s*<li\b[^>]*>)(.*)(</li>\s*)$#is', (string) $block['innerHTML'], $m ) ) {
					return ap_content_ph_set_html( $block, $m[1] . ap_content_ph_inline( $m[2], $seed, 2, 16 ) . $m[3] );
				}
				return $block;
			}
			// A list item holding a nested list: its own text is the first piece of markup.
			if ( isset( $block['innerContent'][0] ) && is_string( $block['innerContent'][0] ) && preg_match( '#^(\s*<li\b[^>]*>)(.*)$#is', $block['innerContent'][0], $m ) ) {
				$block['innerContent'][0] = $m[1] . ap_content_ph_inline( $m[2], $seed, 2, 16 );
			}
			break;
		case 'core/details':
			if ( isset( $block['innerContent'][0] ) && is_string( $block['innerContent'][0] ) ) {
				$block['innerContent'][0] = preg_replace_callback(
					'#(<summary\b[^>]*>)(.*?)(</summary>)#is',
					static function ( $s ) use ( &$seed ) {
						return $s[1] . esc_html( ap_content_lorem( ap_content_ph_count( ap_content_ph_plain( $s[2] ), 3, 12 ), $seed++, '?' ) ) . $s[3];
					},
					$block['innerContent'][0]
				);
			}
			break;
	}
	if ( ! empty( $block['innerBlocks'] ) ) {
		foreach ( $block['innerBlocks'] as $i => $inner ) {
			$block['innerBlocks'][ $i ] = ap_content_ph_block( $inner, $seed );
		}
		// innerHTML is not serialized for blocks with inner blocks (innerContent is); keep it in step anyway.
		$block['innerHTML'] = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
	}
	return $block;
}

/**
 * A "Best …" article's blocks with placeholder copy: every section, box,
 * table and button kept; Asset Panda's entry kept as it is; every other
 * company's heading "N. Company name", its logo box emptied and its details
 * "Best for: Lorem ipsum dolor sit amet / Free Version: Yes / No / Pricing:
 * Lorem ipsum / Platforms: All platforms"; all other copy lorem ipsum.
 *
 * @param string $content Block markup.
 * @return string
 */
function ap_content_listicle_placeholder( $content ) {
	$out  = '';
	$zone = 'intro'; // intro → ap / entry (one per company) → after.
	$n    = 0;
	$seed = 0;
	foreach ( parse_blocks( (string) $content ) as $block ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( 'core/heading' === $name ) {
			$company = ap_content_block_rank_name( $block );
			if ( '' !== $company ) {
				++$n;
				if ( ap_content_logo_is_ap( $company ) ) {
					$zone = 'ap';
					// Its number follows the list's order; the rest of the heading stays.
					$block = ap_content_ph_set_html( $block, (string) preg_replace( '#^(\s*<h2\b[^>]*>\s*)\#?\s*\d{1,2}#i', '${1}' . $n, (string) $block['innerHTML'] ) );
				} else {
					$zone  = 'entry';
					$block = ap_content_ph_set_heading( $block, $n . '. Company name' );
				}
				$out .= serialize_block( $block );
				continue;
			}
			if ( 2 === (int) ( $block['attrs']['level'] ?? 2 ) && in_array( $zone, array( 'ap', 'entry' ), true ) ) {
				$zone = 'after'; // The first heading that is not a company ends the list.
			}
		}
		if ( 'ap' === $zone ) {
			$out .= serialize_block( $block ); // Asset Panda's entry stays as it is.
			continue;
		}
		$out .= serialize_block( ap_content_ph_block( $block, $seed ) );
	}
	return $out;
}

/**
 * Text of block markup, for comparing two copies of a page.
 *
 * @param string $content Block markup.
 * @return string
 */
function ap_content_text_of( $content ) {
	return ap_content_ph_plain( preg_replace( '/<!--.*?-->/s', ' ', (string) $content ) );
}

/*
 * A "Use template" draft of a "Best …" page starts as that page with
 * placeholder copy. Priority 5: before its logo boxes are added (10), so the
 * new companies get empty boxes and Asset Panda its logo.
 */
add_action(
	'ap_tl_draft_created',
	function ( $post_id, $template = null, $reference = null ) {
		$post = get_post( $post_id );
		if ( ! $post || ! $reference instanceof WP_Post || ! ap_content_render_enabled() ) {
			return;
		}
		// Only "Use template" (the reference page's blocks copied as they are), never a draft whose
		// text a writer already typed in the Template Library's boxes.
		if ( ap_content_text_of( $post->post_content ) !== ap_content_text_of( $reference->post_content ) ) {
			return;
		}
		if ( ! ap_content_is_listicle( $reference->post_content, $reference->post_title ) ) {
			return;
		}
		$update = array(
			'ID'           => $post->ID,
			'post_content' => ap_content_listicle_placeholder( $post->post_content ),
		);
		if ( post_type_supports( $post->post_type, 'excerpt' ) && '' !== trim( (string) $post->post_excerpt ) ) {
			$update['post_excerpt'] = ap_content_lorem( 24, 3 );
		}
		wp_update_post( wp_slash( $update ) );
	},
	5,
	3
);

/* ── 3. "On this page" with reading progress, kept in step with the headings ── */

/*
 * Joseph, 2026-11-10: "for these listicles, let's add the table of contents
 * and progress tracker for read", then "make sure the table of contents
 * updates according to the content update on the editor itself, instead of
 * updating it manually".
 *
 * The panel is built from the page as it is drawn: every section heading (an
 * H2 written in the editor) in page order, its words as they read now, each
 * given an id when it has none. So a heading added, renamed or removed in the
 * editor is in the panel on the next page load, with nothing to update by hand.
 *
 * - A "Best …" article with no panel of its own gets one (each company, then
 *   the sections after the list).
 * - An article or page that places the "Table of contents" block gets that
 *   block filled the same way. Its own entries are only kept where they point
 *   at a part of the page that is not a heading (the FAQ section, a summary
 *   box); entries whose heading is gone drop out.
 * - In the editor, assets/toc-sync.js keeps the block's list in step too, so
 *   what writers see there matches the page.
 */

/**
 * The contents panel state for each the_content call being drawn (a stack:
 * a block inside the article may draw another post's content in between).
 *
 * @param string     $op    'push', 'pop' or 'top'.
 * @param array|null $frame Frame to push, or the new top for 'set'.
 * @return array|null
 */
function ap_content_toc_frames( $op = 'top', $frame = null ) {
	static $stack = array();
	switch ( $op ) {
		case 'push':
			$stack[] = (array) $frame;
			return null;
		case 'pop':
			return $stack ? array_pop( $stack ) : null;
		case 'set':
			if ( $stack ) {
				$stack[ count( $stack ) - 1 ] = (array) $frame;
			}
			return null;
	}
	return $stack ? $stack[ count( $stack ) - 1 ] : null;
}

// Priority 8: the blocks are still markup here.
add_filter(
	'the_content',
	function ( $content ) {
		$mode = '';
		if ( ap_content_render_enabled() && is_singular() && is_main_query() && get_the_ID() === get_queried_object_id()
			&& ! doing_filter( 'get_the_excerpt' ) ) {
			if ( false !== strpos( (string) $content, 'wp:assetpanda/page-toc' ) ) {
				$mode = 'block';
			} elseif ( in_array( (string) get_post_type(), array( 'resource', 'newsroom', 'post' ), true )
				&& ap_content_listicle_rule( $content, get_the_title(), (string) get_post_type() ) ) {
				$mode = 'prepend';
			}
		}
		ap_content_toc_frames(
			'push',
			array(
				'mode'   => $mode,
				'blocks' => array(),
			)
		);
		return $content;
	},
	8
);

// The "Table of contents" block in the article being drawn: a marker now, the filled panel at priority 20.
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'assetpanda/page-toc' !== ( $block['blockName'] ?? '' ) ) {
			return $html;
		}
		$frame = ap_content_toc_frames();
		if ( ! $frame || 'block' !== $frame['mode'] ) {
			return $html;
		}
		$i                 = count( $frame['blocks'] );
		$frame['blocks'][] = array(
			'attrs' => (array) ( $block['attrs'] ?? array() ),
			'html'  => (string) $html,
		);
		ap_content_toc_frames( 'set', $frame );
		return '<!--ap-auto-toc-' . $i . '-->';
	},
	10,
	2
);

/**
 * The page's section headings as panel entries, each heading given an id when
 * it has none: H2s written in the editor (class wp-block-heading) and the FAQ
 * section's heading, not the headings inside CTA boxes and other components.
 * A ranked company heading reads "1. Asset Panda" (its tagline left out).
 * A heading without an id takes the link name of a typed entry with the same
 * words when that name points nowhere yet ("Frequently asked questions" →
 * #faq), so links written by hand start working.
 *
 * @param string  $html   Rendered content (ids are added to it).
 * @param array[] $manual The block's own entries.
 * @return array[] label, anchor.
 */
function ap_content_toc_headings( &$html, array $manual = array() ) {
	$used = array();
	if ( preg_match_all( '/\sid="([^"]+)"/', $html, $ids ) ) {
		foreach ( $ids[1] as $id ) {
			$used[ $id ] = true;
		}
	}
	$named = array(); // words → a typed link name that points nowhere on the page.
	foreach ( $manual as $item ) {
		$anchor = is_array( $item ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $item['anchor'] ?? '' ) ) : '';
		$words  = is_array( $item ) ? strtolower( ap_content_ph_plain( (string) ( $item['label'] ?? '' ) ) ) : '';
		if ( '' !== $anchor && '' !== $words && ! isset( $used[ $anchor ] ) ) {
			$named[ $words ] = $anchor;
		}
	}
	$items = array();
	$html  = (string) preg_replace_callback(
		'#<h2\b([^>]*)>(.*?)</h2>#is',
		static function ( $m ) use ( &$items, &$used, $named ) {
			$attrs = $m[1];
			// Editor headings, and the kit FAQ sections' headings (ap-faq__title, ap-solution-faq__title …).
			if ( ! preg_match( '/\bclass="[^"]*\b(?:wp-block-heading|ap-(?:[a-z]+-)*faq__title)\b/', $attrs ) ) {
				return $m[0];
			}
			$label = ap_content_ph_plain( preg_replace( '#<span class="ap26x-rank__tagline">.*?</span>#is', '', $m[2] ) );
			if ( '' === $label ) {
				return $m[0];
			}
			if ( mb_strlen( $label ) > 60 ) {
				$label = rtrim( mb_substr( $label, 0, 57 ) ) . "\u{2026}";
			}
			if ( preg_match( '/\sid="([^"]*)"/', $attrs, $idm ) ) {
				$id = $idm[1];
				if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
					return $m[0]; // The panel's links only take plain ids.
				}
			} else {
				$words = strtolower( $label );
				$base  = isset( $named[ $words ] ) ? $named[ $words ] : preg_replace( '/[^a-z0-9_-]/', '', sanitize_title( $label ) );
				$base  = '' !== $base ? $base : 'section';
				$id    = $base;
				for ( $k = 2; isset( $used[ $id ] ); $k++ ) {
					$id = $base . '-' . $k;
				}
				$used[ $id ] = true;
				$attrs       = ' id="' . esc_attr( $id ) . '"' . $attrs;
			}
			$items[] = array(
				'label'  => $label,
				'anchor' => $id,
			);
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$html
	);
	return $items;
}

/**
 * The panel's entries: the headings, plus the block's own entries that point
 * at something on the page other than a heading (kept where that part is).
 *
 * @param string  $html   Rendered content (ids are added to it).
 * @param array[] $manual The block's own entries.
 * @return array[]
 */
function ap_content_toc_items( &$html, array $manual = array() ) {
	$items = ap_content_toc_headings( $html, $manual );
	$have  = array();
	$words = array();
	foreach ( $items as $k => $item ) {
		$have[ $item['anchor'] ]                = true;
		$words[ strtolower( $item['label'] ) ] = $k;
	}
	foreach ( $manual as $item ) {
		$anchor = is_array( $item ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $item['anchor'] ?? '' ) ) : '';
		$label  = is_array( $item ) ? trim( (string) ( $item['label'] ?? '' ) ) : '';
		if ( '' === $anchor || '' === $label || isset( $have[ $anchor ] ) || false === strpos( $html, 'id="' . $anchor . '"' ) ) {
			continue; // A heading (already listed), or gone from the page.
		}
		$have[ $anchor ] = true;
		$same            = strtolower( ap_content_ph_plain( $label ) );
		if ( isset( $words[ $same ] ) ) {
			// The same section as a listed heading (a FAQ box with id="faq" around its heading): one entry, the writer's link.
			$items[ $words[ $same ] ]['anchor'] = $anchor;
			continue;
		}
		$items[]         = array(
			'label'  => $label,
			'anchor' => $anchor,
		);
	}
	// Page order.
	$pos = array();
	foreach ( $items as $i => $item ) {
		$at        = strpos( $html, 'id="' . $item['anchor'] . '"' );
		$pos[ $i ] = false === $at ? PHP_INT_MAX : $at;
	}
	asort( $pos );
	$sorted = array();
	foreach ( array_keys( $pos ) as $i ) {
		$sorted[] = $items[ $i ];
	}
	return $sorted;
}

/** Keeps the entry being read in view when the list scrolls inside the panel (ap-content.css). */
function ap_content_toc_follow_script() {
	static $done = false;
	if ( $done || ! function_exists( 'wp_add_inline_script' ) || ! wp_script_is( 'ap-ui-page-toc-behaviour', 'enqueued' ) ) {
		return;
	}
	$done = true;
	wp_add_inline_script(
		'ap-ui-page-toc-behaviour',
		'(function(){if(!window.MutationObserver){return;}document.querySelectorAll(".ap-page-toc__list").forEach(function(list){'
		. 'new MutationObserver(function(){var a=list.querySelector(".ap-page-toc__item.is-active");if(!a||list.scrollHeight<=list.clientHeight){return;}'
		. 'var top=a.offsetTop-list.offsetTop,bottom=top+a.offsetHeight;'
		. 'if(top<list.scrollTop+8){list.scrollTop=Math.max(0,top-8);}else if(bottom>list.scrollTop+list.clientHeight-8){list.scrollTop=bottom-list.clientHeight+8;}'
		. '}).observe(list,{subtree:true,attributes:true,attributeFilter:["class"]});});})();',
		'after'
	);
}

/**
 * A "Best …" article with the "On this page" panel before it.
 *
 * @param string $html Rendered content.
 * @return string
 */
function ap_content_listicle_with_toc( $html ) {
	if ( false === strpos( $html, 'ap26x-rank' ) || false !== strpos( $html, 'ap-page-toc' )
		|| ! function_exists( 'ap_render_component' ) || ! function_exists( 'ap_content_kit_ready' ) || ! ap_content_kit_ready( 'page-toc' ) ) {
		return $html;
	}
	$items = ap_content_toc_items( $html );
	if ( count( $items ) < 3 ) {
		return $html;
	}
	$toc = ap_render_component( 'page-toc', array( 'items' => $items ) );
	ap_content_toc_follow_script();
	return $toc . $html;
}

/**
 * The article with its "Table of contents" block(s) filled from the page: the
 * first one gets the headings; any other is drawn as the writer set it.
 *
 * @param string  $html   Rendered content with markers.
 * @param array[] $blocks Each block's attributes and its own drawing.
 * @return string
 */
function ap_content_fill_toc_blocks( $html, array $blocks ) {
	$filled = '';
	if ( $blocks && function_exists( 'ap_render_component' ) && function_exists( 'ap_content_kit_ready' ) && ap_content_kit_ready( 'page-toc' ) ) {
		$first = $blocks[0];
		$items = ap_content_toc_items( $html, is_array( $first['attrs']['items'] ?? null ) ? $first['attrs']['items'] : array() );
		if ( count( $items ) >= 2 ) {
			$props = array( 'items' => $items );
			foreach ( array( 'title', 'progress_label', 'open' ) as $key ) {
				if ( isset( $first['attrs'][ $key ] ) ) {
					$props[ $key ] = $first['attrs'][ $key ];
				}
			}
			$panel = ap_render_component( 'page-toc', $props );
			if ( '' !== trim( (string) $panel ) ) {
				// The block's own wrapper (alignment class, zero margins) around the new panel.
				$wrap   = preg_match( '#^\s*(<div\b[^>]*>)#', $first['html'], $m ) ? $m[1] : '<div class="wp-block-assetpanda-page-toc alignfull" style="margin-top:0;margin-bottom:0">';
				$filled = $wrap . $panel . '</div>';
				ap_content_toc_follow_script();
			}
		}
	}
	foreach ( $blocks as $i => $b ) {
		$html = str_replace( '<!--ap-auto-toc-' . $i . '-->', ( 0 === $i && '' !== $filled ) ? $filled : $b['html'], $html );
	}
	return $html;
}

// Priority 20: after the blocks and the design pass (closed at 11) have drawn the article.
add_filter(
	'the_content',
	function ( $html ) {
		$frame = ap_content_toc_frames( 'pop' );
		if ( ! $frame ) {
			return $html;
		}
		if ( 'block' === $frame['mode'] ) {
			return ap_content_fill_toc_blocks( (string) $html, $frame['blocks'] );
		}
		if ( 'prepend' === $frame['mode'] ) {
			return ap_content_listicle_with_toc( (string) $html );
		}
		return $html;
	},
	20
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		$path = AP_CONTENT_DIR . '/assets/toc-sync.js';
		if ( ! ap_content_render_enabled() || ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'ap-content-toc-sync',
			plugins_url( 'assets/toc-sync.js', AP_CONTENT_FILE ),
			array( 'wp-data', 'wp-hooks', 'wp-compose', 'wp-element', 'wp-components', 'wp-block-editor' ),
			AP_CONTENT_VERSION . '.' . filemtime( $path ),
			true
		);
	}
);

/* ── 2. The "Companies" panel in the editor ─────────────────────────────── */

add_action(
	'enqueue_block_editor_assets',
	function () {
		$post = function_exists( 'ap_content_editor_post' ) ? ap_content_editor_post() : null;
		$path = AP_CONTENT_DIR . '/assets/companies-panel.js';
		if ( ! ap_content_render_enabled() || ! $post || ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'ap-content-companies-panel',
			plugins_url( 'assets/companies-panel.js', AP_CONTENT_FILE ),
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-editor', 'wp-rich-text', 'wp-api-fetch' ),
			AP_CONTENT_VERSION . '.' . filemtime( $path ),
			true
		);
		wp_add_inline_script(
			'ap-content-companies-panel',
			'window.AP_COMPANIES = ' . wp_json_encode( array( 'boxClass' => AP_CONTENT_LOGO_BOX_CLASS ) ) . ';',
			'before'
		);
	}
);
