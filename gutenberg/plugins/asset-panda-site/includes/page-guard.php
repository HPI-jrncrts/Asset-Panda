<?php
/**
 * A page that arrives damaged loads itself again, once.
 *
 * Rex (iPad, Safari) saw it twice, 2026-11-08 and 2026-11-15: a page that began part-way through (no title, its
 * stylesheet printed as text) and a page that stayed empty. Our HTML was whole on the server and on every other
 * device; the copy that reached that iPad was not. Joseph, 2026-11-16: "Let's please make sure nothing like this ever
 * happens."
 *
 * Two tiny inline scripts bracket every front-end page:
 *   - the first thing in <head> marks the page as started; once the document is parsed it checks that the end mark
 *     is there too, and once the page has loaded, that the kit stylesheets applied (a token on the root);
 *   - the last thing before </body> marks the end and checks that the start mark is there.
 * A missing mark or missing styles means the page did not arrive whole: it is fetched again under a fresh address
 * (?ap_fresh=<time>.<reason>, so no browser, edge or server cache can hand back the same damaged copy), and that fresh page
 * takes the parameter back out of the address bar before analytics starts. One try per address per tab session:
 * a page that is still damaged after the fresh load stays as it is (no reload loops), and nothing happens without
 * session storage.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shared reload, as a JavaScript function expression: heal( reason ).
 *
 * @return string
 */
function ap_site_page_guard_heal_js() {
	return 'function(r){var d=document.documentElement,l=location,k="ap-heal:"+l.pathname;'
		. 'if(d.hasAttribute("data-ap-fresh")||/[?&]ap_fresh=/.test(l.search))return;'
		. 'try{if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,r+" "+Date.now());}catch(e){return;}'
		// The reason travels in the value (ap_fresh=<time>.end|start|css), so the access logs say why a page healed.
		. 'var s=l.search.replace(/^\\?/,"");l.replace(l.pathname+"?"+(s?s+"&":"")+"ap_fresh="+Date.now()+"."+r+l.hash);}';
}

/*
 * First thing in <head>: the start mark, the tidy-up after a fresh load, and the checks at parse end and load.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}
		$js = '(function(w,d){var h=d.documentElement,heal=' . ap_site_page_guard_heal_js() . ';'
			. 'h.setAttribute("data-ap-start","");'
			// A fresh copy after a damaged load: take the marker out of the address bar.
			. 'if(/[?&]ap_fresh=/.test(location.search)){h.setAttribute("data-ap-fresh","");'
			. 'try{var q=location.search.replace(/([?&])ap_fresh=[^&]*&?/,"$1").replace(/[?&]$/,"");'
			. 'history.replaceState(history.state,"",location.pathname+q+location.hash);}catch(e){}}'
			// Parsed, but the end of the page never arrived.
			. 'd.addEventListener("DOMContentLoaded",function(){if(!h.hasAttribute("data-ap-end"))heal("end");});'
			// Loaded (a load cut off mid-way may skip DOMContentLoaded): the end is there, and the kit stylesheets applied.
			// A frame that is not drawn has no computed styles at all (Firefox gives empty values for every property of a
			// display:none iframe: the hidden login splash was reloading once per Firefox visit, 10 of the 28 reloads in
			// the edge log of 2026-11-14..17), so the stylesheet check runs only where styles are computed.
			. 'w.addEventListener("load",function(){if(!h.hasAttribute("data-ap-end"))return heal("end");'
			. 'var c=getComputedStyle(h);'
			. 'if(c&&c.display&&d.querySelector("#ap-ui-tokens-css,#ap-ui-tokens-inline-css")&&!c.getPropertyValue("--ap-slate-500").trim())heal("css");'
			. '});})(window,document);';
		echo '<script id="ap-page-guard-start" data-no-defer="1" data-no-optimize="1" data-cookieconsent="ignore">' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed script.
	},
	-1000
);

/*
 * Last thing before </body>: the end mark, and the check that the start of the page arrived.
 */
add_action(
	'wp_footer',
	static function () {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}
		$js = '(function(d){var h=d.documentElement,heal=' . ap_site_page_guard_heal_js() . ';'
			. 'h.setAttribute("data-ap-end","");'
			. 'if(!h.hasAttribute("data-ap-start"))heal("start");})(document);';
		echo '<script id="ap-page-guard-end" data-no-defer="1" data-no-optimize="1" data-cookieconsent="ignore">' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed script.
	},
	PHP_INT_MAX
);
