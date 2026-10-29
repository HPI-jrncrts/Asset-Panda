// TEST ONLY — drives wp-admin on the local site with Playwright, screenshot per step.
//
//   node e2e.mjs <out dir>        (env: AP_TEST_PORT, AP_CHROMIUM_PATH)
//
// Signed in with the auth cookies tests/setup.php generated (no password is typed).
// Network: only the local site; every other host is aborted and listed in the results.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire( 'C:/Sample Code/package.json' );
const { chromium } = require( 'playwright' );

const OUT = process.argv[ 2 ];
const SHOTS = path.join( OUT, 'shots' );
fs.mkdirSync( SHOTS, { recursive: true } );
const PORT = process.env.AP_TEST_PORT || '8898';
const session = JSON.parse( fs.readFileSync( path.join( OUT, 'session.json' ), 'utf8' ) );
const HOST = session.host;
const BASE = `http://${ HOST }`;
const UPLOAD = path.join( OUT, 'ap-tl-upload-test.png' );

const results = [];
const drafts = {};
const blocked = new Map();
const consoleErrors = [];
const ok = ( test, pass, detail = '' ) => {
	results.push( { test, pass: !! pass, detail: pass ? '' : detail } );
	console.log( ( pass ? 'PASS ' : 'FAIL ' ) + test + ( pass ? '' : '  -- ' + String( typeof detail === 'string' ? detail : JSON.stringify( detail ) ).slice( 0, 400 ) ) );
};
let shotN = 0;
const shot = async ( p, name, opts = {} ) => {
	shotN += 1;
	const file = path.join( SHOTS, String( shotN ).padStart( 2, '0' ) + '-' + name + '.png' );
	if ( opts.locator ) {
		await opts.locator.screenshot( { path: file } );
	} else {
		await p.screenshot( { path: file, fullPage: !! opts.full } );
	}
	console.log( '  shot ' + file );
	return file;
};

const browser = await chromium.launch( {
	executablePath: process.env.AP_CHROMIUM_PATH,
	args: [ `--host-resolver-rules=MAP ${ HOST } 127.0.0.1:${ PORT }` ],
} );
const ctx = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
ctx.setDefaultTimeout( 120000 );
ctx.setDefaultNavigationTimeout( 180000 );
await ctx.addCookies( session.cookies.admin.map( ( c ) => ( { name: c.name, value: c.value, domain: HOST, path: '/' } ) ) );
await ctx.route( '**/*', ( route ) => {
	let u;
	try {
		u = new URL( route.request().url() );
	} catch {
		return route.continue();
	}
	if ( ! /^https?:$/.test( u.protocol ) || u.hostname === HOST ) {
		return route.continue();
	}
	blocked.set( u.hostname, ( blocked.get( u.hostname ) || 0 ) + 1 );
	return route.abort();
} );
const page = await ctx.newPage();
page.on( 'dialog', ( d ) => d.accept() );
page.on( 'console', ( m ) => {
	// Aborted third-party requests (the test blocks every non-local host) are expected noise.
	if ( m.type() === 'error' && ! /Failed to load resource|net::ERR_/.test( m.text() ) ) {
		consoleErrors.push( m.text().slice( 0, 300 ) );
	}
} );
page.on( 'pageerror', ( e ) => consoleErrors.push( 'pageerror: ' + String( e ).slice( 0, 300 ) ) );
page.on( 'crash', () => console.log( '  !! page crashed' ) );
page.on( 'close', () => console.log( '  !! page closed' ) );
browser.on( 'disconnected', () => console.log( '  !! browser disconnected' ) );

