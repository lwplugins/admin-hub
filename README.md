# LW Plugins admin hub

The shared **LW Plugins** overview page (`wp-admin/admin.php?page=lw-plugins`)
for every LW plugin: a searchable table of all plugins in the
[registry](https://github.com/lwplugins/registry) with their state
(Active / Inactive / Not installed), installed version, a Settings link for
active plugins, an Activate button for installed but inactive ones and a
GitHub link for the rest. No update checks, no installs.

This repo is the **one source**. Each LW plugin carries its own synced copy
(no runtime dependency on another plugin, a Composer package or the network,
except the registry fetch), so every plugin still works on its own.

## Layout

| Path | What |
|------|------|
| `php/` | Hub classes, namespace `LightweightPlugins\AdminHub`, text domain `lw-admin-hub` |
| `src/` | React app (wp-scripts, React from core, WordPress 6.6+) |
| `build/` | Committed build of `src/` |
| `icons/` | Plugin icons (copied from the registry repo `icons/`) |
| `languages/` | `lw-admin-hub.pot` + `lw-admin-hub-hu_HU.po` |
| `templates/ParentPage.php.tpl` | Compatibility shim for the plugins' old `Admin\ParentPage` |
| `tools/Sync/`, `bin/sync.php` | The sync tool |
| `tests/` | PHPUnit (Brain Monkey, no WordPress) |

| Class | Job |
|-------|-----|
| `Hub` | `init()`, candidate registration, winner gate |
| `Negotiator` | Pure: highest version wins, tie goes to the lowest class name |
| `Page` | Top-level `lw-plugins` menu, mount point, assets |
| `RestController` | `lw-plugins/v1/hub` routes |
| `Registry` / `RegistryFallback` | Remote registry, 12 h transient, bundled fallback, normalization |
| `Detector` | Installed / active / version per registry slug |
| `Catalog` | Registry + detection → table rows |
| `Activator` | Guarded `activate_plugin()` |
| `Assets` | Paths/URLs under the host plugin's `assets/hub/` |

## How the copies agree (version negotiation)

1. The plugin calls `Admin\Hub\Hub::init( MAIN_PLUGIN_FILE )` on every request
   (bootstrap, not only in admin: REST needs it).
2. Each copy adds itself to the shared filter `lw_plugins_hub_candidates` as
   `[ 'version' => Hub::VERSION, 'class' => <its FQCN>, 'file' => ... ]`.
3. On `admin_menu` priority **5** and on `rest_api_init`, every copy asks
   `Negotiator::winner()`. Only the winner adds the `lw-plugins` page and the
   REST routes; the others do nothing.
4. Legacy `ParentPage::maybe_register()` copies (plugins not migrated yet) run
   inside `admin_menu` callbacks at priority 10+ and return early when
   `$admin_page_hooks['lw-plugins']` exists, so an old card page can no longer
   win over the hub.

Bump `Hub::VERSION` for every hub change that should reach sites where an
older copy is also installed.

## REST contract

All routes need a logged-in user (cookie + `X-WP-Nonce`, which apiFetch sends).

`GET /wp-json/lw-plugins/v1/hub/plugins` (capability `manage_options`)

```json
{ "plugins": [ {
  "slug": "lw-seo", "name": "LW SEO", "description": "…",
  "status": "active | inactive | missing", "version": "1.7.3 | null",
  "beta": false, "color": "#2271b1", "iconUrl": "…/assets/hub/icons/lw-seo.svg | null",
  "settingsUrl": "…/admin.php?page=lw-seo | null", "githubUrl": "https://github.com/… | null",
  "canActivate": false
} ] }
```

`POST /wp-json/lw-plugins/v1/hub/plugins/{slug}/activate` (capability
`activate_plugins`, plus `activate_plugin` for the file) → `{ "plugin": Row }`.
Only a slug that is in the registry **and** installed is activated; the file
passed to `activate_plugin()` is always the matching `get_plugins()` key
(directory = slug), never a path from the request. Errors:
`lw_hub_unknown_plugin` 404, `lw_hub_not_installed` 409, `lw_hub_forbidden` 403,
`lw_hub_activation_failed` 500. Already active returns 200.

## Registry

Remote `plugins.json` → transient `lw_plugins_registry` (12 h, shared with the
legacy copies, raw JSON) → normalized. When the remote is unreachable or
unusable, `RegistryFallback` is used. A remote entry takes missing fields
from the bundled entry of the same slug. Keep `RegistryFallback` and `icons/`
in step with the registry repo.

- The list is exactly the registry's list. Plugins not listed there
  (currently LW Memberships and LW Slider) are not shown, and the bundled
  fallback leaves them out too.
- Safeguard: an entry with `"status": "hidden"` is never shown.
- Site-level changes: filter `lw_plugins_hub_registry` (array of entries).
- Descriptions are translated when the text matches a bundled one.
- A slug without a bundled icon gets a neutral plugin glyph.

## Syncing into a plugin

```bash
cd admin-hub
composer install && npm ci && npm run build   # build/ is committed; rebuild after src/ changes
php bin/sync.php ../lw-disable --shim         # first time: also writes the ParentPage shim
php bin/sync.php ../lw-disable                # later syncs
php bin/sync.php ../lw-seo --dry-run          # show what would change
```

What it does (idempotent; a second run prints `no changes`):

- **PHP** → `{psr4-dir}/Admin/Hub/*.php`: namespace `LightweightPlugins\AdminHub`
  → `{PluginNS}\Admin\Hub`, `'lw-admin-hub'` → the plugin text domain, a
  "synced, do not edit" note. Classes removed from the hub are removed.
- **Assets** → `assets/hub/`: `index.js` (text domain rewritten),
  `index.css`, `index-rtl.css`, `index.asset.json` (the manifest as JSON so the
  plugin's phpcs has no generated PHP to scan) and `icons/`. Not `build/`:
  wp-scripts empties `build/` on every build of the plugin's own admin.
- **Translations** → `languages/` (needs `msgfmt` and `wp`). The plugin's own
  wording of a hub string always wins, and the plugin files keep their format:
  - `{domain}-{locale}.po`: hub entries the plugin lacks are added (references
    pointing at the plugin paths), an empty plugin `msgstr` gets the hub
    translation. Nothing else in the file is touched (header, wrapping,
    references, flags); no `msgcat` re-render.
  - `.mo` compiled from that `.po`.
  - `{domain}-{locale}-{md5}.json` (md5 of `assets/hub/index.js`, as WordPress
    looks it up) made with `wp i18n make-json` from the **merged** `.po`,
    restricted to the hub script's strings. So a plugin that says
    "Próbáld újra" where the hub says "Újrapróbálás" keeps its wording in the
    browser too.
  - `{domain}.pot`: when the plugin has its own i18n script (a `package.json`
    or `composer.json` script named `i18n` or running `wp i18n make-pot`),
    the `.pot` is left to it. Its make-pot scans `assets/hub/` and the synced
    PHP, so it picks the hub strings up; the sync only prints
    ``run `npm run i18n` `` while hub strings are missing (and warns if the
    script excludes `assets/`). Without such a script, only the missing hub
    entries are appended.
- `--shim` replaces `Admin/ParentPage.php` with a thin class: `SLUG`,
  `maybe_register()` (NoticeManager init + `Hub::ensure_menu()`),
  `get_plugins_registry()`.

Options: `--namespace=`, `--php-dir=`, `--text-domain=` (auto-detected from
`composer.json` and the main plugin file header), `--no-i18n`, `--dry-run`.

Manual step, once per plugin: call `Hub::init( LW_X_FILE )` from the plugin
bootstrap. The tool prints a TODO line while it cannot find that call.

## Quality gates

```bash
composer phpcs     # WPCS, long arrays + full docblocks (strictest LW ruleset)
composer analyse   # PHPStan level 5, PHP 8.0 grammar
composer test      # PHPUnit
npm run lint:js && npm run build
```

After a sync, run the plugin's own gates (phpcs incl. the checkstyle count,
analyse, test, build).
