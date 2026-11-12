<?php
/**
 * Text → topic terms. Pure functions (no database), so the same code runs in
 * WordPress and in the calibration script.
 *
 * A page is reduced to three bags of stemmed words: its title, its headings and
 * its body. Kit blocks keep their words in block attributes, core blocks in their
 * HTML, so both are read.
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'AP_DUPCHECK_CLI' ) ) {
	exit;
}

/* Thresholds (see ap_dupcheck_verdict()). Overridable in wp-config.php. */
defined( 'AP_DUPCHECK_SAME_COS' ) || define( 'AP_DUPCHECK_SAME_COS', 0.70 );
defined( 'AP_DUPCHECK_TITLE_COS' ) || define( 'AP_DUPCHECK_TITLE_COS', 0.40 );
defined( 'AP_DUPCHECK_SAME_TITLE' ) || define( 'AP_DUPCHECK_SAME_TITLE', 0.67 );
defined( 'AP_DUPCHECK_SIMILAR_COS' ) || define( 'AP_DUPCHECK_SIMILAR_COS', 0.60 );
defined( 'AP_DUPCHECK_SIMILAR_TITLE' ) || define( 'AP_DUPCHECK_SIMILAR_TITLE', 0.75 );
defined( 'AP_DUPCHECK_FLOOR_COS' ) || define( 'AP_DUPCHECK_FLOOR_COS', 0.20 );
defined( 'AP_DUPCHECK_CONFLICT_COS' ) || define( 'AP_DUPCHECK_CONFLICT_COS', 0.80 );
defined( 'AP_DUPCHECK_MIN_WORDS' ) || define( 'AP_DUPCHECK_MIN_WORDS', 200 );
defined( 'AP_DUPCHECK_STARTER_COS' ) || define( 'AP_DUPCHECK_STARTER_COS', 0.50 );
defined( 'AP_DUPCHECK_PHRASE_SAME' ) || define( 'AP_DUPCHECK_PHRASE_SAME', 4 );

/** Attribute keys whose text reads as a heading. */
const AP_DUPCHECK_HEAD_KEYS = '/(title|heading|headline|eyebrow|kicker)/i';

/** Attribute keys that never hold reader-facing words. */
const AP_DUPCHECK_SKIP_KEYS = '/^(url|href|link|src|srcset|id|ids|ref|class|classname|anchor|align|style|color|colour|background|bg|theme|variant|layout|mode|size|width|height|image|img|icon|logo|slug|lock|metadata|template|tag|tagname|level|type|target|rel|poster|video|embed|provider|place|look|key|token|fontsize|gradient)$/i';

/**
 * Words with no topic value. IDF later discounts site-wide words (asset, panda…)
 * on its own, so this list stays generic.
 *
 * @return array<string,true>
 */
function ap_dupcheck_stopwords() {
	static $set = null;
	if ( null === $set ) {
		$words = 'a about above after again against all also am an and any are as at be because been before being below between both but by can could did do does doing down during each few for from further had has have having he her here hers herself him himself his how i if in into is it its itself just let me more most my myself no nor not now of off on once only or other our ours ourselves out over own same she should so some such than that the their theirs them themselves then there these they this those through to too under until up very was we were what when where which while who whom why will with would you your yours yourself yourselves via per etc yes get gets got make makes made use uses used using one two three four five first second new also may might must need needs can\'t don\'t won\'t it\'s let\'s we\'re you\'re they\'re isn\'t aren\'t doesn\'t like well way ways many much even still every across within without into onto upon whether however therefore thus learn read click here see view more today now www http https com';
		$set   = array_fill_keys( preg_split( '/\s+/', $words ), true );
	}
	return $set;
}

/**
 * Light suffix stemmer: "tracking"/"tracked"/"tracks" → "track". Consistency
 * matters more than linguistics here.
 *
 * @param string $w Lower-case word.
 * @return string
 */
