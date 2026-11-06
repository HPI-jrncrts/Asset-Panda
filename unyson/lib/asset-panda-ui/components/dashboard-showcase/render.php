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
$chip_tones = array( 'em', 'in', 'lb', 'yl', 'vi', 'bl' );

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
		if ( '' === $row_title && '' === $row_sub && '' === $chip && empty( $row['parts'] ) ) {
			continue;
		}
		$chip_tone = trim( (string) ( $row['chip_tone'] ?? '' ) );
		// Live rows are an ordered run of typed spans (code, title, num, sub,
		// sub14, chip) and sometimes an avatar; parts keeps that order and type.
		$parts = array();
		foreach ( is_array( $row['parts'] ?? null ) ? $row['parts'] : array() as $part ) {
			$kind = is_array( $part ) ? (string) ( $part['kind'] ?? '' ) : '';
			if ( 'avatar' === $kind && ! empty( $part['image'] ) ) {
				$parts[] = array( 'kind' => 'avatar', 'image' => (string) $part['image'] );
			} elseif ( in_array( $kind, array( 'code', 'title', 'num', 'sub', 'sub14', 'chip' ), true ) && '' !== trim( (string) ( $part['text'] ?? '' ) ) ) {
				$tone    = trim( (string) ( $part['tone'] ?? '' ) );
				$parts[] = array(
					'kind'  => $kind,
					'text'  => trim( (string) $part['text'] ),
					'tone'  => in_array( $tone, $chip_tones, true ) ? $tone : 'in',
					'muted' => ! empty( $part['muted'] ),
				);
			}
		}
		$layout = (string) ( $row['layout'] ?? '' );
		$rows[] = array(
			'title'     => $row_title,
			'sub'       => $row_sub,
			'chip'      => $chip,
			'chip_tone' => in_array( $chip_tone, $chip_tones, true ) ? $chip_tone : 'in',
			'parts'     => $parts,
			'layout'    => in_array( $layout, array( 'person', 'oper' ), true ) ? $layout : '',
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
<?php
// Button row: some pages stack the buttons full width on phones, and Police
// tightens the space above them on tablets. Both reach CSS as a class and a
// whole number.
$ap_dash_classes = array( 'ap-dashboard-showcase' );
if ( in_array( (int) $props['cta_stack'], array( 640, 768 ), true ) ) {
	$ap_dash_classes[] = 'ap-dashboard-showcase--stack-' . (int) $props['cta_stack'];
}
if ( ! empty( $props['outline_fixed'] ) ) {
	$ap_dash_classes[] = 'ap-dashboard-showcase--outline-fixed';
}
$ap_dash_top = (int) $props['cta_top_tablet'];
$ap_dash_vars = array();
if ( $ap_dash_top >= 0 && $ap_dash_top <= 120 && 24 !== $ap_dash_top ) {
	$ap_dash_vars[] = '--ap-dash-cta-top-t:' . $ap_dash_top . 'px';
}
$ap_lede_max = (int) $props['lede_max'];
if ( $ap_lede_max >= 200 && $ap_lede_max <= 1200 && 856 !== $ap_lede_max ) {
	$ap_dash_vars[] = '--ap-dash-lede-max:' . $ap_lede_max . 'px';
}
$ap_lede_m = (int) $props['lede_size_m'];
if ( $ap_lede_m >= 12 && $ap_lede_m <= 28 && 18 !== $ap_lede_m ) {
	$ap_dash_vars[] = '--ap-dash-lede-m:' . $ap_lede_m . 'px';
}
?>
<section class="<?php echo esc_attr( implode( ' ', $ap_dash_classes ) ); ?>"<?php echo $ap_dash_vars ? ' style="' . esc_attr( implode( ';', $ap_dash_vars ) ) . '"' : ''; ?>>
	<?php if ( '' !== $background ) : ?>
		<img class="ap-dashboard-showcase__bg" src="<?php echo esc_url( $background ); ?>" alt="" aria-hidden="true" loading="eager" fetchpriority="low" decoding="async" />
	<?php endif; ?>

	<div class="ap-dashboard-showcase__inner">
		<div class="ap-dashboard-showcase__header"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-dashboard-showcase__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-dashboard-showcase__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-dashboard-showcase__lede"><?php echo ap_ui_brandmarks( esc_html( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $image || ! empty( $tips ) || ! empty( $orbs ) ) : ?>
			<div class="ap-dashboard-showcase__stage">
				<?php if ( '' !== $image ) : ?>
					<figure class="ap-dashboard-showcase__shot">
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="eager" fetchpriority="low" decoding="async" />
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
							<div class="ap-dashboard-showcase__tip-row<?php echo ( $r === count( $tip['rows'] ) - 1 ) ? ' ap-dashboard-showcase__tip-row--last' : ''; ?><?php echo '' !== $row['layout'] ? ' ap-dashboard-showcase__tip-row--' . esc_attr( $row['layout'] ) : ''; ?>">
								<?php if ( $row['parts'] ) : ?>
									<?php
									$ap_open = false;
									foreach ( $row['parts'] as $part ) :
										if ( 'avatar' === $part['kind'] ) :
											?>
											<img class="ap-dashboard-showcase__tip-avatar" src="<?php echo esc_url( $part['image'] ); ?>" alt="" loading="lazy" decoding="async" />
											<?php
											// The person row stacks its text beside the avatar.
											if ( 'person' === $row['layout'] && ! $ap_open ) :
												$ap_open = true;
												?>
												<span class="ap-dashboard-showcase__tip-person-txt">
											<?php endif; ?>
										<?php elseif ( 'chip' === $part['kind'] ) : ?>
											<span class="ap-dashboard-showcase__chip ap-dashboard-showcase__chip--<?php echo esc_attr( $part['tone'] ); ?>"><?php echo esc_html( $part['text'] ); ?></span>
										<?php else : ?>
											<span class="ap-dashboard-showcase__tip-<?php echo esc_attr( $part['kind'] ); ?><?php echo $part['muted'] ? ' ap-dashboard-showcase__tip-' . esc_attr( $part['kind'] ) . '--muted' : ''; ?>"><?php echo esc_html( $part['text'] ); ?></span>
										<?php endif; ?>
									<?php endforeach; ?>
									<?php if ( $ap_open ) : ?>
										</span>
									<?php endif; ?>
								<?php else : ?>
								<?php if ( '' !== $row['title'] ) : ?>
									<span class="ap-dashboard-showcase__tip-title"><?php echo esc_html( $row['title'] ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $row['chip'] ) : ?>
									<span class="ap-dashboard-showcase__chip ap-dashboard-showcase__chip--<?php echo esc_attr( $row['chip_tone'] ); ?>"><?php echo esc_html( $row['chip'] ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $row['sub'] ) : ?>
									<span class="ap-dashboard-showcase__tip-sub14"><?php echo esc_html( $row['sub'] ); ?></span>
								<?php endif; ?>
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

		<?php if ( '' !== $sub_title || '' !== $sub_body ) : ?>
			<div class="ap-dashboard-showcase__sub"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<?php if ( '' !== $sub_title ) : ?>
					<h3><?php echo ap_ui_brandmarks( esc_html( $sub_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
				<?php endif; ?>
				<?php if ( '' !== $sub_body ) : ?>
					<?php // Live writes "UrsaAI" here as plain text, so no brand mark. ?>
					<p><?php echo ap_ui_lines( $sub_body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $ctas ) : ?>
			<div class="ap-dashboard-showcase__ctas"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<?php foreach ( $ctas as $i => $cta ) : ?>
					<a class="ap-dashboard-showcase__btn<?php echo 0 === $i ? '' : ' ap-dashboard-showcase__btn--outline'; ?>" href="<?php echo esc_url( $cta['url'] ); ?>"><?php echo ! empty( $cta['mark'] ) ? ap_ui_brandmarks( esc_html( $cta['label'] ) ) : esc_html( $cta['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>
</section>
