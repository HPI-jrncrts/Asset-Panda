/**
 * Generate Gutenberg block adapters from the kit's component contracts.
 *
 * gutenberg/CLAUDE.md rule 3: "Contract is the source of truth for the schema."
 * Hand-copying prop names into block.json is exactly how the two drift, so this
 * derives block.json and render.php from ui/components/<name>/component.json.
 *
 * Attribute names are kept identical to prop names. The layer rule asks for an
 * explicit attribute -> prop mapping, and render.php still performs one; keeping
 * the names equal simply means the mapping cannot be wrong.
 *
 * Blocks listed in HAND_WRITTEN are skipped — they have bespoke adapters.
 *
 * Usage: node tools/gen-blocks.mjs [--check]
 *   --check  exit non-zero if any generated file is out of date (for CI)
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const COMPONENTS = path.join(ROOT, 'ui', 'components');
const BLOCKS = path.join(ROOT, 'gutenberg', 'blocks');

const HAND_WRITTEN = new Set();

/** Icons are editorial, not derivable from the contract. */
const ICONS = {
  hero: 'cover-image',
  stats: 'chart-bar',
  awards: 'awards',
  industries: 'grid-view',
  'feature-cards': 'screenoptions',
  'value-props': 'yes-alt',
  comparison: 'columns',
  'case-studies': 'format-quote',
  'platform-stack': 'list-view',
  mission: 'heart',
  'site-header': 'menu',
  'site-footer': 'table-row-after',
  'trust-strip': 'awards',
  faq: 'editor-help',
  'sticky-list': 'editor-ul',
  'problem-callouts': 'warning',
  'dashboard-showcase': 'desktop',
  'demo-cta': 'megaphone',
  'vertical-hero': 'cover-image',
  'hub-hero': 'cover-image',
  'link-cards': 'grid-view',
  'problem-scene': 'warning',
  'capability-rows': 'list-view',
  'media-split': 'align-pull-left',
  'hub-intel': 'align-pull-left',
  'story-slider': 'format-quote',
  'assist-hero': 'format-chat',
  'orbit-hero': 'cover-image',
  'continuity-columns': 'columns',
  'capability-scene': 'screenoptions',
  'asset-scene': 'grid-view',
  'tab-showcase': 'table-row-before',
  'solution-hero': 'cover-image',
  'photo-tags': 'tag',
  'intel-dashboard': 'chart-area',
  'readiness-hero': 'cover-image',
  'founder-quote': 'format-quote',
  'why-cards': 'columns',
  'solution-faq': 'editor-help',
  'case-slider': 'slides',
  'solution-features': 'editor-ol',
  'solution-rows': 'excerpt-view',
  'solution-cta': 'megaphone',
  'solution-awards': 'awards',
  'readiness-problem': 'warning',
  'solution-trust': 'groups',
  'platform-rows': 'editor-ol',
  'platform-faq': 'editor-help',
  'platform-cta': 'megaphone',
  'assist-problem': 'warning',
  'pricing-hero': 'money-alt',
  'pricing-calculator': 'calculator',
  'thanks-hero': 'calendar-alt',
  'thanks-steps': 'editor-ol',
  'home-hero': 'cover-image',
  'plans-hero': 'money-alt',
  'plans-picker': 'tag',
  'plans-compare': 'editor-table',
  'plans-faq': 'editor-help',
  'demo-booking': 'calendar-alt',
  'demo-stats': 'chart-bar',
  'demo-stories': 'format-quote',
  'demo-footer': 'table-row-after',
  'demo-confirm': 'yes-alt',
  'form-hero': 'feedback',
  'media-band': 'format-video',
  'card-grid': 'grid-view',
  'message-hero': 'warning',
  'thanks-calendly': 'calendar-alt',
  'login-splash': 'admin-network',
  'roi-calculator': 'calculator',
};

const titleCase = (s) =>
  s.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');

/** component.json type -> block.json attribute type. */
function attrType(type) {
  switch (type) {
    case 'integer':
    case 'number':
      return 'number';
    case 'boolean':
      return 'boolean';
    case 'array':
      return 'array';
    case 'object':
      return 'object';
    default:
      return 'string';
  }
}

function defaultFor(prop) {
  if (Object.prototype.hasOwnProperty.call(prop, 'default')) return prop.default;
  switch (prop.type) {
    case 'array':
      return [];
    case 'boolean':
      return false;
    case 'integer':
    case 'number':
      return 0;
    case 'object':
      return {};
    default:
      return '';
  }
}