function ap_dupcheck_stem( $w ) {
	$n = strlen( $w );
	if ( $n > 5 && 'ies' === substr( $w, -3 ) ) {
		return substr( $w, 0, -3 ) . 'y';
	}
	if ( $n > 6 && 'ing' === substr( $w, -3 ) ) {
		$w = substr( $w, 0, -3 );
		return ( strlen( $w ) > 3 && $w[ strlen( $w ) - 1 ] === $w[ strlen( $w ) - 2 ] && ! in_array( substr( $w, -1 ), array( 'l', 's', 'z' ), true ) ) ? substr( $w, 0, -1 ) : $w;
	}
	if ( $n > 5 && 'ed' === substr( $w, -2 ) && 'eed' !== substr( $w, -3 ) ) {
		$w = substr( $w, 0, -2 );
		return ( strlen( $w ) > 3 && $w[ strlen( $w ) - 1 ] === $w[ strlen( $w ) - 2 ] && ! in_array( substr( $w, -1 ), array( 'l', 's', 'z' ), true ) ) ? substr( $w, 0, -1 ) : $w;
	}
	if ( $n > 4 && preg_match( '/(ss|x|ch|sh)es$/', $w ) ) {
		return substr( $w, 0, -2 );
	}
	if ( $n > 3 && 's' === substr( $w, -1 ) && ! preg_match( '/(ss|us|is)$/', $w ) ) {
		return substr( $w, 0, -1 );
	}
	return $w;
}

/**
 * Plain text → stemmed topic words.
 *
 * @param string $text Text (tags already removed).
 * @return string[]
 */
function ap_dupcheck_words( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	// Two-letter acronyms carry topics ("IT asset management" is not "asset
	// management"): double them so they survive the 3-letter minimum.
	$text = preg_replace( '/\b(IT|AI|HR|QR|EV|OT|PM)\b/', '$1$1', $text );
	$text = strtolower( $text );
	$text = str_replace( array( '’', '‘', '`' ), "'", $text );
	$text = preg_replace( '/\[[^\]]{0,200}\]/u', ' ', $text ); // Shortcodes.
	$text = preg_replace( '~https?://\S+~', ' ', $text );
	$raw  = preg_split( "/[^a-z0-9']+/", $text, -1, PREG_SPLIT_NO_EMPTY );
	$stop = ap_dupcheck_stopwords();
	$out  = array();
	foreach ( $raw as $w ) {
		$w = trim( $w, "'" );
		if ( "'s" === substr( $w, -2 ) ) {
			$w = substr( $w, 0, -2 );
		}
		if ( strlen( $w ) < 3 || isset( $stop[ $w ] ) || ctype_digit( $w ) ) {
			continue;
		}
		$out[] = ap_dupcheck_stem( $w );
	}
	return $out;
}

/**
 * Collects reader-facing strings from a block's attributes.
 *
 * @param mixed  $value Attribute value.
 * @param string $key   Attribute key.
 * @param array  $acc   Accumulator (head, body).
 */
function ap_dupcheck_attr_text( $value, $key, array &$acc ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			ap_dupcheck_attr_text( $v, is_string( $k ) ? $k : $key, $acc );
		}
		return;
	}
	if ( ! is_string( $value ) || '' === $value || preg_match( AP_DUPCHECK_SKIP_KEYS, (string) $key ) ) {
		return;
	}
	$plain = trim( wp_strip_all_tags_safe( $value ) );
	if ( '' === $plain || preg_match( '~^(https?:|/|#|\{|\[)~', $plain ) || ! preg_match( '/[a-z]{3}/i', $plain ) ) {
		return;
	}
	if ( preg_match( AP_DUPCHECK_HEAD_KEYS, (string) $key ) ) {
		$acc['head'][] = $plain;
	} elseif ( false !== strpos( $plain, ' ' ) ) {
		$acc['body'][] = $plain;
	}
}

/**
 * strip_tags that also drops script/style bodies; works without WordPress.
 *
 * @param string $html HTML.
 * @return string
 */
function wp_strip_all_tags_safe( $html ) {
	$html = preg_replace( '@<(script|style|noscript|template)[^>]*?>.*?</\\1>@si', ' ', (string) $html );
	$html = preg_replace( '/<br\s*\/?>|<\/(p|div|li|h[1-6]|td|th|section|article|figcaption|blockquote)>/i', "\n", $html );
	return strip_tags( $html );
}

/**
 * Splits HTML into heading text and body text.
 *
 * @param string $html HTML.
 * @param array  $acc  Accumulator (head, body).
 */
function ap_dupcheck_html_text( $html, array &$acc ) {
	if ( '' === trim( (string) $html ) ) {
		return;
	}
	$html = preg_replace( '@<(script|style|noscript|template)[^>]*?>.*?</\\1>@si', ' ', $html );
	if ( preg_match_all( '@<h[1-4][^>]*>(.*?)</h[1-4]>@si', $html, $m ) ) {
		foreach ( $m[1] as $h ) {
			$acc['head'][] = wp_strip_all_tags_safe( $h );
		}
		$html = preg_replace( '@<h[1-4][^>]*>.*?</h[1-4]>@si', ' ', $html );
	}
	$acc['body'][] = wp_strip_all_tags_safe( $html );
}

