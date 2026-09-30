<?php
/**
 * dashboard-showcase — "configurable to your workflow".
 *
 * Matches the live `.ap-dashboard` section: a faded screenshot with up to four
 * tip cards and eight image orbs floating over it, each taking a placement
 * from the design by its position in the list. Below 768px the stage collapses:
 * the screenshot stands at full opacity and only the two short tips follow it.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tip_tones  = array( 'violet', 'blue', 'green', 'yellow' );
$orb_tones  = array( 'red', 'lb', 'yl', 'green', 'in' );
$chip_tones = array( 'em', 'in', 'lb', 'yl' );

$tips = array();
foreach ( is_array( $props['tips'] ) ? $props['tips'] : array() as $tip ) {
	if ( ! is_array( $tip ) || '' === trim( (string) ( $tip['title'] ?? '' ) ) ) {
		continue;
	}
	$rows = array();
	foreach ( is_array( $tip['rows'] ?? null ) ? $tip['rows'] : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$row_title = trim( (string) ( $row['title'] ?? '' ) );
		$row_sub   = trim( (string) ( $row['sub'] ?? '' ) );
		$chip      = trim( (string) ( $row['chip'] ?? '' ) );
		if ( '' === $row_title && '' === $row_sub && '' === $chip ) {
			continue;
		}
		$chip_tone = trim( (string) ( $row['chip_tone'] ?? '' ) );
		$rows[]    = array(
			'title'     => $row_title,
			'sub'       => $row_sub,
			'chip'      => $chip,
			'chip_tone' => in_array( $chip_tone, $chip_tones, true ) ? $chip_tone : 'in',
		);
	}
	$tone   = trim( (string) ( $tip['tone'] ?? '' ) );
	$tips[] = array(
		'title'    => trim( (string) $tip['title'] ),
		'icon'     => trim( (string) ( $tip['icon'] ?? '' ) ),
		'icon_svg' => ap_ui_svg( $tip['icon_svg'] ?? '' ),
		'icon_alt' => (string) ( $tip['icon_alt'] ?? '' ),
		'tone'     => in_array( $tone, $tip_tones, true ) ? $tone : $tip_tones[ count( $tips ) % count( $tip_tones ) ],
		'rows'     => $rows,
	);
}

$orbs = array();
foreach ( is_array( $props['orbs'] ) ? $props['orbs'] : array() as $orb ) {
	if ( ! is_array( $orb ) || empty( $orb['image'] ) ) {
		continue;
	}
	$tone   = trim( (string) ( $orb['tone'] ?? '' ) );
	$zoom   = isset( $orb['zoom'] ) ? (int) $orb['zoom'] : 0;
	$orbs[] = array(
		'image' => trim( (string) $orb['image'] ),
		'alt'   => (string) ( $orb['alt'] ?? '' ),
		'tone'  => in_array( $tone, $orb_tones, true ) ? $tone : $orb_tones[ count( $orbs ) % count( $orb_tones ) ],
		'zoom'  => ( $zoom >= 50 && $zoom <= 300 ) ? $zoom : 0,
	);
}

$title      = trim( (string) $props['title'] );
$accent     = trim( (string) $props['title_accent'] );
$eyebrow    = trim( (string) $props['eyebrow'] );
$image      = trim( (string) $props['image'] );
$background = trim( (string) $props['background'] );
$sub_title  = trim( (string) $props['sub_title'] );
$sub_body   = trim( (string) $props['sub_body'] );

// The accent is a slice of the heading: escape first, then wrap that slice.
$title_html = ap_ui_brandmarks( ap_ui_lines( $title ) );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped_accent = ap_ui_brandmarks( esc_html( $accent ) );
	$title_html     = str_replace(
		$escaped_accent,
		'<span class="ap-dashboard-showcase__accent">' . $escaped_accent . '</span>',
		$title_html
	);
}
?>
<section class="ap-dashboard-showcase">
	<?php if ( '' !== $background ) : ?>
		<img class="ap-dashboard-showcase__bg" src="<?php echo esc_url( $background ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
	<?php endif; ?>

	<div class="ap-dashboard-showcase__inner">
		<div class="ap-dashboard-showcase__header"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-dashboard-showcase__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-dashboard-showcase__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
		</div>

		<?php if ( '' !== $image || ! empty( $tips ) || ! empty( $orbs ) ) : ?>
			<div class="ap-dashboard-showcase__stage">
				<?php if ( '' !== $image ) : ?>
					<figure class="ap-dashboard-showcase__shot">
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="lazy" decoding="async" />
					</figure>
				<?php endif; ?>

				<?php foreach ( $tips as $tip ) : ?>
					<div class="ap-dashboard-showcase__tip ap-dashboard-showcase__tip--<?php echo esc_attr( $tip['tone'] ); ?>">
						<div class="ap-dashboard-showcase__tip-head">
							<?php if ( '' !== $tip['icon_svg'] ) : ?>
								<span class="ap-dashboard-showcase__tip-icn" aria-hidden="true"><?php echo $tip['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
							<?php elseif ( '' !== $tip['icon'] ) : ?>
								<span class="ap-dashboard-showcase__tip-icn">
									<img src="<?php echo esc_url( $tip['icon'] ); ?>" alt="<?php echo esc_attr( $tip['icon_alt'] ); ?>" loading="lazy" decoding="async" />
								</span>
							<?php endif; ?>
							<strong><?php echo esc_html( $tip['title'] ); ?></strong>
						</div>
						<?php foreach ( $tip['rows'] as $r => $row ) : ?>
							<div class="ap-dashboard-showcase__tip-row<?php echo ( $r === count( $tip['rows'] ) - 1 ) ? ' ap-dashboard-showcase__tip-row--last' : ''; ?>">
								<?php if ( '' !== $row['title'] ) : ?>
									<span class="ap-dashboard-showcase__tip-title"><?php echo esc_html( $row['title'] ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $row['chip'] ) : ?>
									<span class="ap-dashboard-showcase__chip ap-dashboard-showcase__chip--<?php echo esc_attr( $row['chip_tone'] ); ?>"><?php echo esc_html( $row['chip'] ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $row['sub'] ) : ?>
									<span class="ap-dashboard-showcase__tip-sub"><?php echo esc_html( $row['sub'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<?php foreach ( $orbs as $orb ) : ?>
					<span class="ap-dashboard-showcase__orb ap-dashboard-showcase__orb--<?php echo esc_attr( $orb['tone'] ); ?>" aria-hidden="true"<?php echo $orb['zoom'] ? ' style="' . esc_attr( '--ap-orb-zoom:' . $orb['zoom'] . '%' ) . '"' : ''; ?>>
						<img src="<?php echo esc_url( $orb['image'] ); ?>" alt="<?php echo esc_attr( $orb['alt'] ); ?>" loading="lazy" decoding="async" />
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		$checks = array_values(
			array_filter(
				array_map(
					static function ( $c ) {
						return trim( (string) $c );
					},
					is_array( $props['checks'] ) ? $props['checks'] : array()
				),
				static function ( $c ) {
					return '' !== $c;
				}
			)
		);
		$ctas   = array_values(
			array_filter(
				is_array( $props['ctas'] ) ? $props['ctas'] : array(),
				static function ( $c ) {
					return is_array( $c ) && '' !== trim( (string) ( $c['label'] ?? '' ) ) && '' !== trim( (string) ( $c['url'] ?? '' ) );
				}
			)
		);
		?>
		<?php if ( $checks ) : ?>
			<ul class="ap-dashboard-showcase__checks"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<?php foreach ( $checks as $check ) : ?>
					<li><span class="ap-dashboard-showcase__check" aria-hidden="true"></span><span><?php echo ap_ui_brandmarks( esc_html( $check ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $ctas ) : ?>
			<div class="ap-dashboard-showcase__ctas"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<?php foreach ( $ctas as $i => $cta ) : ?>
					<a class="ap-dashboard-showcase__btn<?php echo 0 === $i ? '' : ' ap-dashboard-showcase__btn--outline'; ?>" href="<?php echo esc_url( $cta['url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $cta['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $sub_title || '' !== $sub_body ) : ?>
			<div class="ap-dashboard-showcase__sub"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<?php if ( '' !== $sub_title ) : ?>
					<h3><?php echo ap_ui_brandmarks( esc_html( $sub_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
				<?php endif; ?>
				<?php if ( '' !== $sub_body ) : ?>
					<p><?php echo ap_ui_brandmarks( ap_ui_lines( $sub_body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
