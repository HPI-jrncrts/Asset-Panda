<?php
/**
 * lp-prose — one section of a long-form page.
 *
 * The legal pages and guides are long runs of headings, paragraphs, lists
 * and the odd table. Each heading's section is one instance: the heading is
 * rendered here (with the anchor page-toc jumps to) and the text arrives as
 * HTML that passes a fixed allowlist — structure only, no classes or styles,
 * so every page reads in the same kit type. Pictures get lazy loading,
 * tables a scroll wrapper, and links off the site open in a new tab.
 *
 * @var array $props Validated props.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

static $ap_lp_prose_allowed = null;
if ( null === $ap_lp_prose_allowed ) {
	$cell = array(
		'colspan' => true,
		'rowspan' => true,
		'scope'   => true,
	);
	$ap_lp_prose_allowed = array(
		'p'          => array(),
		'br'         => array(),
		'hr'         => array(),
		'h3'         => array( 'id' => true ),
		'h4'         => array( 'id' => true ),
		'h5'         => array( 'id' => true ),
		'h6'         => array( 'id' => true ),
		'ul'         => array(),
		'ol'         => array(
			'start' => true,
			'type'  => true,
		),
		'li'         => array(),
		'a'          => array(
			'href'  => true,
			'title' => true,
		),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		'sup'        => array(),
		'sub'        => array(),
		'small'      => array(),
		'mark'       => array(),
		'code'       => array(),
		'blockquote' => array(),
		'figure'     => array(),
		'figcaption' => array(),
		'img'        => array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
		),
		'table'      => array(),
		'caption'    => array(),
		'thead'      => array(),
		'tbody'      => array(),
		'tfoot'      => array(),
		'tr'         => array(),
		'th'         => $cell,
		'td'         => $cell,
	);
}

$html = trim( wp_kses( trim( (string) $props['html'] ), $ap_lp_prose_allowed ) );
if ( '' === $html ) {
	return;
}

// Pictures load lazily; links off the site open in a new tab.
if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tag = $tags->get_tag();
		if ( 'IMG' === $tag ) {
			$tags->set_attribute( 'loading', 'lazy' );
			$tags->set_attribute( 'decoding', 'async' );
		} elseif ( 'A' === $tag ) {
			$href = (string) $tags->get_attribute( 'href' );
			$host = (string) parse_url( $href, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- host only, PHP 7+.
			if ( preg_match( '#^https?://#i', $href ) && '' !== $host && ! preg_match( '/(^|\.)assetpanda\.com$/i', $host ) ) {
				$tags->set_attribute( 'target', '_blank' );
				$tags->set_attribute( 'rel', 'noopener' );
			}
		}
	}
	$html = $tags->get_updated_html();
}
// Wide tables scroll inside their own box on phones (the tags are already sanitised).
$html = str_replace( array( '<table>', '</table>' ), array( '<div class="ap-lp-prose__table"><table>', '</table></div>' ), $html );

$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,95}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$eyebrow   = trim( (string) $props['eyebrow'] );
$title     = trim( (string) $props['title'] );
$title_tag = 'h3' === $props['title_tag'] ? 'h3' : 'h2';
$variant   = in_array( $props['variant'], array( 'body', 'lead', 'panel' ), true ) ? $props['variant'] : 'body';
$width     = 'wide' === $props['width'] ? 'wide' : 'narrow';
$space     = in_array( $props['space'], array( 'normal', 'start', 'end' ), true ) ? $props['space'] : 'normal';
$classes   = 'ap-lp-prose ap-lp-prose--' . $variant . ' ap-lp-prose--' . $width . ' ap-lp-prose--space-' . $space;
?>
<section class="<?php echo esc_attr( $classes ); ?>">
	<div class="ap-lp-prose__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-lp-prose__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $title ) : ?>
			<<?php echo tag_escape( $title_tag ); ?> class="ap-lp-prose__title"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>><?php echo ap_ui_brandmarks( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; the brand mark is a fixed span. ?></<?php echo tag_escape( $title_tag ); ?>>
		<?php elseif ( '' !== $anchor ) : ?>
			<span class="ap-lp-prose__anchor" id="<?php echo esc_attr( $anchor ); ?>"></span>
		<?php endif; ?>
		<div class="ap-lp-prose__body">
			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by wp_kses above. ?>
		</div>
	</div>
</section>