/**
 * Walks parsed blocks.
 *
 * @param array $blocks Parsed blocks.
 * @param array $acc    Accumulator.
 */
function ap_dupcheck_walk_blocks( array $blocks, array &$acc ) {
	foreach ( $blocks as $b ) {
		if ( ! empty( $b['attrs'] ) && is_array( $b['attrs'] ) ) {
			ap_dupcheck_attr_text( $b['attrs'], '', $acc );
		}
		$own = isset( $b['innerContent'] ) && is_array( $b['innerContent'] )
			? implode( ' ', array_filter( $b['innerContent'], 'is_string' ) )
			: (string) ( $b['innerHTML'] ?? '' );
		ap_dupcheck_html_text( $own, $acc );
		if ( ! empty( $b['innerBlocks'] ) ) {
			ap_dupcheck_walk_blocks( $b['innerBlocks'], $acc );
		}
	}
}

/**
 * Post content (blocks or plain HTML) → heading and body text.
 *
 * @param string $content Raw post_content or rendered HTML.
 * @return array{head:string, body:string}
 */
function ap_dupcheck_extract( $content ) {
	$acc     = array(
		'head' => array(),
		'body' => array(),
	);
	$content = (string) $content;
	if ( false !== strpos( $content, '<!-- wp:' ) && function_exists( 'parse_blocks' ) ) {
		ap_dupcheck_walk_blocks( parse_blocks( $content ), $acc );
	} else {
		ap_dupcheck_html_text( $content, $acc );
	}
	return array(
		'head' => implode( "\n", $acc['head'] ),
		'body' => implode( "\n", $acc['body'] ),
	);
}

/**
 * Title + content → the stored "terms" record: weighted term counts plus the
 * title's word set (kept for the title check).
 *
 * @param string $title   Title.
 * @param string $content Content.
 * @return array{tf:array<string,float>, title:string[], words:int}
 */
function ap_dupcheck_terms( $title, $content ) {
	$parts = ap_dupcheck_extract( $content );
	$tf    = array();
	$words = 0;
	foreach ( array(
		array( ap_dupcheck_words( $title ), 3.0 ),
		array( ap_dupcheck_words( $parts['head'] ), 2.0 ),
		array( ap_dupcheck_words( $parts['body'] ), 1.0 ),
	) as $bag ) {
		foreach ( $bag[0] as $w ) {
			$tf[ $w ] = ( $tf[ $w ] ?? 0 ) + $bag[1];
		}
		$words += count( $bag[0] );
	}
	arsort( $tf );
	$tf = array_slice( $tf, 0, 400, true );
	return array(
		'tf'    => $tf,
		'title' => array_values( array_unique( ap_dupcheck_words( $title ) ) ),
		'words' => $words,
	);
}

/**
 * Document frequencies over a set of term records.
 *
 * @param array $docs id => terms record.
 * @return array<string,int>
 */
function ap_dupcheck_df( array $docs ) {
	$df = array();
	foreach ( $docs as $d ) {
		foreach ( $d['tf'] as $w => $unused ) {
			$df[ $w ] = ( $df[ $w ] ?? 0 ) + 1;
		}
	}
	return $df;
}

/**
 * Weighted counts → unit TF-IDF vector.
 *
 * @param array $tf Term → weighted count.
 * @param array $df Document frequencies.
 * @param int   $n  Number of documents.
 * @return array<string,float>
 */
function ap_dupcheck_vector( array $tf, array $df, $n ) {
	$v   = array();
	$sum = 0.0;
	foreach ( $tf as $w => $c ) {
		$idf = log( ( $n + 1 ) / ( ( $df[ $w ] ?? 0 ) + 1 ) );
		if ( $idf <= 0 ) {
			continue;
		}
		$x       = ( 1 + log( $c ) ) * $idf;
		$v[ $w ] = $x;
		$sum    += $x * $x;
	}
	if ( $sum <= 0 ) {
		return array();
	}
	$norm = sqrt( $sum );
	foreach ( $v as $w => $x ) {
		$v[ $w ] = $x / $norm;
	}
	return $v;
}

/**
 * Cosine of two unit vectors.
 *
 * @param array $a Vector.
 * @param array $b Vector.
 * @return float
 */
function ap_dupcheck_cosine( array $a, array $b ) {
	if ( count( $a ) > count( $b ) ) {
		$t = $a;
		$a = $b;
		$b = $t;
	}
	$s = 0.0;
	foreach ( $a as $w => $x ) {
		if ( isset( $b[ $w ] ) ) {
			$s += $x * $b[ $w ];
		}
	}
	return $s;
}