function buildBlockJson(name, contract) {
  const attributes = {};
  for (const [key, prop] of Object.entries(contract.props)) {
    attributes[key] = { type: attrType(prop.type), default: defaultFor(prop) };
    if (prop.enum) attributes[key].enum = prop.enum;
  }
  /*
   * Every AP section is a full-bleed band. Block themes wrap post content in a
   * constrained layout that clamps each direct child to the theme's content
   * width (645px in Twenty Twenty-Five), which crushes sections designed for a
   * 1120px container. Declaring align support and defaulting to "full" makes
   * WordPress emit the `alignfull` class, which constrained layouts exempt.
   * `align` is a block-level concern, so it is NOT a kit prop and is never
   * passed through to ap_render_component().
   */
  attributes.align = { type: 'string', default: 'full' };

  return {
    $schema: 'https://schemas.wp.org/trunk/block.json',
    apiVersion: 3,
    name: `assetpanda/${name}`,
    title: `AP ${titleCase(name)}`,
    category: 'design',
    icon: ICONS[name] || 'block-default',
    description: `${contract.description} This block is an adapter — it holds no markup; it maps attributes to kit props.`,
    textdomain: 'assetpanda',
    attributes,
    supports: { html: false, reusable: true, align: [ 'full', 'wide' ] },
    render: 'file:./render.php',
  };
}

function buildRenderPhp(name, contract) {
  const keys = Object.keys(contract.props);
  const width = Math.max(...keys.map((k) => k.length));
  const lines = keys
    .map((k) => {
      const pad = ' '.repeat(width - k.length);
      return `\t'${k}'${pad} => $attributes['${k}'] ?? ${phpDefault(contract.props[k])},`;
    })
    .join('\n');

  return `<?php
/**
 * AP ${titleCase(name)} block — server render (adapter).
 *
 * GENERATED by tools/gen-blocks.mjs from ui/components/${name}/component.json.
 * Do not edit by hand — change the contract and regenerate, or the block and
 * the kit will disagree about the schema.
 *
 * Maps the block's attributes into the kit's prop names and hands them to
 * ap_render_component(). No markup, no escaping, no styling here — all of that
 * lives in ui/components/${name}/.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused — this block has none).
 * @var WP_Block $block      Block instance (unused).
 *
 * @package AssetPanda\Gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

$props = array(
${lines}
);

$ap_html = ap_render_component( '${name}', $props );

if ( '' === trim( (string) $ap_html ) ) {
\treturn;
}

/*
 * The wrapper carries the alignment class. Block themes put post content in a
 * constrained layout that clamps every direct child to the theme's content
 * width; \`alignfull\` is the documented escape hatch, and this block defaults
 * to it because AP sections are full-bleed bands. \`align\` is a block concern,
 * so it is deliberately absent from the props above.
 *
 * The same layouts also insert a block gap (margin-block-start) between
 * siblings. AP sections are designed flush — each paints its own background
 * edge to edge — so the gap would show as a strip of page background between
 * them. The zero margin is inline because the theme rule it beats is
 * zero-specificity; it styles the wrapper only, never the component.
 * Longhands on purpose: WordPress passes this through its safe-CSS allowlist,
 * which drops the margin-block shorthand and leaves an empty style="".
 */
printf(
\t'<div %1$s>%2$s</div>',
\tget_block_wrapper_attributes( array( 'style' => 'margin-top:0;margin-bottom:0' ) ), // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped attribute string.
\t$ap_html // phpcs:ignore WordPress.Security.EscapeOutput -- kit escapes on output.
);
`;
}

function phpDefault(prop) {
  const d = defaultFor(prop);
  if (Array.isArray(d)) return 'array()';
  if (d && typeof d === 'object') return 'array()';
  if (typeof d === 'boolean') return d ? 'true' : 'false';
  if (typeof d === 'number') return String(d);
  if (d === '') return "''";
  return `'${String(d).replace(/'/g, "\'")}'`;
}

const check = process.argv.includes('--check');
const names = fs
  .readdirSync(COMPONENTS, { withFileTypes: true })
  .filter((d) => d.isDirectory())
  .map((d) => d.name)
  .filter((n) => !HAND_WRITTEN.has(n))
  .sort();

let stale = 0;
const written = [];

for (const name of names) {
  const contractPath = path.join(COMPONENTS, name, 'component.json');
  if (!fs.existsSync(contractPath)) continue;
  const contract = JSON.parse(fs.readFileSync(contractPath, 'utf8'));

  const dir = path.join(BLOCKS, name);
  const files = {
    'block.json': JSON.stringify(buildBlockJson(name, contract), null, '\t') + '\n',
    'render.php': buildRenderPhp(name, contract),
  };

  for (const [file, content] of Object.entries(files)) {
    const target = path.join(dir, file);
    const current = fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : null;
    if (current === content) continue;
    if (check) {
      console.error(`stale: gutenberg/blocks/${name}/${file}`);
      stale++;
      continue;
    }
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(target, content);
    written.push(`${name}/${file}`);
  }
}

if (check) {
  console.log(stale ? `${stale} generated file(s) out of date` : 'all block adapters up to date');
  process.exit(stale ? 1 : 0);
}

console.log(`generated ${written.length} file(s) across ${names.length} block(s):`);
for (const w of written) console.log('  ' + w);
