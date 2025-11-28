# format_minimoodlewall · AI README

> Working note for autonomous agents extending the Minimal Moodle Wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards.
- **Core concept**: Every course must pick a *tag set*; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Primary touch points**: `lib.php` (format logic + course options), `classes/tag_manager.php` (DB + files + cache), `tag_management.php` (admin UI), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (design variants), `amd/` (future JS helpers).

## Architecture Cheatsheet
- **Course format base** (`lib.php`)
  - Enforces single-section behavior, course index support, and hides section crumbs on activity pages.
  - Adds course options: `tagsetid` (required at create time), `enablefiltering`, `designvariant`.
  - `edit_form_validation()` forces a tag set selection.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Tables: `*_tagsets`, `*_tags`, `*_cmtags` (one tag per `cm`).
  - File areas (`FILEAREA_CARDIMAGE`, `FILEAREA_FILTERIMAGE`) live in system context; served via `format_minimoodlewall_pluginfile()`.
  - `tag_manager` handles CRUD, cache invalidation, filemanager prep/saving, default palettes, and usage counts.
- **Admin UX** (`settings.php`, `tag_management.php`, `classes/form/*`)
  - External admin page for tag sets/tags with SVG previews and accent swatches.
  - Only admins (`moodle/site:config`) can manage tags; links exposed under Site administration › Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + design variants (`classic`, `light`, `dark`).
- **Caching** (`cache/definitions.php`)
  - `tagconfigurations`: tag + artwork metadata per tagset.
  - `activitytagmappings`: cm→tag lookup.
  - Clear via `tag_manager::clear_tag_cache()` whenever tag data changes.

## Workflows & Entry Points
1. **Course creation**
   - User selects Minimal Moodle Wall format.
   - Must choose an existing tag set (option disabled later to avoid changing taxonomy mid-course).
2. **Tag management**
   - Admin page handles create/edit/delete for tag sets and tags, including SVG uploads.
   - SVGs must be `.svg` (enforced via filemanager options).
3. **Course editing**
  - Teachers see a tag-based activity chooser: clicking the "+" button reveals a dropdown of configured tags.
  - Selecting a tag opens a modal with three options: two quick-create shortcuts (pre-configured activity types) and a link to the full activity chooser.
  - This workflow ensures mandatory tagging and guides teachers toward recommended activity combinations.
  - **Version Support:** The plugin supports both Moodle 5.0 and earlier, and 5.1+ with automatic version detection:
    - **Moodle 5.1+**: Uses `format_minimoodlewall\output\courseformat\content\activitychooserbutton` class that extends the new `core_courseformat\output\local\content\activitychooserbutton` base class (introduced in MDL-86337).
    - **Moodle 5.0 and earlier**: Falls back to template overrides in `cm.php` export method with backward compatibility checks.
    - JavaScript is version-agnostic and works with both `data-section-id` (5.1+) and `data-sectionnum` (5.0 and earlier) attributes.
4. **Learner view**
   - Wall shows all activities from section 0 in a responsive grid.
   - Optional filter bar (enabled via course option) lists tags with usage counts; clicking filters the visible cards.

## Common Extension Tasks
- **Add teacher UI for tagging**
  - Introduce cm-level setting + form element (probably in `classes/courseformat/local/...` or observer hook).
  - Update `tag_manager::set_cm_tag()` (or equivalent) and invalidate caches.
- **Enhance filtering UX**
  - Extend `amd/src/*` to add reactive filtering, track active tag, and animate card visibility.
  - Expose filter data via `section.mustache` context.
- **Design variants**
  - Expand `designvariant` option and `styles.scss` tokens (background, accent, typography).
  - Provide preview info in README or screenshots under `pix/`.
- **Testing**
  - Behat: cover tag selection workflow, filter interactions, and admin CRUD.
  - PHPUnit: tag manager CRUD, cache invalidation, pluginfile access.

## Moodle-Specific Linter Considerations
Moodle has several unique patterns that cause issues with standard PHP linters and static analysis tools:

### Dynamic Class Loading & Missing Namespaces
- **Legacy naming convention**: Many Moodle core classes use `plugintype_pluginname_classname` naming (e.g., `backup_format_minimoodlewall_plugin`, `restore_moodleform`) without namespaces.
- **File naming pattern**: Classes in special directories use `.class.php` extension (backup/restore subsystems) which linters may not recognize.
- **Autoloading limitations**: Moodle's autoloader expects:
  - Namespaced classes in `classes/` directory follow PSR-4
  - Legacy classes in root or subdirectories require explicit `require_once()`
- **Mixed paradigms in same plugin**:
  - `lib.php`: Legacy global class `format_minimoodlewall` (NO namespace, extends `core_courseformat\base`)
  - `classes/`: Modern namespaced classes `format_minimoodlewall\tag_manager`
  - `backup/moodle2/*.class.php`: Legacy classes `backup_format_minimoodlewall_plugin` (NO namespace)
  - `classes/form/*.php`: Modern but extend legacy `\moodleform` which requires explicit `require_once($CFG->libdir.'/formslib.php')`

### PSR Compliance Challenges
- **PSR-4 autoloading**: Only applies to `classes/` directory; other locations require manual includes.
- **PSR-1/2 violations**: Moodle coding style differs from PSR standards:
  - Underscores in class names for legacy classes
  - 4-space indentation vs PSR-2's requirement
  - Different brace positioning in some contexts
