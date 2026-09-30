<?php
/**
 * site-header — the global header: the desktop bar with tab-rail mega menus,
 * the phone header with its drawer, and the phone app banner.
 *
 * Matches the live 2026 header (#ap-presentation-header-v5,
 * #ap-mobile-header-v1, #ap-app-banner-v1) and adds the Figma Global Megamenu
 * options it lacks (4374:23298), such as the case-study cards on Who We Help.
 *
 * One nav prop drives the desktop panels and the phone drawer, so the two
 * cannot drift. A tab with a url renders as a link (a hub page); the others
 * are buttons that only switch the panel. On phones a tab whose groups all
 * have headings splits into one section per group, as on live.
 *
 * The logo, icons and card images are file names in this component's assets/
 * folder or full URLs.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A file name resolves to this component's assets/; a URL or path passes through.
$ap_sh_src = static function ( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|/)#i', $value ) ) {
		return $value;
	}
	return ap_ui_asset_url( 'site-header', $value );
};

// Inline custom property carrying an icon URL for the CSS hover mask.
$ap_sh_icon_var = static function ( $name, $url ) {
	return $name . ":url('" . esc_url_raw( $url ) . "')";
};

$ap_sh_tabs = static function ( $item ) {
	$tabs = isset( $item['tabs'] ) && is_array( $item['tabs'] ) ? $item['tabs'] : array();
	return array_values(
		array_filter(
			$tabs,
			static function ( $tab ) {
				return is_array( $tab ) && '' !== trim( (string) ( $tab['label'] ?? '' ) );
			}
		)
	);
};

// A tab's groups, each reduced to the links that have a title and a url.
$ap_sh_groups = static function ( $tab ) {
	$out    = array();
	$groups = isset( $tab['groups'] ) && is_array( $tab['groups'] ) ? $tab['groups'] : array();
	foreach ( $groups as $group ) {
		if ( ! is_array( $group ) || empty( $group['links'] ) || ! is_array( $group['links'] ) ) {
			continue;
		}
		$links = array_values(
			array_filter(
				$group['links'],
				static function ( $link ) {
					return is_array( $link )
						&& '' !== trim( (string) ( $link['title'] ?? '' ) )
						&& '' !== trim( (string) ( $link['url'] ?? '' ) );
				}
			)
		);
		if ( $links ) {
			$out[] = array(
				'heading' => trim( (string) ( $group['heading'] ?? '' ) ),
				'links'   => $links,
			);
		}
	}
	return $out;
};

// Phone sections for a tab: one per group when every group is headed, else one for the tab.
$ap_sh_sections = static function ( $tab ) use ( $ap_sh_groups ) {
	$groups = $ap_sh_groups( $tab );
	if ( ! $groups ) {
		return array();
	}
	if ( count( array_filter( array_column( $groups, 'heading' ) ) ) === count( $groups ) ) {
		return array_map(
			static function ( $group ) {
				return array(
					'label' => $group['heading'],
					'url'   => '',
					'links' => $group['links'],
				);
			},
			$groups
		);
	}
	return array(
		array(
			'label' => trim( (string) $tab['label'] ),
			'url'   => trim( (string) ( $tab['url'] ?? '' ) ),
			'links' => array_merge( ...array_column( $groups, 'links' ) ),
		),
	);
};

$ap_sh_key = static function ( $tab ) {
	$key = trim( (string) ( $tab['key'] ?? '' ) );
	return sanitize_title( '' !== $key ? $key : (string) $tab['label'] );
};

$ap_sh_link = static function ( $link ) use ( $ap_sh_src, $ap_sh_icon_var ) {
	$icon = $ap_sh_src( $link['icon'] ?? '' );
	$desc = trim( (string) ( $link['desc'] ?? '' ) );
	?>
	<a class="ap-mega-link" href="<?php echo esc_url( $link['url'] ); ?>">
		<span class="ap-icon-box" aria-hidden="true"<?php echo '' !== $icon ? ' style="' . esc_attr( $ap_sh_icon_var( '--ap-icon-url', $icon ) ) . '"' : ''; ?>>
			<?php if ( '' !== $icon ) : ?>
				<img class="ap-icon-img" src="<?php echo esc_url( $icon ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" />
			<?php endif; ?>
		</span>
		<span class="ap-link-copy">
			<strong><?php echo ap_ui_brandmarks( esc_html( $link['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></strong>
			<?php if ( '' !== $desc ) : ?>
				<small><?php echo esc_html( $desc ); ?></small>
			<?php endif; ?>
		</span>
	</a>
	<?php
};

$ap_sh_group_block = static function ( $groups ) use ( $ap_sh_link ) {
	$grid = count( $groups ) > 1;
	if ( $grid ) {
		echo '<div class="ap-core-grid">';
	}
	foreach ( $groups as $group ) {
		echo '<div class="ap-mega-group">';
		if ( '' !== $group['heading'] ) {
			echo '<p class="ap-mega-heading">' . esc_html( $group['heading'] ) . '</p>';
		}
		foreach ( $group['links'] as $link ) {
			$ap_sh_link( $link );
		}
		echo '</div>';
	}
	if ( $grid ) {
		echo '</div>';
	}
};

$ap_sh_card = static function ( $card ) use ( $ap_sh_src ) {
	$image = $ap_sh_src( $card['image'] ?? '' );
	$logo  = $ap_sh_src( $card['logo'] ?? '' );
	$url   = trim( (string) ( $card['url'] ?? '' ) );
	$quote = trim( (string) ( $card['quote'] ?? '' ) );
	$by    = trim( (string) ( $card['author'] ?? '' ) );
	$role  = trim( (string) ( $card['role'] ?? '' ) );

	echo '' !== $url ? '<a class="ap-mega-card" href="' . esc_url( $url ) . '">' : '<div class="ap-mega-card">';
	?>
		<?php if ( '' !== $image ) : ?>
			<span class="ap-mega-card__media">
				<img class="ap-mega-card__img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $card['image_alt'] ?? '' ); ?>" loading="lazy" decoding="async" />
				<?php if ( '' !== $logo ) : ?>
					<span class="ap-mega-card__logo"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $card['logo_alt'] ?? '' ); ?>" loading="lazy" decoding="async" /></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
		<span class="ap-mega-card__body">
			<span class="ap-mega-card__mark" aria-hidden="true"><img src="<?php echo esc_url( ap_ui_asset_url( 'site-header', 'quote.svg' ) ); ?>" alt="" /></span>
			<span class="ap-mega-card__text">
				<span class="ap-mega-card__copy">
					<span class="ap-mega-card__title"><?php echo esc_html( $card['title'] ); ?></span>
					<?php if ( '' !== $quote ) : ?>
						<span class="ap-mega-card__quote"><?php echo esc_html( $quote ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( '' !== $by || '' !== $role ) : ?>
					<span class="ap-mega-card__by">
						<?php if ( '' !== $by ) : ?>
							<span class="ap-mega-card__author"><?php echo esc_html( $by ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $role ) : ?>
							<span class="ap-mega-card__role"><?php echo esc_html( $role ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</span>
		</span>
	<?php
	echo '' !== $url ? '</a>' : '</div>';
};

$ap_sh_mobile_item = static function ( $link ) use ( $ap_sh_src, $ap_sh_icon_var ) {
	$icon = $ap_sh_src( $link['icon'] ?? '' );
	?>
	<a class="ap-mobile-item" href="<?php echo esc_url( $link['url'] ); ?>">
		<span class="ap-mobile-link-icon" aria-hidden="true"<?php echo '' !== $icon ? ' style="' . esc_attr( $ap_sh_icon_var( '--ap-mobile-icon-url', $icon ) ) . '"' : ''; ?>>
			<?php if ( '' !== $icon ) : ?>
				<img class="ap-mobile-icon-img" src="<?php echo esc_url( $icon ); ?>" alt="" width="22" height="22" loading="lazy" decoding="async" />
			<?php endif; ?>
		</span>
		<span class="ap-mobile-link-copy"><strong><?php echo ap_ui_brandmarks( esc_html( $link['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></strong></span>
	</a>
	<?php
};

$nav = array_values(
	array_filter(
		is_array( $props['nav'] ) ? $props['nav'] : array(),
		static function ( $item ) {
			return is_array( $item ) && '' !== trim( (string) ( $item['label'] ?? '' ) );
		}
	)
);

$login_links = array_values(
	array_filter(
		is_array( $props['login_links'] ) ? $props['login_links'] : array(),
		static function ( $entry ) {
			return is_array( $entry ) && ! empty( $entry['label'] ) && ! empty( $entry['url'] );
		}
	)
);

$logo     = $ap_sh_src( $props['logo'] );
$logo_alt = (string) $props['logo_alt'];
$home_url = '' !== trim( (string) $props['home_url'] ) ? $props['home_url'] : '/';
$tel      = preg_replace( '/[^0-9+]/', '', (string) $props['phone_tel'] );
$phone    = trim( (string) $props['phone_label'] );
$trial    = '' !== trim( (string) $props['trial_url'] ) && '' !== trim( (string) $props['trial_label'] );
$demo     = '' !== trim( (string) $props['demo_url'] ) && '' !== trim( (string) $props['demo_label'] );
$app_icon = $ap_sh_src( $props['app_icon'] );
$menu_id  = 'ap-site-header-menu';
?>
<div class="ap-site-header" data-ap-site-header>
	<?php if ( $props['app_banner'] && '' !== trim( (string) $props['app_ios_url'] ) ) : ?>
		<div class="ap-site-header__banner">
			<div class="ap-app-banner-left">
				<?php if ( '' !== $app_icon ) : ?>
					<span class="ap-app-icon" aria-hidden="true"><img src="<?php echo esc_url( $app_icon ); ?>" alt="" /></span>
				<?php endif; ?>
				<span class="ap-app-text"><?php echo esc_html( $props['app_text'] ); ?></span>
			</div>
			<a class="ap-app-get" href="<?php echo esc_url( $props['app_ios_url'] ); ?>"<?php echo '' !== trim( (string) $props['app_android_url'] ) ? ' data-ap-sh-android="' . esc_url( $props['app_android_url'] ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $props['app_text'] ); ?>">Get</a>
			<button class="ap-app-close" type="button" aria-label="Close app banner">&times;</button>
		</div>
	<?php endif; ?>

	<div class="ap-site-header__desk">
		<div class="ap-head-inner">
			<a class="ap-head-logo" href="<?php echo esc_url( $home_url ); ?>">
				<?php if ( '' !== $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" width="520" height="200" decoding="async" />
				<?php endif; ?>
			</a>

			<nav class="ap-head-nav" aria-label="Main navigation">
				<?php foreach ( $nav as $item ) : ?>
					<?php
					$tabs  = $ap_sh_tabs( $item );
					$label = trim( (string) $item['label'] );
					$url   = trim( (string) ( $item['url'] ?? '' ) );
					?>
					<?php if ( $tabs ) : ?>
						<div class="ap-nav-item ap-has-mega">
							<?php if ( '' !== $url ) : ?>
								<a class="ap-nav-top" href="<?php echo esc_url( $url ); ?>" aria-haspopup="true" aria-expanded="false"><?php echo esc_html( $label ); ?> <span class="ap-caret" aria-hidden="true"></span></a>
							<?php else : ?>
								<button class="ap-nav-top" type="button" aria-haspopup="true" aria-expanded="false"><?php echo esc_html( $label ); ?> <span class="ap-caret" aria-hidden="true"></span></button>
							<?php endif; ?>
							<div class="ap-mega-panel ap-mega-<?php echo esc_attr( sanitize_title( $label ) ); ?>">
								<div class="ap-mega-shell ap-help-grid">
									<div class="ap-help-tabs">
										<?php foreach ( $tabs as $t => $tab ) : ?>
											<?php
											$key     = $ap_sh_key( $tab );
											$tab_url = trim( (string) ( $tab['url'] ?? '' ) );
											$classes = 'ap-help-tab' . ( 0 === $t ? ' is-active' : '' );
											?>
											<?php if ( '' !== $tab_url ) : ?>
												<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $tab_url ); ?>" data-ap-help-tab="<?php echo esc_attr( $key ); ?>"><span class="ap-help-tab__label"><?php echo esc_html( $tab['label'] ); ?></span> <span aria-hidden="true">&rsaquo;</span></a>
											<?php else : ?>
												<button class="<?php echo esc_attr( $classes ); ?>" type="button" data-ap-help-tab="<?php echo esc_attr( $key ); ?>"><span class="ap-help-tab__label"><?php echo esc_html( $tab['label'] ); ?></span> <span aria-hidden="true">&rsaquo;</span></button>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
									<div class="ap-help-content">
										<?php foreach ( $tabs as $t => $tab ) : ?>
											<?php
											$groups  = $ap_sh_groups( $tab );
											$heading = trim( (string) ( $tab['heading'] ?? '' ) );
											$card    = isset( $tab['card'] ) && is_array( $tab['card'] ) && ! empty( $tab['card']['title'] ) ? $tab['card'] : null;
											?>
											<div class="ap-help-panel<?php echo 0 === $t ? ' is-active' : ''; ?>" data-ap-help-panel="<?php echo esc_attr( $ap_sh_key( $tab ) ); ?>">
												<?php if ( '' !== $heading ) : ?>
													<p class="ap-mega-heading"><?php echo esc_html( $heading ); ?></p>
												<?php endif; ?>
												<?php if ( $card ) : ?>
													<div class="ap-help-split">
														<div class="ap-help-links"><?php $ap_sh_group_block( $groups ); ?></div>
														<?php $ap_sh_card( $card ); ?>
													</div>
												<?php else : ?>
													<?php $ap_sh_group_block( $groups ); ?>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
					<?php elseif ( '' !== $url ) : ?>
						<a class="ap-nav-simple" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>

			<div class="ap-head-actions">
				<a class="ap-login-icon" href="<?php echo esc_url( $props['login_url'] ); ?>" aria-label="Log in"<?php echo $login_links ? ' role="button" aria-haspopup="true" aria-expanded="false" data-ap-sh-login' : ''; ?>>
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
				</a>
				<?php if ( $trial ) : ?>
					<a class="ap-header-btn ap-header-free" href="<?php echo esc_url( $props['trial_url'] ); ?>"><?php echo esc_html( $props['trial_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $demo ) : ?>
					<a class="ap-header-btn ap-header-demo" href="<?php echo esc_url( $props['demo_url'] ); ?>"><?php echo esc_html( $props['demo_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $tel && '' !== $phone ) : ?>
					<a class="ap-header-sales" href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
				<?php if ( $login_links ) : ?>
					<div class="ap-login-dd" role="menu">
						<?php foreach ( $login_links as $entry ) : ?>
							<a role="menuitem" href="<?php echo esc_url( $entry['url'] ); ?>"><?php echo esc_html( $entry['label'] ); ?><?php if ( ! empty( $entry['note'] ) ) : ?><small><?php echo esc_html( $entry['note'] ); ?></small><?php endif; ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="ap-site-header__mob">
		<div class="ap-mobile-head-inner">
			<a class="ap-mobile-logo" href="<?php echo esc_url( $home_url ); ?>">
				<?php if ( '' !== $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" width="520" height="200" decoding="async" />
				<?php endif; ?>
			</a>
			<div class="ap-mobile-actions">
				<?php if ( $trial ) : ?>
					<a class="ap-mobile-free" href="<?php echo esc_url( $props['trial_url'] ); ?>"><?php echo esc_html( $props['trial_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $demo ) : ?>
					<a class="ap-mobile-demo" href="<?php echo esc_url( $props['demo_url'] ); ?>"><?php echo esc_html( $props['demo_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $tel ) : ?>
					<a class="ap-mobile-phone" href="<?php echo esc_url( 'tel:' . $tel ); ?>" aria-label="<?php echo esc_attr( '' !== $phone ? 'Call ' . $phone : 'Call sales' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
					</a>
				<?php endif; ?>
				<button class="ap-mobile-burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>"><span></span><span></span><span></span></button>
			</div>
		</div>

		<nav class="ap-mobile-menu" id="<?php echo esc_attr( $menu_id ); ?>" aria-label="Mobile navigation">
			<?php foreach ( $nav as $item ) : ?>
				<?php
				$tabs  = $ap_sh_tabs( $item );
				$label = trim( (string) $item['label'] );
				$url   = trim( (string) ( $item['url'] ?? '' ) );
				?>
				<?php if ( $tabs ) : ?>
					<details>
						<summary><?php echo esc_html( $label ); ?></summary>
						<?php foreach ( $tabs as $tab ) : ?>
							<?php foreach ( $ap_sh_sections( $tab ) as $section ) : ?>
								<div class="ap-mobile-subgroup">
									<?php if ( '' !== $section['url'] ) : ?>
										<a class="ap-mobile-subhead" href="<?php echo esc_url( $section['url'] ); ?>"><?php echo esc_html( $section['label'] ); ?><span class="ap-mobile-subhead-chev" aria-hidden="true">&rsaquo;</span></a>
									<?php else : ?>
										<p><?php echo esc_html( $section['label'] ); ?></p>
									<?php endif; ?>
									<?php foreach ( $section['links'] as $link ) : ?>
										<?php $ap_sh_mobile_item( $link ); ?>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</details>
				<?php elseif ( '' !== $url ) : ?>
					<a class="ap-mobile-top-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>

			<div class="ap-mobile-menu-actions">
				<?php if ( $trial ) : ?>
					<a class="ap-intl-free" href="<?php echo esc_url( $props['trial_url'] ); ?>"><?php echo esc_html( $props['trial_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $demo ) : ?>
					<a class="ap-mobile-menu-demo" href="<?php echo esc_url( $props['demo_url'] ); ?>"><?php echo esc_html( $props['demo_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $tel && '' !== $phone ) : ?>
					<a class="ap-mobile-sales" href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
	</div>
</div>
