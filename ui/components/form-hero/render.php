<?php
/**
 * form-hero — a form page's hero (or an in-page form band).
 *
 * Copy on the left (eyebrow, heading, paragraph, check list, badges), a white
 * card on the right holding the HubSpot form; stacked below 992px. The form
 * belongs to HubSpot: this renders a mount point with the portal, form and
 * region as data attributes, and behaviour.js loads HubSpot's script and draws
 * the form there (classic v2 embed, or the developer embed for forms built in
 * HubSpot's new editor). Where the visitor goes after submitting is the form's
 * own redirect, set in HubSpot, unless after_submit_url names a page: then
 * behaviour.js sends the visitor there (with a fresh {trial_token}, also
 * written into the form's trial_token field), as the trial sign-up does.
 *
 * The card carries data-ap-form-skin: it is the kit's form skin (HubSpot's own
 * stylesheet is never attached; style.css draws the fields and button), and
 * the launch check looks for every form inside such a wrapper.
 *
 * Live's /get-a-quote-pro/ and /barcode-labels/ show a "Form not loading?"
 * box when no form has appeared after 6 seconds (blocked script, network);
 * it is rendered hidden here and revealed by the same rule.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$variant   = in_array( $props['variant'], array( 'light', 'dark', 'section', 'banner', 'card' ), true ) ? $props['variant'] : 'light';
$title_tag = 'h2' === $props['title_tag'] ? 'h2' : 'h1';
$card_tag  = 'h1' === $title_tag ? 'h2' : 'h3';
$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$eyebrow   = trim( (string) $props['eyebrow'] );
$body      = trim( (string) $props['body'] );
// The banner paints its own glows (style.css), so a backdrop picture is neither drawn nor preloaded there.
$bg        = 'banner' === $variant ? '' : trim( (string) $props['background'] );

$hs_portal = preg_match( '/^\d{3,12}$/', (string) $props['hubspot_portal'] ) ? (string) $props['hubspot_portal'] : '';
$hs_form   = preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $props['hubspot_form'] ) ? strtolower( (string) $props['hubspot_form'] ) : '';
$hs_region = preg_match( '/^[a-z0-9]{2,6}$/', (string) $props['hubspot_region'] ) ? (string) $props['hubspot_region'] : 'na1';
$hs_embed  = 'developer' === $props['form_embed'] ? 'developer' : 'v2';
$hs_submit = substr( trim( (string) $props['form_submit'] ), 0, 60 );
$has_form  = '' !== $hs_portal && '' !== $hs_form;

/*
 * The mount id HubSpot targets, unique across the page. (A `static` counter
 * here restarts on every render — the loader includes this file inside a fresh
 * closure — so two forms on one page both became …-form-1 and only the first
 * was ever drawn; the AWS re:Invent page had an empty second card.)
 */
$mount_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-form-hero-form-' ) : 'ap-form-hero-form-' . substr( md5( uniqid( '', true ) ), 0, 8 );

/*
 * In the builder (the block editor's live preview) the HubSpot form is not drawn: its place shows a
 * placeholder box that names the connected form, or invites adding one. The builder opens a box on click
 * to paste the form's embed code or link (ap-editor.js, data-ap-hs-placeholder).
 */
$in_builder = function_exists( 'ap_ui_editor_preview' ) && ap_ui_editor_preview();

$fb_email = sanitize_email( trim( (string) $props['fallback_email'] ) );
$fb_phone = trim( (string) $props['fallback_phone'] );
$fb_tel   = preg_replace( '/[^0-9+]/', '', $fb_phone );

$form_title = trim( (string) $props['form_title'] );
// The card variant has no copy column: the title heads the card when no form_title is given.
if ( 'card' === $variant && '' === $form_title ) {
	$form_title = $title;
}
$form_intro = trim( (string) $props['form_intro'] );
$form_note  = trim( (string) $props['form_note'] );

/*
 * Escaped text with the author's formatting (the fields are plain text; the builder writes these marks):
 * line breaks, [link text](address), **bold**, //italic//, __underline__ and {hover:name}words{/hover} (a hover
 * effect from the kit's animation sheet: <span class="ap-anim-hover-name">). The text is escaped first, so the
 * fixed <a>, <strong>, <em>, <u>, <span> and <br> are the only markup added; a link address must be https/http, a
 * /path on this site, #anchor, mailto: or tel:, and goes through esc_url(). Links come first, so formatting can sit
 * inside a link's text.
 */