const lib = `${ BASE }/wp-admin/admin.php?page=ap-template-library`;
const boxes = () => page.locator( '.ap-tl-boxes > .ap-tl-box' );
const box = ( i ) => boxes().nth( i );
const topField = ( b, key ) => b.locator( `.ap-tl-box__body > .ap-tl-fields > .ap-tl-field[data-key="${ key }"]` );
const openBox = async ( i ) => {
	const b = box( i );
	if ( ( await b.locator( '.ap-tl-box__toggle' ).getAttribute( 'aria-expanded' ) ) !== 'true' ) {
		await b.locator( '.ap-tl-box__toggle' ).click();
	}
	return box( i );
};
const boxTitles = async () => page.$$eval( '.ap-tl-boxes > .ap-tl-box .ap-tl-box__title', ( els ) => els.map( ( e ) => e.textContent ) );

async function uploadThroughModal( chooseButton, expectButtonText ) {
	await chooseButton.click();
	const modal = page.locator( '.media-modal:visible' ).last();
	await modal.waitFor();
	const uploadTab = modal.locator( '.media-menu-item, .media-router button, .media-router a' ).filter( { hasText: /Upload files/i } ).first();
	if ( await uploadTab.count() ) {
		await uploadTab.click();
	}
	const [ chooser ] = await Promise.all( [ page.waitForEvent( 'filechooser' ), modal.locator( 'button.browser, .upload-ui button' ).filter( { hasText: /Select Files/i } ).first().click() ] );
	await chooser.setFiles( UPLOAD );
	await modal.locator( '.attachments .attachment.selected:not(.uploading)' ).first().waitFor( { timeout: 120000 } );
	const select = modal.locator( '.media-button-select' );
	await page.waitForFunction( () => {
		const b = document.querySelector( '.media-modal:not([style*="display: none"]) .media-button-select' );
		return b && ! b.disabled;
	} );
	ok( 'E-media modal button reads "' + expectButtonText + '"', ( await select.textContent() ).trim() === expectButtonText, await select.textContent() );
	return { modal, select };
}

async function editorCheck( editLink, label ) {
	await page.goto( editLink );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() && wp.data.select( 'core/block-editor' ).getBlocks().length > 0, null, { timeout: 180000 } );
	await page.waitForTimeout( 4000 );
	const info = await page.evaluate( () => {
		const blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		const flat = [];
		const walk = ( list ) => list.forEach( ( b ) => {
			flat.push( b );
			walk( b.innerBlocks );
		} );
		walk( blocks );
		return {
			status: wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' ),
			top: blocks.map( ( b ) => b.name ),
			invalid: flat.filter( ( b ) => b.isValid === false ).map( ( b ) => b.name ),
			missing: flat.filter( ( b ) => b.name === 'core/missing' ).length,
		};
	} );
	ok( `E-${ label } opens in the block editor as a draft`, info.status === 'draft', info );
	ok( `E-${ label } every block is valid in the block editor (no "unexpected content" warnings)`, info.invalid.length === 0 && info.missing === 0, info );
	const frame = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const warnings = ( await page.locator( '.block-editor-warning' ).count() ) + ( await frame.locator( '.block-editor-warning' ).count().catch( () => 0 ) );
	ok( `E-${ label } no block warnings rendered`, warnings === 0, warnings );
	await page.keyboard.press( 'Escape' ).catch( () => {} );
	await shot( page, `${ label }-editor` );
	return info;
}

