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
	return '' !== $icon ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $icon ) . '" alt="" decoding="async" />' : '';
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
// Optional Ursa chat scene (Ursa AI frame 7261:107107, nodes 7305:119583-119951): the assistant's
// reply card with a question bubble, three glass stat cards and soft glows. Decorative; only the
// strings come from props, the icons and art are the kit's (assets/chat-*). Absent → output unchanged.
$ap_chat    = null;
$ap_chat_in = is_array( $props['chat'] ?? null ) ? $props['chat'] : array();
$ap_chat_q  = trim( (string) ( $ap_chat_in['question'] ?? '' ) );
$ap_chat_a  = trim( (string) ( $ap_chat_in['answer'] ?? '' ) );
if ( '' !== $ap_chat_q || '' !== $ap_chat_a ) {
	$ap_icons = array( 'search', 'analytics', 'package', 'wrench', 'sparkles', 'shield' );
	$ap_icon_of = static function ( $raw, $fallback ) use ( $ap_icons ) {
		$raw = trim( (string) $raw );
		return in_array( $raw, $ap_icons, true ) ? $raw : $fallback;
	};
	$ap_chat = array(
		'question'    => $ap_chat_q,
		'answer'      => $ap_chat_a,
		'placeholder' => trim( (string) ( $ap_chat_in['placeholder'] ?? '' ) ),
		'tiles'       => array(),
		'actions'     => array(),
		'cards'       => array(),
	);
	foreach ( is_array( $ap_chat_in['tiles'] ?? null ) ? $ap_chat_in['tiles'] : array() as $i => $tile ) {
		if ( is_array( $tile ) && '' !== trim( (string) ( $tile['title'] ?? '' ) ) && count( $ap_chat['tiles'] ) < 6 ) {
			$ap_chat['tiles'][] = array(
				'title' => trim( (string) $tile['title'] ),
				'note'  => trim( (string) ( $tile['note'] ?? '' ) ),
				'icon'  => $ap_icon_of( $tile['icon'] ?? '', $ap_icons[ $i % 6 ] ),
			);
		}
	}
	foreach ( is_array( $ap_chat_in['actions'] ?? null ) ? $ap_chat_in['actions'] : array() as $action ) {
		$label = trim( (string) ( is_array( $action ) ? ( $action['label'] ?? '' ) : $action ) );
		if ( '' !== $label && count( $ap_chat['actions'] ) < 3 ) {
			$ap_chat['actions'][] = $label;
		}
	}
	// Cards sit in three fixed slots: in front left, in front right, blurred behind.
	$ap_slots = array( 'left', 'right', 'back' );
	foreach ( is_array( $ap_chat_in['cards'] ?? null ) ? $ap_chat_in['cards'] : array() as $card ) {
		if ( ! is_array( $card ) || '' === trim( (string) ( $card['label'] ?? '' ) ) || count( $ap_chat['cards'] ) >= 3 ) {
			continue;
		}
		$slot = trim( (string) ( $card['slot'] ?? '' ) );
		$slot = in_array( $slot, $ap_slots, true ) ? $slot : $ap_slots[ count( $ap_chat['cards'] ) ];
		$ap_chat['cards'][ $slot ] = array(
			'label' => trim( (string) $card['label'] ),
			'value' => trim( (string) ( $card['value'] ?? '' ) ),
			'note'  => trim( (string) ( $card['note'] ?? '' ) ),
			'icon'  => $ap_icon_of( $card['icon'] ?? '', 'search' ),
		);
	}
}
$ap_chat_card = static function ( $card, $slot ) {
	$file = in_array( $card['icon'], array( 'search', 'shield', 'wrench' ), true ) ? 'chat-c-' . $card['icon'] . '.svg' : 'chat-i-' . $card['icon'] . '.svg';
	return '<div class="ap-orbit-hero__glass ap-orbit-hero__glass--' . esc_attr( $slot ) . '"><span class="ap-orbit-hero__glass-ico"><img src="' . esc_url( ap_ui_asset_url( 'orbit-hero', $file ) ) . '" alt="" width="26" height="26" decoding="async" /></span>'
		. '<span class="ap-orbit-hero__glass-label">' . esc_html( $card['label'] ) . '</span>'
		. ( '' !== $card['value'] ? '<span class="ap-orbit-hero__glass-value">' . esc_html( $card['value'] ) . '</span>' : '' )
		. ( '' !== $card['note'] ? '<span class="ap-orbit-hero__glass-note">' . esc_html( $card['note'] ) . '</span>' : '' )
		. '</div>';
};

