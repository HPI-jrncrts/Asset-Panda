<?php
/**
 * site-header — the global header: the desktop bar with tab-rail mega menus,
 * the phone header with its drawer, and the phone app banner.
 *
 * Built on the live 2026 header (#ap-presentation-header-v5,
 * #ap-mobile-header-v1, #ap-app-banner-v1). Since 2026-10-19 the menu content
 * (Products, Solutions, Who We Help) follows the Asset Panda Navigation
 * Implementation Map, which supersedes the Figma Global Megamenu (4374:23298):
 * Solutions in its five revised groups, and two featured resource cards per
 * Who We Help group in place of Figma's single case-study card. The card
 * markup is kept for a tab that has a card but no featured list.
 *
 * One nav prop drives the desktop panels and the phone drawer, so the two
 * cannot drift. A tab with a url renders as a link (a hub page); the others
 * are buttons that only switch the panel. On phones a tab whose groups all
 * have headings splits into one section per group, as on live.
 *
 * A top-level item may set layout "columns" (Resources, since 2026-10-23): its
 * tabs then show all at once, side by side — one column per tab — instead of
 * the tab rail. A tab's heading tops its column (as a link when the tab has a
 * url); only when no tab has a heading is the item's label drawn once as an
 * eyebrow, so the panel is never unlabelled. Featured lists and cards are not
 * drawn in that layout. The phone drawer is the same for both layouts.
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

// Inline custom property carrying an icon URL for the CSS hover mask. Only a
// plain http(s) URL or site path survives, and none of the characters that
// could end the CSS string or the declaration (quotes, parentheses, semicolon,
// backslash, whitespace) may appear in it; anything else yields no declaration.
$ap_sh_icon_var = static function ( $name, $url ) {
	$url = esc_url_raw( (string) $url );
	if ( '' === $url || ! preg_match( '#^(?:https?://|/)[^\s"\'()\\\\<>;]+$#i', $url ) ) {
		return '';
	}
	return $name . ':url("' . $url . '")';
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
	$icon       = $ap_sh_src( $link['icon'] ?? '' );
	$desc       = trim( (string) ( $link['desc'] ?? '' ) );
	$icon_style = '' !== $icon ? $ap_sh_icon_var( '--ap-icon-url', $icon ) : '';
	?>
	<a class="ap-mega-link" href="<?php echo esc_url( $link['url'] ); ?>">
		<span class="ap-icon-box" aria-hidden="true"<?php echo '' !== $icon_style ? ' style="' . esc_attr( $icon_style ) . '"' : ''; ?>>
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

/*
 * The case-card art is the heaviest thing in the header, and its panel is laid out (hidden, but
 * inside the viewport) on every page, so loading="lazy" still downloads it. It ships as
 * data-ap-sh-src over a transparent 1px placeholder instead; behaviour.js swaps it in on the
 * first pointer or focus on the desktop bar, before a menu has faded in. The placeholder is a
 * constant, not a prop: esc_url() would strip its data: scheme, so it is escaped as an attribute.
 */
$ap_sh_blank = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

$ap_sh_card = static function ( $card ) use ( $ap_sh_src, $ap_sh_blank ) {
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
				<img class="ap-mega-card__img" src="<?php echo esc_attr( $ap_sh_blank ); ?>" data-ap-sh-src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $card['image_alt'] ?? '' ); ?>" decoding="async" />
				<?php if ( '' !== $logo ) : ?>
					<span class="ap-mega-card__logo"><img src="<?php echo esc_attr( $ap_sh_blank ); ?>" data-ap-sh-src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $card['logo_alt'] ?? '' ); ?>" decoding="async" /></span>
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

// A tab's featured resources, reduced to the entries that have a title and a url.
$ap_sh_featured = static function ( $tab ) {
	$items = isset( $tab['featured'] ) && is_array( $tab['featured'] ) ? $tab['featured'] : array();
	return array_values(
		array_filter(
			$items,
			static function ( $item ) {
				return is_array( $item )
					&& '' !== trim( (string) ( $item['title'] ?? '' ) )
					&& '' !== trim( (string) ( $item['url'] ?? '' ) );
			}
		)
	);
};

