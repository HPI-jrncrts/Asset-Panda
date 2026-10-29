<?php
/**
 * capability-scene — capability cards around a centre panel.
 *
 * Matches the live .apx-int on /asset-intelligence-platform/: two cards either
 * side of a panel that states the capture/understand/act lines and carries the
 * assistant artwork. The inner class names are the live ones, so this stays
 * diffable against the ported sheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Title with gradient slices: escape first, then wrap each slice. Live paints two
// (the brand mark and the whole second line), so title_accents adds more.
$ap_accent = static function ( $title, $accents ) {
	$html = ap_ui_brandmarks( ap_ui_lines( $title ) );
	foreach ( array_unique( $accents ) as $accent ) {
		$accent = trim( (string) $accent );
		if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
			$escaped = ap_ui_brandmarks( esc_html( $accent ) );
			$html    = str_replace( $escaped, '<span class="apx-g">' . $escaped . '</span>', $html );
		}
	}
	return $html;
};
$ap_title_accents = array_merge(
	array( $props['title_accent'] ),
	array_filter( is_array( $props['title_accents'] ) ? $props['title_accents'] : array(), 'is_string' )
);

$ap_cards = static function ( $list ) {
	return array_values(
		array_filter(
			is_array( $list ) ? $list : array(),
			static function ( $c ) {
				return is_array( $c ) && '' !== trim( (string) ( $c['title'] ?? '' ) );
			}
		)
	);
};

$left  = $ap_cards( $props['left'] );
$right = $ap_cards( $props['right'] );
$lines = array_values(
	array_filter(
		is_array( $props['lines'] ) ? $props['lines'] : array(),
		static function ( $l ) {
			return is_array( $l ) && ( '' !== trim( (string) ( $l['accent'] ?? '' ) ) || '' !== trim( (string) ( $l['text'] ?? '' ) ) );
		}
	)
);

// The sentence carries the link inline; substitute the escaped label so nothing
// but the fixed anchor is ever added to escaped text.
$meet_html = '';
$meet      = trim( (string) $props['meet'] );
$label     = trim( (string) $props['link_label'] );
$url       = trim( (string) $props['link_url'] );
if ( '' !== $meet ) {
	$meet_html = ap_ui_brandmarks( esc_html( $meet ) );
	if ( '' !== $label && '' !== $url ) {
		$escaped_label = ap_ui_brandmarks( esc_html( $label ) );
		$anchor        = '<a href="' . esc_url( $url ) . '">' . $escaped_label . '</a>';
		$meet_html     = str_replace( $escaped_label, $anchor, $meet_html );
	}
}

// Artwork with the Ursa AI orb painted in at a known spot — the Asset Intelligence Platform panda (Group-5452,
// 424 × 417) — renders as two layers so the orb can float above the panda's paws. The orb's place in that file
// lives in style.css (.ap-capability-scene__art--orb); any other artwork renders as the single image it always was.
$ap_orb_art = (bool) preg_match( '#/Group-5452(?:[.\-][^/]*)?$#i', (string) strtok( (string) $props['image'], '?#' ) );

$ap_column = static function ( $cards ) {
	ob_start();
	?>
	<div class="ap-capability-scene__col">
		<?php foreach ( $cards as $card ) : ?>
			<div class="ap-capability-scene__card">
				<?php if ( ! empty( $card['kicker'] ) ) : ?>
					<p class="ap-capability-scene__k"><?php echo esc_html( $card['kicker'] ); ?></p>
				<?php endif; ?>
				<h3 class="ap-capability-scene__ct"><?php echo ap_ui_brandmarks( esc_html( $card['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
				<?php if ( ! empty( $card['body'] ) ) : ?>
					<p class="ap-capability-scene__cb"><?php echo ap_ui_brandmarks( esc_html( $card['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
};
?>
<section class="ap-capability-scene<?php echo 'lg' === $props['lines_size'] ? ' ap-capability-scene--lines-lg' : ''; ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( '' !== trim( (string) $props['eyebrow'] ) ? $props['eyebrow'] : $props['title'] ) ); ?>">
	<div class="ap-capability-scene__glow" aria-hidden="true"></div>
	<div class="ap-capability-scene__wrap">
		<div class="ap-capability-scene__top">
			<div class="ap-capability-scene__head">
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="ap-capability-scene__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 class="ap-capability-scene__title"><?php echo $ap_accent( $props['title'], $ap_title_accents ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			</div>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-capability-scene__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-capability-scene__cards">
			<?php echo $ap_column( $left ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above with escaping. ?>

			<div class="ap-capability-scene__panel">
				<div class="ap-capability-scene__paneltop">
					<?php if ( $lines ) : ?>
						<p class="ap-capability-scene__lines">
							<?php foreach ( $lines as $line ) : ?>
								<span><?php if ( ! empty( $line['accent'] ) ) : ?><span class="apx-g"><?php echo esc_html( $line['accent'] ); ?></span> <?php endif; ?><?php echo esc_html( $line['text'] ?? '' ); ?></span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
					<?php if ( '' !== $meet_html ) : ?>
						<p class="ap-capability-scene__meet"><?php echo $meet_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed anchor is added. ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== trim( (string) $props['image'] ) && $ap_orb_art ) : ?>
					<?php // The orb floats on its own layer: a second copy of the same file, masked to the orb (style.css "QA 2026-10-29"). ?>
					<div class="ap-capability-scene__art ap-capability-scene__art--orb">
						<img class="ap-capability-scene__artimg" src="<?php echo esc_url( $props['image'] ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="424" height="417" loading="lazy" decoding="async" />
						<img class="ap-capability-scene__orb" src="<?php echo esc_url( $props['image'] ); ?>" alt="" aria-hidden="true" width="424" height="417" loading="lazy" decoding="async" />
					</div>
				<?php elseif ( '' !== trim( (string) $props['image'] ) ) : ?>
					<img class="ap-capability-scene__art" src="<?php echo esc_url( $props['image'] ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="424" height="417" loading="lazy" decoding="async" />
				<?php endif; ?>
			</div>

			<?php echo $ap_column( $right ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above with escaping. ?>
		</div>

		<?php
		// Optional closing buttons (Ursa AI frame 7261:111751): the outline one first, then the solid one.
		$ap_buttons = array();
		if ( '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] ) ) {
			$ap_buttons[] = '<a class="ap-capability-scene__btn ap-capability-scene__btn--outline" href="' . esc_url( $props['secondary_url'] ) . '">' . ap_ui_brandmarks( esc_html( $props['secondary_label'] ) ) . '</a>';
		}
		if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) {
			$ap_buttons[] = '<a class="ap-capability-scene__btn ap-capability-scene__btn--solid" href="' . esc_url( $props['cta_url'] ) . '">' . ap_ui_brandmarks( esc_html( $props['cta_label'] ) ) . '</a>';
		}
		?>
		<?php if ( $ap_buttons ) : ?>
			<div class="ap-capability-scene__cta"><?php echo implode( '', $ap_buttons ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></div>
		<?php endif; ?>
	</div>
</section>