$ap_rich = static function ( $text ) {
	$html  = ap_ui_lines( $text );
	$steps = array(
		array( '/\[([^\[\]<>]+)\]\(\s*((?:https?:\/\/|mailto:|tel:|\/(?![\/\\\\])|#)[^\s()<>]*)\s*\)/i', 'link' ),
		array( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>' ),
		// Italic //…//: never the // of an address (after ":" or another "/").
		array( '#(?<![:/\w])//(?=\S)(.+?)(?<=\S)//(?![/\w])#s', '<em>$1</em>' ),
		array( '/(?<!\w)__(?=\S)(.+?)(?<=\S)__(?!\w)/s', '<u>$1</u>' ),
		array( '/\{hover:(lift|grow|glow|brighten|tilt|underline|colour|highlight|gradient)\}(.+?)\{\/hover\}/s', '<span class="ap-anim-hover-$1">$2</span>' ),
	);
	foreach ( $steps as $step ) {
		$next = 'link' === $step[1]
			? preg_replace_callback(
				$step[0],
				static function ( $m ) {
					$url = esc_url( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ), array( 'http', 'https', 'mailto', 'tel' ) );
					return '' !== $url ? '<a href="' . $url . '">' . $m[1] . '</a>' : $m[0];
				},
				$html
			)
			: preg_replace( $step[0], $step[1], $html );
		$html = is_string( $next ) ? $next : $html;
	}
	return $html;
};

/*
 * Where the visitor goes once the form is sent (behaviour.js), when the page says so instead of HubSpot. Only
 * https/http or a /path; {trial_token} is kept for the script (esc_url_raw() would strip the braces).
 */
$hs_after = '';
$after_in = trim( (string) $props['after_submit_url'] );
if ( '' !== $after_in && preg_match( '#^(https?://[^\s/]+|/(?![/\\\\]))#i', $after_in ) ) {
	$marker   = 'ap0trial0token0';
	$after_ok = esc_url_raw( str_replace( '{trial_token}', $marker, $after_in ), array( 'http', 'https' ) );
	if ( '' !== $after_ok && preg_match( '#^(https?://[^\s/]+|/(?![/\\\\]))#i', $after_ok ) ) {
		$hs_after = str_replace( $marker, '{trial_token}', $after_ok );
	}
}

$bullets = array();
foreach ( is_array( $props['bullets'] ) ? $props['bullets'] : array() as $bullet ) {
	if ( is_string( $bullet ) && '' !== trim( $bullet ) ) {
		$bullets[] = trim( $bullet );
	}
}

$badges = array();
foreach ( is_array( $props['badges'] ) ? $props['badges'] : array() as $badge ) {
	if ( is_array( $badge ) && '' !== trim( (string) ( $badge['image'] ?? '' ) ) ) {
		$badges[] = $badge;
	}
}

