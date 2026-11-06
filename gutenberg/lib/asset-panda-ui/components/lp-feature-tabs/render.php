<?php
/**
 * lp-feature-tabs — a tabbed library of checked features.
 *
 * Live's /solutions/asset-management-ot-ga/ lists 45 features under five tab
 * headings (Asset Tracking, Configuration, Mobile app, Ease of use,
 * Security) in a slick slider. Here the tabs are radio inputs and labels, so
 * switching needs no script: every radio precedes the tab bar and the panels,
 * and the stylesheet shows the panel whose radio is checked (slots 1-8).
 * Keyboard users move between tabs with the arrow keys, as in any radio group.
 *
 * @var array $props Validated props.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
$tabs  = array();
foreach ( is_array( $props['tabs'] ) ? $props['tabs'] : array() as $tab ) {
	if ( ! is_array( $tab ) || '' === trim( (string) ( $tab['label'] ?? '' ) ) ) {
		continue;
	}
	$items = array();
	foreach ( is_array( $tab['items'] ?? null ) ? $tab['items'] : array() as $item ) {
		if ( is_array( $item ) && '' !== trim( (string) ( $item['title'] ?? '' ) ) ) {
			$items[] = array(
				'title' => trim( (string) $item['title'] ),
				'body'  => trim( (string) ( $item['body'] ?? '' ) ),
			);
		}
	}
	if ( $items ) {
		$tabs[] = array(
			'label' => trim( (string) $tab['label'] ),
			'items' => $items,
		);
	}
	if ( count( $tabs ) >= 8 ) {
		break;
	}
}
if ( '' === $title || empty( $tabs ) ) {
	return;
}

$anchor  = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$tone    = 'white' === $props['tone'] ? 'white' : 'soft';
$eyebrow = trim( (string) $props['eyebrow'] );
$intro   = trim( (string) $props['intro'] );
$multi   = count( $tabs ) > 1;
static $ap_lp_ftabs_n = 0;
++$ap_lp_ftabs_n;
$uid = 'ap-lp-ftabs-' . $ap_lp_ftabs_n;
?>
<section class="ap-lp-ftabs ap-lp-ftabs--<?php echo esc_attr( $tone ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<svg class="ap-lp-ftabs__defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><linearGradient id="<?php echo esc_attr( $uid ); ?>-g" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#5044e1"/><stop offset=".7" stop-color="#00a991"/></linearGradient></defs></svg>
	<div class="ap-lp-ftabs__inner">
		<div class="ap-lp-ftabs__head" data-ap-reveal>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-lp-ftabs__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-lp-ftabs__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="ap-lp-ftabs__intro"><?php echo ap_ui_lines( $intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
			<?php endif; ?>
		</div>
		<div class="ap-lp-ftabs__box<?php echo $multi ? '' : ' ap-lp-ftabs__box--single'; ?>">
			<?php if ( $multi ) : ?>
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<input class="ap-lp-ftabs__radio" type="radio" name="<?php echo esc_attr( $uid ); ?>" id="<?php echo esc_attr( $uid . '-' . ( $i + 1 ) ); ?>"<?php echo 0 === $i ? ' checked' : ''; ?> />
				<?php endforeach; ?>
				<div class="ap-lp-ftabs__bar">
					<?php foreach ( $tabs as $i => $tab ) : ?>
						<label class="ap-lp-ftabs__tab" for="<?php echo esc_attr( $uid . '-' . ( $i + 1 ) ); ?>"><?php echo esc_html( $tab['label'] ); ?><span class="ap-lp-ftabs__count"><?php echo esc_html( (string) count( $tab['items'] ) ); ?></span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="ap-lp-ftabs__panels">
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<div class="ap-lp-ftabs__panel">
						<?php if ( $multi ) : ?>
							<h3 class="ap-lp-ftabs__panel-title"><?php echo esc_html( $tab['label'] ); ?></h3>
						<?php endif; ?>
						<ul class="ap-lp-ftabs__grid">
							<?php foreach ( $tab['items'] as $item ) : ?>
								<li class="ap-lp-ftabs__item">
									<svg class="ap-lp-ftabs__check" width="32" height="32" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><circle cx="16" cy="16" r="16" fill="url(#<?php echo esc_attr( $uid ); ?>-g)"/><path d="m10 16.5 4 4 8-9" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<div class="ap-lp-ftabs__text">
										<<?php echo $multi ? 'h4' : 'h3'; ?> class="ap-lp-ftabs__item-title"><?php echo esc_html( $item['title'] ); ?></<?php echo $multi ? 'h4' : 'h3'; ?>>
										<?php if ( '' !== $item['body'] ) : ?>
											<p class="ap-lp-ftabs__item-body"><?php echo esc_html( $item['body'] ); ?></p>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
