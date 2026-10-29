<?php
/**
 * readiness-hero — the Emergency Preparedness / School Reunification hero.
 *
 * Matches the live `.epx-hero` section: copy and badges beside a photo with
 * three stat cards whose connector lines meet at the Asset Panda badge, then a
 * feature ticker. At 1000px and below the stage stacks (copy, visual, ticker);
 * at 700px and below the cards, lines and photo give way to one cards image and
 * the ticker scrolls, so each ticker item is rendered twice (the copy hidden
 * from assistive tech). Every value is a prop; the stat cards are structured.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'violet', 'orange', 'red' );

$cards = array();
foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( ! is_array( $card ) || '' === trim( (string) ( $card['title'] ?? '' ) ) ) {
		continue;
	}
	$tone    = (string) ( $card['tone'] ?? '' );
	$cards[] = array(
		'tone'   => in_array( $tone, $tones, true ) ? $tone : $tones[ count( $cards ) % 3 ],
		'kicker' => trim( (string) ( $card['kicker'] ?? '' ) ),
		'title'  => trim( (string) $card['title'] ),
		'sub'    => trim( (string) ( $card['sub'] ?? '' ) ),
		'tag'    => trim( (string) ( $card['tag'] ?? '' ) ),
		'icon'   => ap_ui_svg( $card['icon_svg'] ?? '' ),
		'dot'    => trim( (string) ( $card['dot'] ?? '' ) ),
	);
	if ( 3 === count( $cards ) ) {
		break;
	}
}

$lines = array();
foreach ( is_array( $props['lines'] ) ? $props['lines'] : array() as $line ) {
	$svg = ap_ui_svg( $line );
	if ( '' !== $svg ) {
		$lines[] = $svg;
	}
}
$lines = array_slice( $lines, 0, 3 );

$badges = array_values(
	array_filter(
		is_array( $props['badges'] ) ? $props['badges'] : array(),
		static function ( $b ) {
			return is_array( $b ) && '' !== trim( (string) ( $b['image'] ?? '' ) );
		}
	)
);

$features = array();
foreach ( is_array( $props['features'] ) ? $props['features'] : array() as $f ) {
	if ( is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) ) ) {
		$features[] = array(
			'label' => trim( (string) $f['label'] ),
			'icon'  => ap_ui_svg( $f['icon_svg'] ?? '' ),
		);
	}
}

$title      = trim( (string) $props['title'] );
$accent     = trim( (string) $props['title_accent'] );
$title_html = ap_ui_lines( $title );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped    = esc_html( $accent );
	$title_html = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $title_html );
}

$vars = array();
if ( '' !== trim( (string) $props['background'] ) ) {
	$vars[] = '--ap-readiness-hero-bg:url("' . esc_url_raw( $props['background'] ) . '")';
}
if ( '' !== trim( (string) $props['background_mobile'] ) ) {
	$vars[] = '--ap-readiness-hero-bg-m:url("' . esc_url_raw( $props['background_mobile'] ) . '")';
}
// First-screen backdrop (a CSS background): preload it, unless phones get a different file.
$ap_pre = '' === trim( (string) $props['background_mobile'] ) ? trim( (string) $props['background'] ) : '';
if ( '' !== $ap_pre && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $ap_pre ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() in the loader.
}

$defs    = ap_ui_svg( $props['defs_svg'] );
$photo   = trim( (string) $props['photo'] );
$mobile  = trim( (string) $props['mobile_image'] );
$logo    = trim( (string) $props['logo_badge'] );
$variant = (string) $props['variant'];
$ap_pill = static function ( $f, $dup ) {
	return '<span class="ap-readiness-hero__pill' . ( $dup ? ' ap-readiness-hero__pill--dup" aria-hidden="true' : '' ) . '">' . $f['icon'] . ' ' . esc_html( $f['label'] ) . '</span>';
};
?>
<section class="ap-readiness-hero<?php echo 'reunification' === $variant ? ' ap-readiness-hero--reunification' : ''; ?>"<?php echo $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : ''; ?>>
	<?php if ( '' !== $defs ) : ?>
		<span class="ap-readiness-hero__defs" aria-hidden="true"><?php echo $defs; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
	<?php endif; ?>
	<div class="ap-readiness-hero__orbs" aria-hidden="true"></div>
	<div class="ap-readiness-hero__stage">
		<div class="ap-readiness-hero__visual">
			<?php if ( '' !== $photo ) : ?>
				<img class="ap-readiness-hero__photo" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" decoding="async" fetchpriority="high" />
			<?php endif; ?>
			<?php if ( '' !== $mobile ) : ?>
				<img class="ap-readiness-hero__mobcards" src="<?php echo esc_url( $mobile ); ?>" alt="<?php echo esc_attr( $props['mobile_image_alt'] ); ?>" decoding="async" />
			<?php endif; ?>
			<?php echo implode( '', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput -- each sanitised by ap_ui_svg(). ?>
			<?php foreach ( $cards as $i => $card ) : ?>
				<div class="epx-hcard epx-hcard--<?php echo (int) $i + 1; ?>">
					<div class="epx-hcard__rev"><div class="epx-hcard__flo epx-hcard__card epx-hcard__card--<?php echo esc_attr( $card['tone'] ); ?>">
						<?php if ( '' !== $card['icon'] ) : ?>
							<span class="epx-hcard__ico epx-hcard__ico--<?php echo esc_attr( $card['tone'] ); ?>"><?php echo $card['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php endif; ?>
						<span class="epx-hcard__body">
							<?php if ( '' !== $card['kicker'] ) : ?>
								<span class="epx-hcard__kick"><?php echo esc_html( $card['kicker'] ); ?></span>
							<?php endif; ?>
							<span class="epx-hcard__ttl"><?php echo esc_html( $card['title'] ); ?></span>
							<?php if ( '' !== $card['sub'] ) : ?>
								<span class="epx-hcard__sub"><?php echo esc_html( $card['sub'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $card['tag'] ) : ?>
								<span class="epx-hcard__tag epx-hcard__tag--<?php echo esc_attr( $card['tone'] ); ?>"><?php echo esc_html( $card['tag'] ); ?></span>
							<?php endif; ?>
						</span>
						<?php if ( '' !== $card['dot'] ) : ?>
							<img class="epx-hcard__dot" src="<?php echo esc_url( $card['dot'] ); ?>" alt="" decoding="async" />
						<?php endif; ?>
					</div></div>
				</div>
			<?php endforeach; ?>
			<?php if ( '' !== $logo ) : ?>
				<div class="ap-readiness-hero__logowrap">
					<span class="ap-readiness-hero__logoring" aria-hidden="true"></span>
					<img class="ap-readiness-hero__logobadge" src="<?php echo esc_url( $logo ); ?>" alt="" decoding="async" />
				</div>
			<?php endif; ?>
		</div>

		<div class="ap-readiness-hero__content">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-readiness-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="ap-readiness-hero__h1"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and a fixed span are added. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-readiness-hero__lede"><?php echo esc_html( $props['lede'] ); ?></p>
			<?php endif; ?>
			<div class="ap-readiness-hero__cta">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="epx-btn epx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $badges ) : ?>
					<span class="ap-readiness-hero__badges">
						<?php foreach ( $badges as $b ) : ?>
							<img src="<?php echo esc_url( $b['image'] ); ?>" alt="<?php echo esc_attr( $b['alt'] ?? '' ); ?>" decoding="async" />
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $features ) : ?>
			<div class="ap-readiness-hero__ticker">
				<div class="ap-readiness-hero__strip">
					<?php
					foreach ( $features as $f ) {
						echo $ap_pill( $f, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
					}
					foreach ( $features as $f ) {
						echo $ap_pill( $f, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<div class="ap-readiness-hero__veil" aria-hidden="true"></div>
</section>
