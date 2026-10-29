<?php
/**
 * assist-hero — the help desk hero.
 *
 * Matches the live .epx-hero on /solutions/help-desk-ticketing/: the copy column
 * beside a fixed-size stage holding the photo, an employee request card, the
 * assistant's reply with its action buttons, a follow-up card, three floating
 * icon tiles and a chip strip. The stage is decorative — it illustrates the
 * product and repeats nothing the copy needs to say.
 *
 * The inner class names are the live ones, so this stays diffable against the
 * ported stylesheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item, $key = 'icon_svg', $class = '' ) {
	$svg = ap_ui_svg( $item[ $key ] ?? '' );
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
		$html    = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $html );
	}
	return $html;
};

$arr = static function ( $value ) {
	return is_array( $value ) ? $value : array();
};

$title     = trim( (string) $props['title'] );
$message   = $arr( $props['message'] );
$assistant = $arr( $props['assistant'] );
$response  = $arr( $props['response'] );
$badges    = array_values( array_filter( $arr( $props['badges'] ), static function ( $b ) { return is_array( $b ) && ! empty( $b['image'] ); } ) );
$feats     = array_values( array_filter( $arr( $props['features'] ), static function ( $f ) { return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) ); } ) );
$tiles     = array_values( array_filter( $arr( $props['tiles'] ), static function ( $t ) { return is_array( $t ) && '' !== ap_ui_svg( $t['icon_svg'] ?? '' ); } ) );
$tones     = array( 'ticket', 'flag', 'history' );
$actions   = array_values( array_filter( $arr( $assistant['actions'] ?? null ), static function ( $a ) { return is_array( $a ) && '' !== trim( (string) ( $a['label'] ?? '' ) ); } ) );
$bg        = trim( (string) $props['background'] );
?>
<section class="ap-assist-hero"<?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-assist-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
	<div class="ap-assist-hero__orbs" aria-hidden="true"></div>
	<div class="ap-assist-hero__stage">
		<div class="ap-assist-hero__vwrap" aria-hidden="true">
			<div class="ap-assist-hero__visual">
				<?php if ( $message ) : ?>
					<div class="epx-hv epx-hv--msg">
						<div class="epx-hv__rev">
							<div class="epx-hv__flo epx-hmsg">
								<div class="epx-hmsg__head">
									<?php if ( ! empty( $message['avatar'] ) ) : ?>
										<img class="epx-hmsg__avatar" src="<?php echo esc_url( $message['avatar'] ); ?>" alt="" width="28" height="28" decoding="async" />
									<?php endif; ?>
									<span class="epx-hmsg__meta">
										<span class="epx-hmsg__name"><?php echo esc_html( $message['name'] ?? '' ); ?></span>
										<span class="epx-hmsg__time"><?php echo esc_html( $message['time'] ?? '' ); ?></span>
									</span>
								</div>
								<?php if ( ! empty( $message['title'] ) ) : ?>
									<p class="epx-hmsg__title"><?php echo esc_html( $message['title'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $message['shot'] ) ) : ?>
									<img class="epx-hmsg__shot" src="<?php echo esc_url( $message['shot'] ); ?>" alt="" width="223" height="131" decoding="async" />
								<?php endif; ?>
								<div class="epx-hmsg__foot">
									<span class="epx-hmsg__dept"><?php echo $ap_icon( $message, 'dept_icon_svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?><?php echo esc_html( $message['dept'] ?? '' ); ?></span>
									<span class="epx-hmsg__cat"><?php echo esc_html( $message['category'] ?? '' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $assistant ) : ?>
					<div class="epx-hv epx-hv--ursa">
						<div class="epx-hv__rev">
							<div class="epx-hv__flo epx-hursa">
								<div class="epx-hursa__head">
									<span class="epx-hursa__slot" aria-hidden="true">
										<?php if ( ! empty( $assistant['orb'] ) ) : ?>
											<img class="ap-assist-hero__orb" src="<?php echo esc_url( $assistant['orb'] ); ?>" alt="" width="80" height="80" decoding="async" />
										<?php endif; ?>
									</span>
									<span class="epx-hursa__who">
										<span class="epx-hursa__brand"><?php echo ap_ui_brandmarks( esc_html( $assistant['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
										<span class="epx-hursa__when"><?php echo esc_html( $assistant['when'] ?? '' ); ?></span>
									</span>
								</div>
								<?php if ( ! empty( $assistant['body'] ) ) : ?>
									<p class="epx-hursa__body"><?php echo ap_ui_brandmarks( esc_html( $assistant['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
								<?php endif; ?>
								<?php if ( $actions ) : ?>
									<div class="epx-hursa__actions">
										<?php foreach ( $actions as $i => $action ) : ?>
											<span class="epx-hursa__btn epx-hursa__btn--<?php echo 0 === $i ? 'primary' : 'secondary'; ?>"><?php echo esc_html( $action['label'] ); ?><?php echo $ap_icon( $action ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $props['photo'] ) ) : ?>
					<img class="ap-assist-hero__photo" src="<?php echo esc_url( $props['photo'] ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" width="486" height="729" decoding="async" fetchpriority="high" />
				<?php endif; ?>

				<?php foreach ( array_slice( $tiles, 0, 3 ) as $i => $tile ) : ?>
					<?php $tone = in_array( $tile['tone'] ?? '', $tones, true ) ? $tile['tone'] : $tones[ $i ]; ?>
					<span class="epx-hv epx-hv--tile epx-htile epx-htile--<?php echo esc_attr( $tone ); ?>">
						<span class="epx-hv__rev"><span class="epx-hv__flo"><?php echo ap_ui_svg( $tile['icon_svg'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span></span>
					</span>
				<?php endforeach; ?>

				<?php if ( $response ) : ?>
					<div class="epx-hv epx-hv--resp">
						<div class="epx-hv__rev">
							<div class="epx-hv__flo epx-hresp">
								<div class="epx-hresp__row">
									<span class="epx-hresp__ico"><?php echo $ap_icon( $response ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span>
									<span class="epx-hresp__text">
										<span class="epx-hresp__ttl"><?php echo esc_html( $response['title'] ?? '' ); ?></span>
										<span class="epx-hresp__body"><?php echo esc_html( $response['body'] ?? '' ); ?></span>
									</span>
								</div>
								<?php if ( ! empty( $response['link_label'] ) ) : ?>
									<span class="epx-hresp__btn"><?php echo esc_html( $response['link_label'] ); ?><?php echo $ap_icon( $response, 'link_icon_svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="ap-assist-hero__content">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-assist-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="ap-assist-hero__h1"><?php echo $ap_accent( $title, $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-assist-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<div class="ap-assist-hero__cta">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="epx-btn epx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $badges ) : ?>
					<span class="ap-assist-hero__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" />
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $feats ) : ?>
			<div class="ap-assist-hero__ticker">
				<div class="ap-assist-hero__strip">
					<?php foreach ( array( false, true ) as $dup ) : ?>
						<?php foreach ( $feats as $feat ) : ?>
							<span class="ap-assist-hero__pill<?php echo $dup ? ' ap-assist-hero__pill--dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_icon( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?><?php echo esc_html( $feat['label'] ); ?></span>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<div class="ap-assist-hero__veil" aria-hidden="true"></div>
</section>