// No mascot, orb or badges (Ursa AI frame 7261:107107): no stage at all, so tablets and phones get no empty block.
$ap_bare = '' === trim( (string) $props['panda'] ) && '' === trim( (string) $props['orb'] ) && ! $badges && ! $ap_chat;
?>
<section class="ap-orbit-hero<?php echo $ap_bare ? ' ap-orbit-hero--bare' : ''; ?><?php echo $ap_chat ? ' ap-orbit-hero--chat' : ''; ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( $props['title'] ) ); ?>">
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

			<?php if ( $ap_chat ) : ?>
			<div class="ap-orbit-hero__chatstage" aria-hidden="true">
				<div class="ap-orbit-hero__scene">
					<img class="ap-orbit-hero__glow ap-orbit-hero__glow--red" src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-glow-red.svg' ) ); ?>" alt="" width="310" height="286" decoding="async" />
					<img class="ap-orbit-hero__glow ap-orbit-hero__glow--indigo" src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-glow-indigo.svg' ) ); ?>" alt="" width="322" height="309" decoding="async" />
					<span class="ap-orbit-hero__spark"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-sparkles.svg' ) ); ?>" alt="" width="28" height="28" decoding="async" /></span>
					<?php echo isset( $ap_chat['cards']['back'] ) ? $ap_chat_card( $ap_chat['cards']['back'], 'back' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
					<div class="ap-orbit-hero__reply">
						<div class="ap-orbit-hero__reply-head">
							<span class="ap-orbit-hero__reply-orb"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-orb.svg' ) ); ?>" alt="" width="84" height="83" decoding="async" /></span>
							<span class="ap-orbit-hero__reply-text"><?php echo esc_html( $ap_chat['answer'] ); ?></span>
						</div>
						<div class="ap-orbit-hero__chart"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-bars.svg' ) ); ?>" alt="" width="251" height="70" decoding="async" /></div>
						<?php if ( $ap_chat['tiles'] ) : ?>
							<div class="ap-orbit-hero__tiles">
								<?php foreach ( $ap_chat['tiles'] as $tile ) : ?>
									<div class="ap-orbit-hero__tile"><span class="ap-orbit-hero__tile-ico"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-i-' . $tile['icon'] . '.svg' ) ); ?>" alt="" width="17" height="17" decoding="async" /></span><span class="ap-orbit-hero__tile-text"><span class="ap-orbit-hero__tile-title"><?php echo esc_html( $tile['title'] ); ?></span><?php if ( '' !== $tile['note'] ) : ?><span class="ap-orbit-hero__tile-note"><?php echo esc_html( $tile['note'] ); ?></span><?php endif; ?></span></div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $ap_chat['actions'] ) : ?>
							<div class="ap-orbit-hero__actions">
								<?php foreach ( $ap_chat['actions'] as $i => $action ) : ?>
									<span class="ap-orbit-hero__action<?php echo 0 === $i ? ' ap-orbit-hero__action--primary' : ''; ?>"><?php echo esc_html( $action ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="ap-orbit-hero__ask">
							<img class="ap-orbit-hero__clip" src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-clip.svg' ) ); ?>" alt="" width="22" height="22" decoding="async" />
							<span class="ap-orbit-hero__field"><?php echo esc_html( $ap_chat['placeholder'] ); ?></span>
							<span class="ap-orbit-hero__send"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-send.svg' ) ); ?>" alt="" width="22" height="22" decoding="async" /></span>
						</div>
					</div>
					<?php echo isset( $ap_chat['cards']['right'] ) ? $ap_chat_card( $ap_chat['cards']['right'], 'right' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
					<?php echo isset( $ap_chat['cards']['left'] ) ? $ap_chat_card( $ap_chat['cards']['left'], 'left' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
					<?php if ( '' !== $ap_chat['question'] ) : ?>
						<div class="ap-orbit-hero__bubble"><span class="ap-orbit-hero__q"><?php echo esc_html( $ap_chat['question'] ); ?></span><span class="ap-orbit-hero__avatar"><img src="<?php echo esc_url( ap_ui_asset_url( 'orbit-hero', 'chat-avatar.webp' ) ); ?>" alt="" width="96" height="96" decoding="async" /></span></div>
					<?php endif; ?>
				</div>
			</div>
			<?php elseif ( ! $ap_bare ) : ?>
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
