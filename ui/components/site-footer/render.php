<?php
/**
 * site-footer — the global footer: the closing CTA band, the brand column
 * (logo, address, social links, app store badges), the link columns and the
 * legal line.
 *
 * Matches the live 2026 footer (#ap-final-footer-v1, injected sitewide by the
 * ap-global-header-footer plugin) class for class, so the port stays diffable
 * and the kit rules that adjust it per page keep working: demo-footer moves
 * its compact footer into .ap-final-footer-inner and hides the CTA band and
 * link columns; demo-confirm hides the CTA band. The root keeps live's id for
 * those rules; the footer renders once per page, from the theme's footer
 * template part, which already supplies the <footer> landmark (hence a <div>).
 *
 * Two live details that are script, not markup, are built in: the primary
 * button carries the hover-gradient layer and label span that live's
 * sitewide button script injects, and live's inline style on the badge
 * column lives in style.css.
 *
 * Images are file names in this component's assets/ folder or full URLs.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A file name resolves to this component's assets/; a URL or path passes through.
$ap_sf_src = static function ( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|/)#i', $value ) ) {
		return $value;
	}
	return ap_ui_asset_url( 'site-footer', $value );
};

$ap_sf_rows = static function ( $rows, array $required ) {
	$out = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		foreach ( $required as $key ) {
			if ( '' === trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				continue 2;
			}
		}
		$out[] = $row;
	}
	return $out;
};

$socials = $ap_sf_rows( $props['socials'], array( 'url', 'icon' ) );
$apps    = $ap_sf_rows( $props['apps'], array( 'url', 'image' ) );

$columns = array();
foreach ( is_array( $props['columns'] ) ? $props['columns'] : array() as $col ) {
	if ( ! is_array( $col ) ) {
		continue;
	}
	$links = $ap_sf_rows( $col['links'] ?? array(), array( 'label', 'url' ) );
	if ( $links || '' !== trim( (string) ( $col['heading'] ?? '' ) ) ) {
		$columns[] = array(
			'heading' => trim( (string) ( $col['heading'] ?? '' ) ),
			'links'   => $links,
		);
	}
}

$bg        = $ap_sf_src( $props['background'] );
$cta_image = $ap_sf_src( $props['cta_image'] );
$logo      = $ap_sf_src( $props['logo'] );
$show_cta  = $props['show_cta'] && ( '' !== trim( (string) $props['cta_title'] ) || '' !== trim( (string) $props['primary_label'] ) );

// Optional accessible name, printed only when set.
$ap_sf_aria = static function ( $label ) {
	$label = trim( (string) $label );
	return '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '';
};
?>
<div id="ap-final-footer-v1" class="ap-site-footer"<?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-site-footer-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
	<div class="ap-final-footer-inner">
		<?php if ( $show_cta ) : ?>
			<section class="ap-final-cta-section"<?php echo $ap_sf_aria( $props['cta_aria'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
				<div class="ap-final-cta-card">
					<div class="ap-final-cta-copy">
						<div class="ap-text-group">
							<div class="ap-heading-group">
								<?php if ( '' !== trim( (string) $props['cta_eyebrow'] ) ) : ?>
									<p class="ap-eyebrow"><?php echo esc_html( trim( (string) $props['cta_eyebrow'] ) ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== trim( (string) $props['cta_title'] ) ) : ?>
									<h2><?php echo ap_ui_lines( $props['cta_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
								<?php endif; ?>
							</div>
							<?php if ( '' !== trim( (string) $props['cta_body'] ) ) : ?>
								<div class="ap-paragraph-group">
									<p><?php echo ap_ui_lines( $props['cta_body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
								</div>
							<?php endif; ?>
						</div>
						<div class="ap-btn-group"<?php echo $ap_sf_aria( $props['cta_buttons_aria'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
							<?php if ( '' !== trim( (string) $props['primary_label'] ) ) : ?>
								<a href="<?php echo esc_url( $props['primary_url'] ); ?>" class="ap-btn ap-btn-primary"><span class="ap-grad-hov-ov" aria-hidden="true"></span><span class="ap-btn-lbl"><?php echo esc_html( trim( (string) $props['primary_label'] ) ); ?></span></a>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $props['secondary_label'] ) ) : ?>
								<a href="<?php echo esc_url( $props['secondary_url'] ); ?>" class="ap-btn ap-btn-outline"><?php echo esc_html( trim( (string) $props['secondary_label'] ) ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( '' !== $cta_image ) : ?>
						<div class="ap-final-cta-art"<?php echo '' === trim( (string) $props['cta_image_alt'] ) ? ' aria-hidden="true"' : ''; ?>>
							<img width="607" height="392" loading="lazy" decoding="async" src="<?php echo esc_url( $cta_image ); ?>" alt="<?php echo esc_attr( $props['cta_image_alt'] ); ?>" />
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
		<section class="ap-footer-links-section"<?php echo $ap_sf_aria( $props['nav_aria'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
			<div class="ap-footer-navs">
				<div class="ap-footer-col ap-footer-brand">
					<?php if ( '' !== $logo ) : ?>
						<a href="<?php echo esc_url( $props['home_url'] ); ?>" class="ap-footer-logo"<?php echo $ap_sf_aria( $props['logo_aria'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
							<img width="520" height="200" loading="lazy" decoding="async" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $props['logo_alt'] ); ?>" />
						</a>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['address'] ) ) : ?>
						<address><?php echo ap_ui_lines( $props['address'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></address>
					<?php endif; ?>
					<?php if ( $socials ) : ?>
						<div class="ap-socials"<?php echo $ap_sf_aria( $props['socials_aria'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
							<?php foreach ( $socials as $social ) : ?>
								<a href="<?php echo esc_url( $social['url'] ); ?>"<?php echo $ap_sf_aria( $social['label'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>><img loading="lazy" decoding="async" src="<?php echo esc_url( $ap_sf_src( $social['icon'] ) ); ?>" alt="" /></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $apps ) : ?>
						<div class="ap-footer-brand-apps">
							<?php
							foreach ( $apps as $app ) :
								$w = isset( $app['w'] ) && is_numeric( $app['w'] ) ? (int) $app['w'] : 0;
								$h = isset( $app['h'] ) && is_numeric( $app['h'] ) ? (int) $app['h'] : 0;
								?>
								<a href="<?php echo esc_url( $app['url'] ); ?>" class="ap-store-badge"<?php echo $ap_sf_aria( $app['label'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>><img<?php echo $w && $h ? ' width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '"' : ''; ?> loading="lazy" decoding="async" src="<?php echo esc_url( $ap_sf_src( $app['image'] ) ); ?>" alt="<?php echo esc_attr( $app['alt'] ?? '' ); ?>" /></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php foreach ( $columns as $col ) : ?>
					<nav class="ap-footer-col"<?php echo $ap_sf_aria( $col['heading'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>>
						<?php if ( '' !== $col['heading'] ) : ?>
							<h3><?php echo esc_html( $col['heading'] ); ?></h3>
						<?php endif; ?>
						<?php foreach ( $col['links'] as $link ) : ?>
							<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( trim( (string) $link['label'] ) ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endforeach; ?>
			</div>
			<?php if ( '' !== trim( (string) $props['legal'] ) ) : ?>
				<div class="ap-footer-copy">
					<p><?php echo esc_html( trim( (string) $props['legal'] ) ); ?></p>
				</div>
			<?php endif; ?>
		</section>
	</div>
</div>
