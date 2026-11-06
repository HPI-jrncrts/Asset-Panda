<?php
/**
 * why-cards — "one platform, different teams" use-case cards.
 *
 * Matches the live .epx-why section (solution pages, Emergency Preparedness,
 * School Reunification, Help Desk): eyebrow and heading centred over up to
 * three cards whose titles link onward. The cards reveal on scroll through a
 * CSS scroll timeline (no script), as on live.
 *
 * groups (Purchasing Contracts, 2026-10-31: "change the UI into this ... 3x2
 * windowed grid ... center aligned", Federal and International "the same
 * section"): labelled card sets one after another in the same section, each
 * under its own label, so the splash runs behind all of them.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_why_items = static function ( $list ) {
	$out = array();
	foreach ( is_array( $list ) ? $list : array() as $item ) {
		if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
			continue;
		}
		$out[] = array(
			'title'      => trim( (string) $item['title'] ),
			'body'       => trim( (string) ( $item['body'] ?? '' ) ),
			'url'        => trim( (string) ( $item['url'] ?? '' ) ),
			// Opt-in: with a url, an arrow link under the body carries it (the title stays plain).
			'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
			'icon'       => trim( (string) ( $item['icon'] ?? '' ) ),
			'icon_svg'   => ap_ui_svg( $item['icon_svg'] ?? '' ),
			'icon_alt'   => (string) ( $item['icon_alt'] ?? '' ),
			// Partners: a logo box above the title, and several arrow links at the foot.
			'logo'       => trim( (string) ( $item['logo'] ?? '' ) ),
			'logo_alt'   => (string) ( $item['logo_alt'] ?? '' ),
			'logo_w'     => max( 0, (int) ( $item['logo_w'] ?? 0 ) ),
			'logo_h'     => max( 0, (int) ( $item['logo_h'] ?? 0 ) ),
			'links'      => array_values(
				array_filter(
					is_array( $item['links'] ?? null ) ? $item['links'] : array(),
					static function ( $l ) {
						return is_array( $l ) && '' !== trim( (string) ( $l['label'] ?? '' ) ) && '' !== trim( (string) ( $l['url'] ?? '' ) );
					}
				)
			),
		);
	}
	return $out;
};
$items = $ap_why_items( $props['items'] );

$groups = array();
foreach ( is_array( $props['groups'] ) ? $props['groups'] : array() as $group ) {
	if ( ! is_array( $group ) ) {
		continue;
	}
	$group_items = $ap_why_items( $group['items'] ?? array() );
	if ( $group_items ) {
		$anchor   = trim( (string) ( $group['anchor'] ?? '' ) );
		$groups[] = array(
			'heading' => trim( (string) ( $group['heading'] ?? '' ) ),
			'anchor'  => preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $anchor ) ? $anchor : '',
			'items'   => $group_items,
		);
	}
}

// Card body: line breaks kept, **bold** (contract terms), every piece escaped.
$ap_why_body = static function ( $text ) {
	$html = ap_ui_lines( $text );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};

// The solution pages' explore-link arrow (as solution-rows draws it).
$ap_why_arrow = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="#5044E1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
$eyebrow = trim( (string) $props['eyebrow'] );
$title   = trim( (string) $props['title'] );
$anchor  = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$note    = trim( (string) $props['note'] );
$lede    = trim( (string) $props['lede'] );
if ( '' === $title && ! $items && ! $groups ) {
	return;
}
// Group labels are the section's h2s when it has no heading of its own.
$group_tag = '' === $title ? 'h2' : 'h3';
// Opt-in grid (more than three cards): 0 keeps today's single row.
$cols    = in_array( (int) $props['columns'], array( 2, 3 ), true ) ? (int) $props['columns'] : 0;
?>
<section class="ap-why-cards ap-why-cards--<?php echo esc_attr( $variant ); ?><?php echo $cols ? ' ap-why-cards--cols ap-why-cards--cols-' . esc_attr( (string) $cols ) : ''; ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-why-cards__orbs" aria-hidden="true"></div>
	<div class="ap-why-cards__wrap">
		<?php if ( '' !== $eyebrow || '' !== $title || '' !== $lede ) : ?>
			<div class="ap-why-cards__head ap-why-cards__rev">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-why-cards__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h2 class="ap-why-cards__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $lede ) : ?>
					<p class="ap-why-cards__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php
		$ap_why_sets = $items ? array( array( 'heading' => '', 'anchor' => '', 'items' => $items ) ) : array();
		$ap_why_sets = array_merge( $ap_why_sets, $groups );
		foreach ( $ap_why_sets as $set ) :
			?>
			<?php if ( '' !== $set['heading'] ) : ?>
				<<?php echo tag_escape( $group_tag ); ?> class="ap-why-cards__group ap-why-cards__eyebrow ap-why-cards__rev"<?php echo '' !== $set['anchor'] ? ' id="' . esc_attr( $set['anchor'] ) . '"' : ''; ?>><?php echo esc_html( $set['heading'] ); ?></<?php echo tag_escape( $group_tag ); ?>>
			<?php endif; ?>
			<div class="ap-why-cards__grid">
				<?php foreach ( $set['items'] as $i => $item ) : ?>
					<article class="ap-why-cards__card ap-why-cards__rev ap-why-cards__rev--d<?php echo esc_attr( (string) min( $i + 1, 3 ) ); ?><?php echo '' !== $item['logo'] ? ' ap-why-cards__card--logo' : ''; ?>" tabindex="0">
						<?php if ( '' !== $item['logo'] ) : ?>
							<span class="ap-why-cards__logo"><img src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ); ?>"<?php echo $item['logo_w'] > 0 && $item['logo_h'] > 0 ? ' width="' . esc_attr( (string) $item['logo_w'] ) . '" height="' . esc_attr( (string) $item['logo_h'] ) . '"' : ''; ?> loading="lazy" decoding="async" /></span>
						<?php endif; ?>
						<?php if ( '' !== $item['icon_svg'] ) : ?>
							<span class="ap-why-cards__ico" aria-hidden="true"><?php echo $item['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php elseif ( '' !== $item['icon'] ) : ?>
							<img class="ap-why-cards__ico" src="<?php echo esc_url( $item['icon'] ); ?>" alt="<?php echo esc_attr( $item['icon_alt'] ); ?>" width="32" height="32" loading="lazy" decoding="async" />
						<?php endif; ?>
						<?php $ap_why_link = '' !== $item['url'] && '' !== $item['link_label']; ?>
						<h3>
							<?php if ( '' !== $item['url'] && ! $ap_why_link ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></a>
							<?php else : ?>
								<?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?>
							<?php endif; ?>
						</h3>
						<?php if ( '' !== $item['body'] ) : ?>
							<p><?php echo $ap_why_body( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper; only <br>, <strong> and a fixed span are added. ?></p>
						<?php endif; ?>
						<?php if ( $ap_why_link ) : ?>
							<a class="ap-why-cards__link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?><?php echo $ap_why_arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
						<?php endif; ?>
						<?php if ( $item['links'] ) : ?>
							<span class="ap-why-cards__links">
								<?php foreach ( $item['links'] as $ap_l ) : ?>
									<a class="ap-why-cards__link" href="<?php echo esc_url( trim( (string) $ap_l['url'] ), array( 'http', 'https', 'mailto', 'tel' ) ); ?>"><?php echo esc_html( trim( (string) $ap_l['label'] ) ); ?><?php echo $ap_why_arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<?php if ( '' !== $note ) : ?>
			<p class="ap-why-cards__note"><?php echo ap_ui_brandmarks( ap_ui_lines( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
		<?php endif; ?>
	</div>
</section>