/**
 * How many documents use each word in their TITLE (the title check ignores
 * words most titles share: asset, panda, tracking, management…).
 *
 * @param array $docs id => terms record.
 * @return array<string,int>
 */
function ap_dupcheck_title_df( array $docs ) {
	$df = array();
	foreach ( $docs as $d ) {
		foreach ( array_unique( $d['title'] ) as $w ) {
			$df[ $w ] = ( $df[ $w ] ?? 0 ) + 1;
		}
	}
	return $df;
}

/**
 * Title overlap: share of the shorter title's distinctive words found in the
 * other title.
 *
 * @param string[] $a   Title words.
 * @param string[] $b   Title words.
 * @param array    $tdf Title document frequencies.
 * @param int      $n   Number of documents.
 * @return float 0..1 (0 when either title has fewer than 2 distinctive words)
 */
function ap_dupcheck_title_overlap( array $a, array $b, array $tdf, $n ) {
	$cap  = max( 5, (int) round( $n * 0.08 ) );
	$keep = static function ( $w ) use ( $tdf, $cap ) {
		return ( $tdf[ $w ] ?? 0 ) < $cap;
	};
	$a = array_values( array_filter( array_unique( $a ), $keep ) );
	$b = array_values( array_filter( array_unique( $b ), $keep ) );
	if ( count( $a ) < 2 || count( $b ) < 2 ) {
		return 0.0;
	}
	// Titles built from one template ("Asset Panda vs Sortly | Features,
	// Differences…" / "…vs UpKeep…") share most words but not their rarest one.
	$rarest = static function ( array $words ) use ( $tdf ) {
		$min = min( array_map( static function ( $w ) use ( $tdf ) {
			return $tdf[ $w ] ?? 0;
		}, $words ) );
		return array_filter( $words, static function ( $w ) use ( $tdf, $min ) {
			return ( $tdf[ $w ] ?? 0 ) === $min;
		} );
	};
	if ( ! array_intersect( $rarest( $a ), $b ) && ! array_intersect( $rarest( $b ), $a ) ) {
		return 0.0;
	}
	$common = count( array_intersect( $a, $b ) );
	return $common / min( count( $a ), count( $b ) );
}

/**
 * Normalises a slug for the "same address" check: drops -copy/-old suffixes,
 * an "old-" prefix and every number (-2, years).
 *
 * @param string $slug Slug.
 * @return string
 */
function ap_dupcheck_slug_base( $slug ) {
	$s = strtolower( (string) $slug );
	$s = preg_replace( '/^(old|copy)-/', '', $s );
	$s = preg_replace( '/-(copy|old|new|draft|v\d+)$/', '', $s );
	$s = trim( preg_replace( '/(^|-)\d+(?=-|$)/', '', $s ), '-' ); // -2, -2026, 2025-…
	return $s;
}

/**
 * Normalises a focus keyphrase.
 *
 * @param string $kw Keyphrase.
 * @return string
 */
function ap_dupcheck_keyphrase( $kw ) {
	$w = ap_dupcheck_words( (string) $kw );
	sort( $w );
	return implode( ' ', $w );
}

/**
 * Two pages are about different things when each title has a word the other
 * title lacks that is both rare on the site and one of its own page's five
 * strongest topic words ("vs Cheqroom" and "vs Snipe-IT" share a template, not
 * a topic).
 *
 * @param array $ta Title words of A.
 * @param array $va Unit vector of A.
 * @param array $tb Title words of B.
 * @param array $vb Unit vector of B.
 * @param array $df Document frequencies.
 * @param int   $n  Number of documents.
 * @return bool
 */
function ap_dupcheck_topic_conflict( array $ta, array $va, array $tb, array $vb, array $df, $n ) {
	$rare = max( 3, (int) round( $n * 0.02 ) );
	$own  = static function ( array $title, array $other, array $vec ) use ( $df, $rare ) {
		arsort( $vec );
		$top = array_slice( array_keys( $vec ), 0, 5 );
		foreach ( array_intersect( array_diff( $title, $other ), $top ) as $w ) {
			if ( ( $df[ $w ] ?? 0 ) <= $rare ) {
				return true;
			}
		}
		return false;
	};
	return $own( $ta, $tb, $va ) && $own( $tb, $ta, $vb );
}

/**
 * Title phrase match: the shorter title's topic words appear, in order and
 * together, inside the other title ("Best Asset Tracking Software" inside "Best
 * Asset Tracking Software (2026) — Top Platforms Compared"). Catches titles made
 * only of words the site uses everywhere, which the overlap check ignores.
 *
 * @param string[] $a Title words (in order).
 * @param string[] $b Title words (in order).
 * @return int Number of words in the matched phrase (0 when none, or under 3).
 */