try {
	/* ------------------------------------------------------------ library */
	await page.goto( lib );
	await page.locator( '.ap-tl-library.is-ready' ).waitFor();
	const cards = page.locator( '.ap-tl-card' );
	const nCards = await cards.count();
	ok( 'E1 library lists every default template as a card', nCards >= 20, nCards );
	const partners = page.locator( '.ap-tl-card[data-template="partners"]' );
	ok( 'E2 Partners page card: "Reference missing", Use template disabled', ( await partners.textContent() ).includes( 'Reference missing' ) && ( await partners.locator( 'button[disabled]' ).count() ) === 1 );
	const solCard = page.locator( '.ap-tl-card[data-template="solution"]' );
	ok( 'E3 Solution card shows its reference and a generated section placeholder', ( await solCard.textContent() ).includes( 'Inventory Management Software' ) && ( await solCard.locator( '.ap-tl-ph__bar' ).count() ) >= 5 );
	await shot( page, 'library', { full: true } );
	await page.locator( '.ap-tl-tab[data-group="solution"]' ).click();
	const visible = await page.locator( '.ap-tl-card:not([hidden])' ).count();
	ok( 'E4 group tab filters the cards', visible === 1, visible );
	await shot( page, 'library-solution-tab' );
	await page.locator( '.ap-tl-tab[data-group="all"]' ).click();
	await page.locator( '.ap-tl-search' ).fill( 'blog' );
	ok( 'E5 search filters the cards', ( await page.locator( '.ap-tl-card:not([hidden])' ).count() ) >= 1 && ( await page.locator( '.ap-tl-card[data-template="solution"]' ).isHidden() ) );
	await page.locator( '.ap-tl-search' ).fill( '' );

	/* ------------------------------------------------------- builder: page */
	await solCard.locator( '.ap-tl-use' ).click();
	await page.locator( '#ap-tl-builder.is-ready' ).waitFor();
	ok( 'E6 builder shows one box per section of the reference (7)', ( await boxes().count() ) === 7, await boxTitles() );
	ok( 'E7 box titles come from block.json', JSON.stringify( await boxTitles() ) === JSON.stringify( [ 'Vertical Hero', 'Trust Strip', 'Heading (H2)', 'Paragraph', 'Stats', 'Faq', 'Demo Cta' ] ), await boxTitles() );
	await shot( page, 'builder' );

	const hero = box( 0 );
	const titleField = topField( hero, 'title' ).locator( 'textarea' );
	ok( 'E8 hero title pre-filled from the reference', ( await titleField.inputValue() ) === 'Inventory software\nthat keeps count', await titleField.inputValue() );
	await titleField.fill( 'Inventory software\nbuilt by the Template Library' );
	ok( 'E9 box subtitle follows the edited title', ( await hero.locator( '.ap-tl-box__sub' ).textContent() ).includes( 'built by the Template Library' ) );
	await shot( page, 'edit-title', { locator: topField( hero, 'title' ) } );

	const dash = topField( hero, 'dashboard' );
	ok( 'E10 image field is a media picker with a preview', ( await dash.locator( '.ap-tl-media__preview img' ).count() ) === 1 );
	const { select } = await uploadThroughModal( dash.locator( '.ap-tl-media__choose' ), 'Use this image' );
	await shot( page, 'media-modal-uploaded' );
	await select.click();
	await page.locator( '.media-modal:visible' ).waitFor( { state: 'hidden' } ).catch( () => {} );
	const dashUrl = await dash.locator( '.ap-tl-media__url' ).inputValue();
	ok( 'E11 image swapped through the media library (uploaded PNG), stored as a site-relative path', /^\/wp-content\/uploads\/\d{4}\/\d{2}\/ap-tl-upload-test.*\.png$/.test( dashUrl ), dashUrl );
	ok( 'E12 preview shows the new image', ( await dash.locator( '.ap-tl-media__preview img' ).getAttribute( 'src' ) ) === dashUrl );
	await shot( page, 'image-swapped', { locator: dash } );

	const stats = await openBox( 4 );
	const items = stats.locator( '.ap-tl-field[data-key="items"]' ).first();
	ok( 'E13 stats items shown as a repeater with 3 items', ( await items.locator( ':scope > .ap-tl-repeater__list > .ap-tl-item' ).count() ) === 3 );
	await items.locator( ':scope > .ap-tl-add' ).click();
	const added = items.locator( ':scope > .ap-tl-repeater__list > .ap-tl-item' ).nth( 3 );
	ok( 'E14 "Add item" adds an open, empty row', ( await added.getAttribute( 'open' ) ) !== null && ( await added.locator( '.ap-tl-field[data-key="value"] input' ).inputValue() ) === '' );
	await added.locator( '.ap-tl-field[data-key="value"] input' ).fill( '4x' );
	await added.locator( '.ap-tl-field[data-key="label"] input' ).fill( 'Faster audits' );
	await added.locator( '.ap-tl-field[data-key="description"] textarea, .ap-tl-field[data-key="description"] input' ).first().fill( 'Added in the Template Library test.' );
	ok( 'E15 new row title follows its fields', ( await added.locator( '.ap-tl-item__title' ).textContent() ).includes( 'Faster audits' ) || ( await added.locator( '.ap-tl-item__title' ).textContent() ).includes( '4x' ) );
	await shot( page, 'repeater-added', { locator: stats } );

	const para = await openBox( 3 );
	await para.locator( '.ap-tl-field textarea' ).first().fill( '<strong>Spreadsheets</strong> break. The Template Library built this paragraph.' );

	// Duplicate the trust strip, then remove the copy; move the FAQ above the stats.
	await box( 1 ).locator( '.ap-tl-box__actions button' ).nth( 2 ).click();
	ok( 'E16 duplicate adds a section', ( await boxes().count() ) === 8 );
	await box( 2 ).locator( '.ap-tl-box__actions button' ).nth( 3 ).click();
	ok( 'E17 remove deletes a section (after confirm)', ( await boxes().count() ) === 7 );
	await box( 5 ).locator( '.ap-tl-box__actions button' ).nth( 0 ).click();
	const order = await boxTitles();
	ok( 'E18 move up reorders (FAQ before Stats)', order[ 4 ] === 'Faq' && order[ 5 ] === 'Stats', order );
	await shot( page, 'reordered' );

	await page.locator( '.ap-tl-page-title' ).fill( 'Template Library E2E Inventory' );
	const slugInput = page.locator( '.ap-tl-page input.code' ).first();
	await slugInput.fill( 'tl-e2e-inventory' );
	ok( 'E19 parent pre-filled with the reference parent (Solutions)', ( await page.locator( '.ap-tl-page select' ).first().locator( 'option:checked' ).textContent() ).includes( 'Solutions' ) );

	await page.locator( '.ap-tl-save' ).click();
	await page.waitForFunction( () => ( document.querySelector( '.ap-tl-progress-note' ) || {} ).textContent );
	ok( 'E20 Save progress confirms', ( await page.locator( '.ap-tl-progress-note' ).textContent() ).includes( 'Progress saved' ) );
	await page.reload();
	await page.locator( '#ap-tl-builder.is-ready' ).waitFor();
	ok( 'E21 reopening offers the saved progress', await page.locator( '.ap-tl-banner' ).isVisible() );
	await shot( page, 'progress-banner' );
	await page.locator( '.ap-tl-resume' ).click();
	ok( 'E22 resumed state: edited title, new stat, order, page title', ( await box( 0 ).locator( '.ap-tl-field[data-key="title"] textarea' ).first().inputValue() ).includes( 'built by the Template Library' ) && ( await page.locator( '.ap-tl-page-title' ).inputValue() ) === 'Template Library E2E Inventory' && ( await boxTitles() )[ 4 ] === 'Faq' );
	const statsAgain = await openBox( 5 );
	ok( 'E23 resumed repeater keeps the added item', ( await statsAgain.locator( '.ap-tl-field[data-key="items"] .ap-tl-repeater__list > .ap-tl-item' ).count() ) === 4 );

	await page.locator( '.ap-tl-send' ).click();
	await page.locator( '.ap-tl-success' ).waitFor();
	const editLink = await page.locator( '.ap-tl-edit-link' ).getAttribute( 'href' );
	const previewLink = await page.locator( '.ap-tl-preview-link' ).getAttribute( 'href' );
	ok( 'E24 Send to Draft shows Preview and Open in editor', /post\.php\?post=\d+/.test( editLink ) && /preview=true/.test( previewLink ), [ editLink, previewLink ] );
	drafts.page = Number( new URL( editLink, BASE ).searchParams.get( 'post' ) );
	await shot( page, 'draft-created' );

	const preview = await ctx.newPage();
	await preview.goto( previewLink );
	const html = await preview.content();
	ok( 'E25 front-end preview renders the edited title', html.includes( 'built by the Template Library' ) );
	ok( 'E26 preview renders the uploaded image and the added stat', html.includes( 'ap-tl-upload-test' ) && html.includes( 'Faster audits' ) && html.includes( '4x' ) );
	ok( 'E27 preview renders the kit sections', ( await preview.locator( '.ap-vhero' ).count() ) >= 1 && ( await preview.locator( '.ap-stats-inner' ).count() ) >= 1 && ( await preview.locator( '.ap-faq' ).count() ) >= 1 );
	ok( 'E28 preview of a draft (not a published page)', /preview=true/.test( preview.url() ) );
	await shot( preview, 'page-preview', { full: true } );
	await preview.close();

	await editorCheck( editLink, 'page' );

	/* ------------------------------------------------ builder: blog resource */
	await page.goto( lib );
	await page.locator( '.ap-tl-card[data-template="blog"] .ap-tl-use' ).click();
	await page.locator( '#ap-tl-builder.is-ready' ).waitFor();
	ok( 'E29 blog builder: 9 boxes, core and kit', ( await boxes().count() ) === 9, await boxTitles() );
	await box( 0 ).locator( '.ap-tl-field textarea' ).first().fill( 'Edited intro from the <em>Template Library</em>.' );
	const list = await openBox( 3 );
	await list.locator( '.ap-tl-add' ).click();
	await list.locator( '.ap-tl-strings__row input' ).last().fill( 'Audit twice a year' );
	const group = await openBox( 5 );
	ok( 'E30 group opens to its nested button with text and link fields', ( await group.locator( '.ap-tl-core--nested .ap-tl-field' ).count() ) >= 2 );
	await group.locator( '.ap-tl-field[data-key="text"] input' ).first().fill( 'Talk to sales' );
	const table = await openBox( 7 );
	ok( 'E31 table is shown read-only with a preview', ( await table.locator( '.ap-tl-static__note' ).count() ) === 1 );
	ok( 'E32 resource type pre-selected (Blog)', ( await page.locator( 'select[data-taxonomy="resource-types"] option:checked' ).textContent() ).trim() === 'Blog' );
	await page.locator( '.ap-tl-page-title' ).fill( 'Template Library E2E Blog' );
	await shot( page, 'blog-builder', { full: true } );
	await page.locator( '.ap-tl-send' ).click();
	await page.locator( '.ap-tl-success' ).waitFor();
	const blogEdit = await page.locator( '.ap-tl-edit-link' ).getAttribute( 'href' );
	const blogPreview = await page.locator( '.ap-tl-preview-link' ).getAttribute( 'href' );
	drafts.blog = Number( new URL( blogEdit, BASE ).searchParams.get( 'post' ) );
	const bp = await ctx.newPage();
	await bp.goto( blogPreview );
	const bhtml = await bp.content();
	ok( 'E33 blog preview renders the edits (intro, list item, button)', bhtml.includes( 'Edited intro from the' ) && bhtml.includes( 'Audit twice a year' ) && bhtml.includes( 'Talk to sales' ) );
	await shot( bp, 'blog-preview', { full: true } );
	await bp.close();
	await editorCheck( blogEdit, 'blog' );

	/* ----------------------------------------------------------- settings */
	await page.goto( `${ BASE }/wp-admin/admin.php?page=ap-template-library-settings` );
	await shot( page, 'settings' );
	await page.locator( '#ap-tl-ref' ).fill( '/resource-center/compare/asset-panda-vs-ezo/' );
	await page.locator( '#ap-tl-label' ).fill( 'E2E compare copy' );
	await page.locator( '#ap-tl-group' ).selectOption( 'compare' );
	await page.locator( '#ap-tl-add' ).scrollIntoViewIfNeeded();
	await shot( page, 'settings-add-form' );
	await page.locator( '.ap-tl-add-form [type="submit"]' ).click();
	await page.waitForLoadState();
	ok( 'E34 admin adds a template from a resource URL', ( await page.locator( '.notice-success' ).textContent() ).includes( 'Template added' ) && ( await page.locator( 'tr', { hasText: 'E2E compare copy' } ).count() ) === 1 );
	await page.locator( '#ap-tl-row-legal button[type="submit"]' ).click();
	await page.waitForLoadState();
	ok( 'E35 admin hides a default template', ( await page.locator( '.notice-success' ).textContent() ).includes( 'hidden' ) );
	const thumbBtn = page.locator( '#ap-tl-row-solution .ap-tl-pick-thumb' );
	await thumbBtn.click();
	const modal = page.locator( '.media-modal:visible' ).last();
	await modal.waitFor();
	const libTab = modal.locator( '.media-menu-item, .media-router button, .media-router a' ).filter( { hasText: /Media Library/i } ).first();
	if ( await libTab.count() ) {
		await libTab.click();
	}
	await modal.locator( '.attachments .attachment' ).first().click();
	await Promise.all( [ page.waitForNavigation(), modal.locator( '.media-button-select' ).click() ] );
	ok( 'E36 admin sets a card thumbnail from the media library', ( await page.locator( '.notice-success' ).textContent() ).includes( 'Thumbnail saved' ) && ( await page.locator( '#ap-tl-row-solution .ap-tl-thumb-mini img' ).count() ) === 1 );
	await shot( page, 'settings-after' );

	await page.goto( lib );
	await page.locator( '.ap-tl-library.is-ready' ).waitFor();
	const thumbSrc = await page.locator( '.ap-tl-card[data-template="solution"] .ap-tl-card__thumb img' ).getAttribute( 'src' ).catch( () => '' );
	ok( 'E37 library card shows the chosen thumbnail', /ap-tl-upload-test/.test( thumbSrc || '' ), thumbSrc );
	ok( 'E38 hidden template gone, added template present', ( await page.locator( '.ap-tl-card[data-template="legal"]' ).count() ) === 0 && ( await page.locator( '.ap-tl-card', { hasText: 'E2E compare copy' } ).count() ) === 1 );
	await shot( page, 'library-after' );
	await page.locator( '.ap-tl-card', { hasText: 'E2E compare copy' } ).locator( '.ap-tl-use' ).click();
	await page.locator( '#ap-tl-builder.is-ready' ).waitFor();
	ok( 'E39 the added template opens in the builder (compare: kit + core boxes)', ( await boxes().count() ) === 5, await boxTitles() );
	await openBox( 2 );
	await shot( page, 'custom-template-builder' );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( lib );
	await page.locator( '.ap-tl-library.is-ready' ).waitFor();
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	ok( 'E40 library has no horizontal overflow at 390px', overflow <= 0, overflow );
	await shot( page, 'library-mobile' );
} catch ( e ) {
	ok( 'E-unexpected error', false, String( e && e.stack ? e.stack : e ) );
	await shot( page, 'error' ).catch( () => {} );
}

ok( 'E-no browser JS errors on plugin screens', consoleErrors.length === 0, consoleErrors );
await browser.close();
fs.writeFileSync( path.join( OUT, 'e2e-results.json' ), JSON.stringify( { results, drafts, blocked: Object.fromEntries( blocked ), consoleErrors }, null, 2 ) );
fs.writeFileSync( path.join( OUT, 'e2e-drafts.json' ), JSON.stringify( drafts ) );
const failed = results.filter( ( r ) => ! r.pass ).length;
console.log( `\n${ results.length - failed }/${ results.length } passed; blocked hosts: ${ JSON.stringify( Object.fromEntries( blocked ) ) }` );
process.exit( failed ? 1 : 0 );