- **Global dependencies**: `$CFG`, `$DB`, `$USER` globals are standard practice, triggering undefined variable warnings.
- **Dynamic file paths**: `require_once($CFG->dirroot . '/path/to/file.php')` patterns confuse static analyzers.

### Required Patterns to Avoid Linter Errors
1. **Always include base classes explicitly** in files that don't use autoloading:
   ```php
   require_once($CFG->dirroot . '/course/format/lib.php');  // For format base class
   require_once($CFG->libdir . '/formslib.php');            // For moodleform
   ```
2. **Namespace declaration placement**: Must come AFTER `defined('MOODLE_INTERNAL') || die();` but BEFORE any `require_once()` that references non-namespaced classes.
3. **Use statements**: Can reference both namespaced and global classes, but global classes don't need leading backslash if referenced after namespace declaration.
4. **Backup/restore classes**: Cannot use namespaces (Moodle's backup system expects specific legacy class names).

### Linter Configuration Recommendations
- **PHPStan/Psalm**: Requires custom bootstrap file to define globals (`$CFG`, `$DB`, etc.)
- **PHP_CodeSniffer**: Use `moodle` or `moodle-extra` rulesets, NOT standard PSR-2
- **IDEs (PHPStorm/VSCode)**: 
  - Add Moodle root as "library" to resolve dynamic includes
  - Configure to recognize `.class.php` files as PHP
  - Suppress "undefined global variable" warnings for Moodle globals

### Plugin-Specific Patterns
This plugin demonstrates the hybrid approach:
- `lib.php` (68 lines): Global `format_minimoodlewall` class, extends namespaced base
- `classes/tag_manager.php`: Fully namespaced, modern PSR-4
- `classes/form/tag_form.php`: Namespaced but extends global `\moodleform`, requires explicit include
- `backup/moodle2/*.class.php`: Legacy naming, NO namespaces, loaded by Moodle's backup API

## Guardrails for Future Agents
- Respect single-section assumption; avoid introducing multiple sections unless architecture is revisited.
- Never bypass tagset requirement—other logic assumes every course module can resolve a tag.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` helpers to avoid orphans.
- Update caches after any tag/tagset change; otherwise wall rendering will show stale logos/colors.
- Keep AI-facing docs (this file + README) updated when adding new options or data flows to minimize forgotten invariants.
- **Version Compatibility**: The plugin automatically detects Moodle version and uses appropriate classes/templates. The split is at Moodle 5.1 (branch 501) where MDL-86337 moved activity chooser to core_courseformat. Test changes in both 5.0 and 5.1+ environments.
- **Linter warnings**: Expect false positives for dynamic class loading, missing namespaces in legacy code, and global variable usage—these are intentional Moodle patterns, not bugs.

## Version Compatibility Details

### Moodle 5.1+ Implementation (MDL-86337)
- Activity chooser uses `format_minimoodlewall\output\courseformat\content\activitychooserbutton` class
- Extends `core_courseformat\output\local\content\activitychooserbutton` (moved from core_course in 5.1)
- Uses template: `format_minimoodlewall/local/content/activitychooserbutton.mustache`
- Data attributes: `data-section-id`, `data-sectionreturnid` (alongside legacy attributes)
- Hook support: Compatible with `\core_course\hook\before_activitychooserbutton_exported`
- Commit: MDL-86337 (August 18, 2025)

### Moodle 5.0 and Earlier Fallback
- Uses `cm.php` export method to inject tag data into activitychooserbutton context
- Uses legacy template: `format_minimoodlewall/tagchooserbutton.mustache` (via cm.mustache)
- Data attributes: `data-sectionnum`, `data-sectionreturnnum`
- Version detection: `$CFG->branch < 501`

### JavaScript Version Agnostic
- `amd/src/tagchooserbutton.js` supports both attribute sets
- Tries `data-section-id` first, falls back to `data-sectionnum`
- Passes both old and new parameters to maintain compatibility

## Quick File Map
- `lib.php` – course options, validation, navigation tweaks, pluginfile hook.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes.
- `tag_management.php` – admin UI controller.
- `classes/form/tag*_form.php` – mform definitions for UI.
- `classes/output/courseformat/content/activitychooserbutton.php` – **Moodle 5.0+ tag chooser button** (extends core class).
- `classes/output/courseformat/content/cm.php` – course module data provider (backward compatible with 4.x).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates.
- `templates/local/content/activitychooserbutton.mustache` – **Moodle 5.0+ tag chooser template**.
- `templates/local/content/cm.mustache` – course module template (uses core or custom chooser button).
- `templates/tagchooserbutton.mustache` – **Legacy Moodle 4.x tag chooser template**.
- `templates/local/content/*.mustache` – Mustache templates for wall, sections, cards.
- `styles.scss` / `styles.css` – wall styling + design variants.
- `amd/src/tagchooserbutton.js` – tag chooser modal handler (version-agnostic).
- `amd/` – placeholder for JS (filter bar, quick create, etc.).

## Open Questions / TODO Hooks
- Teacher-side workflow for assigning/changing tags per activity is not implemented.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.
- Additional design variants + documentation for recommended SVG sizing still pending.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