function ap_dupcheck_title_phrase( array $a, array $b ) {
	$a = array_values( $a );
	$b = array_values( $b );
	if ( count( $a ) > count( $b ) ) {
		$t = $a;
		$a = $b;
		$b = $t;
	}
	$n = count( $a );
	if ( $n < 3 ) {
		return 0;
	}
	for ( $i = 0; $i + $n <= count( $b ); $i++ ) {
		if ( array_slice( $b, $i, $n ) === $a ) {
			return $n;
		}
	}
	return 0;
}

/**
 * Compares two term records.
 *
 * @param array $a   Terms record with 'vec' added.
 * @param array $b   Terms record with 'vec' added.
 * @param array $df  Document frequencies.
 * @param array $tdf Title document frequencies.
 * @param int   $n   Number of documents.
 * @return array{cos:float, title:float, conflict:bool, short:bool, phrase:int, narrower:bool}
 */
function ap_dupcheck_compare( array $a, array $b, array $df, array $tdf, $n ) {
	$phrase   = ap_dupcheck_title_phrase( $a['title'], $b['title'] );
	$narrower = false;
	if ( $phrase ) {
		$long  = count( $a['title'] ) >= count( $b['title'] ) ? $a['title'] : $b['title'];
		$short = $long === $a['title'] ? $b['title'] : $a['title'];
		$rare  = max( 3, (int) round( $n * 0.01 ) );
		foreach ( array_diff( $long, $short ) as $w ) {
			if ( ( $tdf[ $w ] ?? 0 ) <= $rare ) {
				$narrower = true;
				break;
			}
		}
	}
	return array(
		'cos'      => ap_dupcheck_cosine( $a['vec'], $b['vec'] ),
		'title'    => ap_dupcheck_title_overlap( $a['title'], $b['title'], $tdf, $n ),
		'conflict' => ap_dupcheck_topic_conflict( $a['title'], $a['vec'], $b['title'], $b['vec'], $df, $n ),
		'short'    => min( $a['words'], $b['words'] ) < AP_DUPCHECK_MIN_WORDS,
		'phrase'   => $phrase,
		// The longer title adds a specific word ("Hospital Facilities Asset
		// Management Software" vs "Facility Asset Management Software"): a
		// narrower page on the same theme, so "similar" rather than "same".
		'narrower' => $narrower,
	);
}

/**
 * The verdict for one candidate page. Thresholds were set on Asset Panda's own
 * site (Nov 2026): pre-rewrite copies of 12 posts against their rewritten live
 * versions, and every pair of live pages.
 *
 * @param float $cos       Topic similarity (TF-IDF cosine).
 * @param float $title     Title overlap.
 * @param bool  $conflict  The titles name different main topics.
 * @param bool  $short     One page has too few words to compare bodies.
 * @param bool  $same_slug Same address once -2/-copy/old-/years are ignored.
 * @param bool  $same_kw   Same Yoast focus keyphrase.
 * @param int   $phrase    Words in a shared title phrase (ap_dupcheck_title_phrase()).
 * @param bool  $narrower  The longer title adds a specific word to that phrase.
 * @return string 'same', 'similar' or ''.
 */
function ap_dupcheck_verdict( $cos, $title, $conflict = false, $short = false, $same_slug = false, $same_kw = false, $phrase = 0, $narrower = false ) {
	if ( $same_slug || $same_kw || ( $phrase >= AP_DUPCHECK_PHRASE_SAME && ! $narrower ) ) {
		return 'same';
	}
	if ( $phrase >= AP_DUPCHECK_PHRASE_SAME || ( $phrase >= 3 && $cos >= AP_DUPCHECK_FLOOR_COS ) ) {
		return 'similar';
	}
	if ( $short ) {
		return ( ! $conflict && $title >= AP_DUPCHECK_SIMILAR_TITLE && $cos >= AP_DUPCHECK_FLOOR_COS ) ? 'similar' : '';
	}
	if ( $conflict ) {
		return $cos >= AP_DUPCHECK_CONFLICT_COS ? 'similar' : '';
	}
	if ( $cos >= AP_DUPCHECK_SAME_COS || ( $cos >= AP_DUPCHECK_TITLE_COS && $title >= AP_DUPCHECK_SAME_TITLE ) ) {
		return 'same';
	}
	if ( $cos >= AP_DUPCHECK_SIMILAR_COS || ( $title >= AP_DUPCHECK_SIMILAR_TITLE && $cos >= AP_DUPCHECK_FLOOR_COS ) ) {
		return 'similar';
	}
	return '';
}