$classes = 'ap-form-hero ap-form-hero--' . $variant;
$style   = '' !== $bg ? '--ap-form-hero-bg:url("' . esc_url_raw( $bg ) . '")' : '';
// First-screen backdrop (a CSS background): preload it (PageSpeed LCP).
if ( '' !== $bg && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $bg ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() in the loader.
}
// The form card, buffered once: the section variants draw it beside their copy, the card variant draws it alone.
ob_start();
?>
		<?php if ( $has_form || '' !== $form_title || $in_builder ) : ?>
			<div class="ap-form-hero__card" data-ap-form-skin>
				<?php if ( '' !== $form_title ) : ?>
					<<?php echo tag_escape( $card_tag ); ?> class="ap-form-hero__card-title"><?php echo esc_html( $form_title ); ?></<?php echo tag_escape( $card_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $form_intro ) : ?>
					<p class="ap-form-hero__card-intro"><?php echo $ap_rich( $form_intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
				<?php endif; ?>
				<?php if ( $in_builder ) : ?>
					<div class="ap-form-hero__placeholder<?php echo $has_form ? ' is-set' : ''; ?>" data-ap-hs-placeholder data-ap-noedit role="button" tabindex="0">
						<span class="ap-form-hero__placeholder-icon" aria-hidden="true"></span>
						<?php if ( $has_form ) : ?>
							<strong>HubSpot form</strong>
							<span><?php echo esc_html( substr( $hs_form, 0, 8 ) . '… · portal ' . $hs_portal . ' · ' . $hs_region . ( 'developer' === $hs_embed ? ' · new editor' : '' ) ); ?></span>
							<span>The form shows on the published page. Click to change it.</span>
						<?php else : ?>
							<strong>Add a HubSpot form</strong>
							<span>Click and paste the form's embed code or link from HubSpot.</span>
						<?php endif; ?>
					</div>
				<?php elseif ( $has_form ) : ?>
					<div
						id="<?php echo esc_attr( $mount_id ); ?>"
						class="ap-form-hero__form ap-form-hero__form--<?php echo esc_attr( $hs_embed ); ?>"
						data-ap-form-hero
						data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>"
						data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>"
						data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"
						data-ap-hs-embed="<?php echo esc_attr( $hs_embed ); ?>"
						<?php if ( '' !== $hs_submit ) : ?>data-ap-hs-submit="<?php echo esc_attr( $hs_submit ); ?>"<?php endif; ?>
						<?php if ( '' !== $hs_after ) : ?>data-ap-hs-after="<?php echo esc_attr( $hs_after ); ?>"<?php endif; ?>
					>
						<?php if ( 'developer' === $hs_embed ) : ?>
							<div class="hs-form-html" data-region="<?php echo esc_attr( $hs_region ); ?>" data-form-id="<?php echo esc_attr( $hs_form ); ?>" data-portal-id="<?php echo esc_attr( $hs_portal ); ?>"></div>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $fb_email || '' !== $fb_phone ) : ?>
						<div class="ap-form-hero__fallback" data-ap-form-fallback hidden>
							<strong>Form not loading?</strong>
							<?php if ( '' !== $fb_email ) : ?>
								Email <a href="<?php echo esc_url( 'mailto:' . $fb_email ); ?>"><?php echo esc_html( $fb_email ); ?></a><?php endif; ?><?php if ( '' !== $fb_phone && '' !== $fb_tel ) : ?><span class="ap-form-hero__fb-phone"> <?php echo '' !== $fb_email ? 'or call' : 'Call'; ?> <a href="<?php echo esc_url( 'tel:' . $fb_tel ); ?>"><?php echo esc_html( $fb_phone ); ?></a></span><?php endif; ?>
							and we will get right back to you.
							<a href="#" data-ap-form-reload>Reload the form</a>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( '' !== $form_note ) : ?>
					<p class="ap-form-hero__note"><?php echo $ap_rich( $form_note ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
<?php
$ap_card_html = (string) ob_get_clean();

// card (QA 2026-10-30): just the card, for a host component that places it (the solution hero's form, /barcode-labels/).
if ( 'card' === $variant ) {
	echo '<div class="ap-form-hero ap-form-hero--card"' . ( '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '' ) . '>' . $ap_card_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts.
	return;
}
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?><?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<div class="ap-form-hero__inner">
		<div class="ap-form-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-form-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<<?php echo tag_escape( $title_tag ); ?> class="ap-form-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo tag_escape( $title_tag ); ?>>
			<?php if ( '' !== $body ) : ?>
				<p class="ap-form-hero__body"><?php echo $ap_rich( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
			<?php endif; ?>
			<?php if ( $bullets ) : ?>
				<ul class="ap-form-hero__list">
					<?php foreach ( $bullets as $bullet ) : ?>
						<li><?php echo $ap_rich( $bullet ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $badges ) : ?>
				<div class="ap-form-hero__badges">
					<?php foreach ( $badges as $badge ) : ?>
						<?php
						$w = isset( $badge['w'] ) ? (int) $badge['w'] : 0;
						$h = isset( $badge['h'] ) ? (int) $badge['h'] : 0;
						?>
						<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( (string) ( $badge['alt'] ?? '' ) ); ?>"<?php echo $w > 0 && $h > 0 ? ' width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '"' : ''; ?> decoding="async" />
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php echo $ap_card_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built above from escaped values. ?>
	</div>
</section>
