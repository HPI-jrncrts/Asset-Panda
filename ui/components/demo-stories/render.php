<?php
/**
 * demo-stories — customer stories slider (live #ap-demo-stories).
 *
 * Markup of live-pages/get-a-demo/src/content/cases.mjs renderCaseSlider(),
 * class for class. Every slide shares one grid cell, so the slider keeps the
 * tallest slide's height and auto-advance never moves the page; only the first
 * slide loads its images up front, the others wait in data-src until
 * behaviour.js brings them (or a neighbour) up.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $item ) {
			return is_array( $item ) && '' !== trim( (string) ( $item['quote'] ?? '' ) );
		}
	)
);

if ( empty( $ap_items ) ) {
	return;
}

$ap_count = count( $ap_items );
$ap_quote = trim( (string) $props['quote_icon'] );
if ( '' === $ap_quote ) {
	$ap_quote = ap_ui_asset_url( 'demo-stories', 'quote-up.svg' );
}
$ap_arrow = ap_ui_asset_url( 'demo-stories', 'icon-arrow.svg' );

$ap_px = static function ( $value ) {
	return is_numeric( $value ) && (float) $value > 0 ? (int) round( (float) $value ) : 0;
};

// The opening of an <img>: the first slide gets src, the rest data-src (see the note above).
$ap_img = static function ( $src, $eager ) {
	return '<img ' . ( $eager ? 'src' : 'data-src' ) . '="' . esc_url( $src ) . '"';
};

/*
 * Escaped story text. WordPress runs wptexturize() over block output, which would turn the
 * quotes' "..." into an ellipsis glyph; live shows plain dots, so a dot pair is split with an
 * entity texturize does not read.
 */
$ap_story_text = static function ( $text ) {
	return str_replace( '..', '.&#46;', esc_html( trim( (string) $text ) ) );
};

$ap_heading = trim( (string) $props['heading'] );
$ap_viewall = trim( (string) $props['viewall_url'] );
$ap_all     = trim( (string) $props['viewall_label'] );
?>
<section class="ap-demo-stories ap-d26-sec<?php echo $props['cta'] ? ' ap-d26-stories--cta' : ''; ?>" aria-label="<?php echo esc_attr( $ap_heading ); ?>">
	<div class="ap-d26-stories">
		<?php if ( '' !== $ap_heading ) : ?>
			<h2 class="ap-d26-sr"><?php echo esc_html( $ap_heading ); ?></h2>
		<?php endif; ?>
		<div class="ap-d26-cases" data-ap-cases data-ap-autoplay="<?php echo esc_attr( (string) max( 0, (int) $props['autoplay_ms'] ) ); ?>" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $ap_heading ); ?>">
			<div class="ap-d26-case-slides" aria-live="polite">
				<?php foreach ( $ap_items as $ap_i => $ap_item ) : ?>
					<?php
					$ap_eager = 0 === $ap_i;
					$ap_lw    = $ap_px( $ap_item['logo_w'] ?? null );
					$ap_lh    = $ap_px( $ap_item['logo_h'] ?? null );
					$ap_photo = trim( (string) ( $ap_item['image'] ?? '' ) );
					$ap_logo  = trim( (string) ( $ap_item['logo'] ?? '' ) );
					$ap_link  = trim( (string) ( $ap_item['link_url'] ?? '' ) );
					$ap_cat   = trim( (string) ( $ap_item['category'] ?? '' ) );
					$ap_title = trim( (string) ( $ap_item['title'] ?? '' ) );
					?>
					<div class="ap-d26-case<?php echo $ap_eager ? ' is-active' : ''; ?>" data-case aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $ap_i + 1 ) . ' of ' . $ap_count ); ?>">
						<div class="ap-d26-case-copy">
							<img class="ap-d26-case-icon" src="<?php echo esc_url( $ap_quote ); ?>" alt="" width="32" height="32" loading="lazy" decoding="async">
							<?php if ( '' !== $ap_cat ) : ?>
								<p class="ap-d26-case-cat"><?php echo esc_html( $ap_cat ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $ap_title ) : ?>
								<h3 class="ap-d26-case-title"><?php echo $ap_story_text( $ap_title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h3>
							<?php endif; ?>
							<p class="ap-d26-case-quote"><?php echo $ap_story_text( $ap_item['quote'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
							<div class="ap-d26-case-author"><p class="ap-d26-case-name"><?php echo esc_html( $ap_item['author'] ?? '' ); ?></p><p class="ap-d26-case-role"><?php echo esc_html( $ap_item['role'] ?? '' ); ?></p></div>
							<?php if ( '' !== $ap_link ) : ?>
								<a class="ap-d26-case-link" href="<?php echo esc_url( $ap_link ); ?>"><?php echo esc_html( $ap_item['link_label'] ?? '' ); ?> <img src="<?php echo esc_url( $ap_arrow ); ?>" alt="" width="24" height="24"></a>
							<?php endif; ?>
						</div>
						<div class="ap-d26-case-media">
							<?php if ( '' !== $ap_photo ) : ?>
								<?php echo $ap_img( $ap_photo, $ap_eager ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> alt="<?php echo esc_attr( $ap_item['image_alt'] ?? '' ); ?>" width="1106" height="620" loading="lazy" decoding="async">
							<?php endif; ?>
							<?php if ( '' !== $ap_logo ) : ?>
								<span class="ap-d26-case-logo"><?php echo $ap_img( $ap_logo, $ap_eager ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> alt="<?php echo esc_attr( $ap_item['logo_alt'] ?? '' ); ?>"<?php echo $ap_lw ? ' width="' . $ap_lw . '"' : ''; ?><?php echo $ap_lh ? ' height="' . $ap_lh . '"' : ''; ?><?php echo $ap_lw ? ' style="width:' . $ap_lw . 'px"' : ''; ?> loading="lazy" decoding="async"></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="ap-d26-case-controls">
				<?php if ( $ap_count > 1 ) : ?>
					<div class="ap-d26-case-arrows"><button type="button" class="ap-d26-case-arrow" data-case-prev aria-label="Previous story">&#8592;</button><button type="button" class="ap-d26-case-arrow" data-case-next aria-label="Next story">&#8594;</button></div>
					<div class="ap-d26-case-progress" aria-hidden="true"><span class="ap-d26-case-bar" style="width:<?php echo esc_attr( number_format( 100 / $ap_count, 4, '.', '' ) ); ?>%"></span></div>
					<div class="ap-d26-dots" role="group" aria-label="Choose a story"><?php for ( $ap_d = 0; $ap_d < $ap_count; $ap_d++ ) : ?><button type="button" class="ap-d26-dot<?php echo 0 === $ap_d ? ' is-active' : ''; ?>" data-case-dot aria-label="<?php echo esc_attr( 'Story ' . ( $ap_d + 1 ) . ' of ' . $ap_count ); ?>"<?php echo 0 === $ap_d ? ' aria-current="true"' : ''; ?>></button><?php endfor; ?></div>
				<?php endif; ?>
				<?php if ( '' !== $ap_viewall && '' !== $ap_all ) : ?>
					<a class="ap-d26-case-all" href="<?php echo esc_url( $ap_viewall ); ?>"><?php echo esc_html( $ap_all ); ?> <img src="<?php echo esc_url( $ap_arrow ); ?>" alt="" width="24" height="24"></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
