<?php
/**
 * orbit-hero — the platform hero.
 *
 * Matches the live .apx-hero on /asset-intelligence-platform/: the copy column
 * beside a stage where the mascot holds the assistant orb and six labelled
 * badges orbit it, with a chip strip underneath. The stage is decorative.
 *
 * Badges sit in named slots (the live CSS places each one), so a page picks the
 * slot rather than supplying coordinates. The inner class names are the live
 * ones, so this stays diffable against the ported stylesheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item, $class = '' ) {
	$svg = ap_ui_svg( $item['icon_svg'] ?? '' );
	if ( '' !== $svg ) {
		return '<span class="' . esc_attr( trim( 'ap-inline-icon ' . $class ) ) . '" aria-hidden="true">' . $svg . '</span>';
	}
	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	return '' !== $icon ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $icon ) . '" alt="" loading="lazy" decoding="async" />' : '';
};

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="apx-grad">' . $escaped . '</span>', $html );
	}
	return $html;
};

// The live layout gives each badge its own slot; "rev" ones put the text first.
$slots  = array( 'inspect', 'work', 'assets', 'analytics', 'insights', 'compliance' );
$rev    = array( 'inspect', 'assets', 'insights' );
$badges = array_values(
	array_filter(
		is_array( $props['badges'] ) ? $props['badges'] : array(),
		static function ( $b ) {
			return is_array( $b ) && '' !== trim( (string) ( $b['title'] ?? '' ) );
		}
	)
);
$feats = array_values(
	array_filter(
		is_array( $props['features'] ) ? $props['features'] : array(),
		static function ( $f ) {
			return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) );
		}
	)
);

// Live's inline pill icons stroke with url(#apg), a gradient the section defines
// once. Without it the strokes have no paint and the icons vanish.
$ap_needs_gradient = false;
foreach ( $feats as $feat ) {
	if ( '' !== trim( (string) ( $feat['icon_svg'] ?? '' ) ) ) {
		$ap_needs_gradient = true;
		break;
	}
}
?>
<?php
// No mascot, orb or badges (Ursa AI frame 7261:107107): no stage at all, so tablets and phones get no empty block.
$ap_bare = '' === trim( (string) $props['panda'] ) && '' === trim( (string) $props['orb'] ) && ! $badges;
?>
<section class="ap-orbit-hero<?php echo $ap_bare ? ' ap-orbit-hero--bare' : ''; ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( $props['title'] ) ); ?>">
	<?php if ( $ap_needs_gradient ) : ?>
		<svg class="ap-orbit-hero__defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><linearGradient id="apg" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#5044E1"></stop><stop offset="1" stop-color="#00A991"></stop></linearGradient></defs></svg>
	<?php endif; ?>
	<div class="ap-orbit-hero__bg" aria-hidden="true"></div>
	<div class="ap-orbit-hero__wrap">
		<div class="ap-orbit-hero__veil" aria-hidden="true"></div>
		<div class="ap-orbit-hero__grid">
			<div class="ap-orbit-hero__content">
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="ap-orbit-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="ap-orbit-hero__title"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
				<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
					<p class="ap-orbit-hero__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>
				<div class="ap-orbit-hero__cta">
					<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
						<a class="apx-btn apx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $props['cta_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] ) ) : ?>
						<a class="apx-btn apx-btn--ghost" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $props['secondary_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $ap_bare ) : ?>
			<div class="ap-orbit-hero__stage" aria-hidden="true">
				<?php if ( '' !== trim( (string) $props['panda'] ) ) : ?>
					<img class="ap-orbit-hero__panda" src="<?php echo esc_url( $props['panda'] ); ?>" alt="<?php echo esc_attr( $props['panda_alt'] ); ?>" decoding="async" fetchpriority="high" />
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $props['orb'] ) ) : ?>
					<div class="apx-orb"><img src="<?php echo esc_url( $props['orb'] ); ?>" alt="" decoding="async" /></div>
				<?php endif; ?>
				<?php foreach ( array_slice( $badges, 0, 6 ) as $i => $badge ) : ?>
					<?php
					$slot    = in_array( $badge['slot'] ?? '', $slots, true ) ? $badge['slot'] : $slots[ $i ];
					$is_rev  = in_array( $slot, $rev, true );
					$text    = '<span class="apx-badge__text"><b>' . esc_html( $badge['title'] ) . '</b><i>' . esc_html( $badge['note'] ?? '' ) . '</i></span>';
					$graphic = '<span class="apx-badge__icon">' . $ap_icon( $badge ) . '</span>';
					?>
					<div class="apx-badge<?php echo $is_rev ? ' apx-badge--rev' : ''; ?> apx-badge--<?php echo esc_attr( $slot ); ?>">
						<?php
						echo $is_rev ? $text . $graphic : $graphic . $text; // phpcs:ignore WordPress.Security.EscapeOutput -- both halves escaped above.
						?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $feats ) : ?>
			<div class="ap-orbit-hero__ticker">
				<ul class="ap-orbit-hero__pills">
					<?php foreach ( array( false, true ) as $dup ) : ?>
						<?php foreach ( $feats as $feat ) : ?>
							<li class="apx-pill<?php echo $dup ? ' apx-pill--dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_icon( $feat, 'apx-ic' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?><?php echo esc_html( $feat['label'] ); ?></li>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</section>