// Featured resource cards (Navigation Implementation Map, "Featured"): type, title, description, call to action.
$ap_sh_feature_list = static function ( $items ) {
	echo '<div class="ap-mega-featured">';
	foreach ( $items as $item ) {
		$type = trim( (string) ( $item['type'] ?? '' ) );
		$desc = trim( (string) ( $item['desc'] ?? '' ) );
		$cta  = trim( (string) ( $item['cta'] ?? '' ) );
		?>
		<a class="ap-mega-feature" href="<?php echo esc_url( $item['url'] ); ?>">
			<?php if ( '' !== $type ) : ?>
				<span class="ap-mega-feature__type"><?php echo esc_html( $type ); ?></span>
			<?php endif; ?>
			<span class="ap-mega-feature__title"><?php echo esc_html( $item['title'] ); ?></span>
			<?php if ( '' !== $desc ) : ?>
				<span class="ap-mega-feature__desc"><?php echo esc_html( $desc ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $cta ) : ?>
				<span class="ap-mega-feature__cta"><?php echo esc_html( $cta ); ?> <span class="ap-mega-feature__arrow" aria-hidden="true">&rarr;</span></span>
			<?php endif; ?>
		</a>
		<?php
	}
	echo '</div>';
};

$ap_sh_mobile_item = static function ( $link ) use ( $ap_sh_src, $ap_sh_icon_var ) {
	$icon       = $ap_sh_src( $link['icon'] ?? '' );
	$icon_style = '' !== $icon ? $ap_sh_icon_var( '--ap-mobile-icon-url', $icon ) : '';
	?>
	<a class="ap-mobile-item" href="<?php echo esc_url( $link['url'] ); ?>">
		<span class="ap-mobile-link-icon" aria-hidden="true"<?php echo '' !== $icon_style ? ' style="' . esc_attr( $icon_style ) . '"' : ''; ?>>
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
// Digits, "+" and "-" only, so the link reads tel:888-928-6112 exactly as on live.
$tel      = preg_replace( '/[^0-9+\-]/', '', (string) $props['phone_tel'] );
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
							<?php $columns = 'columns' === trim( (string) ( $item['layout'] ?? '' ) ); ?>
							<div class="ap-mega-panel ap-mega-<?php echo esc_attr( sanitize_title( $label ) ); ?>">
								<?php if ( $columns ) : ?>
									<?php
									// Every tab at once: one column per tab that has links.
									$cols   = array();
									$headed = false;
									foreach ( $tabs as $tab ) {
										$groups = $ap_sh_groups( $tab );
										if ( $groups ) {
											$cols[] = array(
												'tab'    => $tab,
												'groups' => $groups,
											);
											if ( '' !== trim( (string) ( $tab['heading'] ?? '' ) ) ) {
												$headed = true;
											}
										}
									}
									?>
									<div class="ap-mega-shell ap-mega-columns" style="<?php echo esc_attr( '--ap-mega-cols:' . count( $cols ) ); ?>">
										<?php if ( ! $headed ) : ?>
											<p class="ap-mega-eyebrow"><?php echo esc_html( $label ); ?></p>
										<?php endif; ?>
										<div class="ap-mega-cols">
											<?php foreach ( $cols as $col ) : ?>
												<?php
												$heading = trim( (string) ( $col['tab']['heading'] ?? '' ) );
												$tab_url = trim( (string) ( $col['tab']['url'] ?? '' ) );
												?>
												<div class="ap-mega-col" data-ap-mega-col="<?php echo esc_attr( $ap_sh_key( $col['tab'] ) ); ?>">
													<?php if ( '' !== $heading && '' !== $tab_url ) : ?>
														<a class="ap-mega-heading" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $heading ); ?></a>
													<?php elseif ( '' !== $heading ) : ?>
														<p class="ap-mega-heading"><?php echo esc_html( $heading ); ?></p>
													<?php endif; ?>
													<?php foreach ( $col['groups'] as $group ) : ?>
														<div class="ap-mega-group">
															<?php if ( '' !== $group['heading'] ) : ?>
																<p class="ap-mega-heading"><?php echo esc_html( $group['heading'] ); ?></p>
															<?php endif; ?>
															<?php foreach ( $group['links'] as $link ) : ?>
																<?php $ap_sh_link( $link ); ?>
															<?php endforeach; ?>
														</div>
													<?php endforeach; ?>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php else : ?>
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
											$featured = $ap_sh_featured( $tab );
											$card     = ! $featured && isset( $tab['card'] ) && is_array( $tab['card'] ) && ! empty( $tab['card']['title'] ) ? $tab['card'] : null;
											?>
											<div class="ap-help-panel<?php echo 0 === $t ? ' is-active' : ''; ?>" data-ap-help-panel="<?php echo esc_attr( $ap_sh_key( $tab ) ); ?>">
												<?php if ( '' !== $heading ) : ?>
													<p class="ap-mega-heading"><?php echo esc_html( $heading ); ?></p>
												<?php endif; ?>
												<?php if ( $featured ) : ?>
													<div class="ap-help-split">
														<div class="ap-help-links"><?php $ap_sh_group_block( $groups ); ?></div>
														<?php $ap_sh_feature_list( $featured ); ?>
													</div>
												<?php elseif ( $card ) : ?>
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
								<?php endif; ?>
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
