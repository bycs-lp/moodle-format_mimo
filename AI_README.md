# format_minimoodlewall · AI README

> Working note for autonomous agents extending the Minimal Moodle Wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards.
- **Core concept**: Every course must select at least one *tag* via autocomplete multiselect; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Primary touch points**: `lib.php` (format logic + course options), `classes/tag_manager.php` (DB + files + cache), `tag_management.php` (admin UI), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (design variants), `amd/` (future JS helpers).

## Architecture Cheatsheet
- **Course format base** (`lib.php`)
  - Enforces single-section behavior, course index support, and hides section crumbs on activity pages.
  - Adds course options: `selectedtags` (PARAM_SEQUENCE comma-separated tag IDs, required, editable anytime), `enablefiltering`, `designvariant`.
  - `edit_form_validation()` forces at least one tag selection.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Tables: `*_tags` (standalone flat list), `*_cmtags` (one tag per `cm`).
  - File areas (`FILEAREA_CARDIMAGE`, `FILEAREA_FILTERIMAGE`) live in system context; served via `format_minimoodlewall_pluginfile()`.
  - `tag_manager` handles CRUD, cache invalidation, filemanager prep/saving, default palettes, and usage counts.
  - Key methods: `get_all_tags()`, `get_tags_for_course($courseid)` (filters by course's selectedtags option).
- **Description tags system** (`classes/description_tag_manager.php` + `classes/activity_description_manager.php`)
  - Tables: `*_desc_tags` (name + color), `*_actdesc` (activity type descriptions with optional `desctagid`).
  - Description tags provide visual categorization pills on activity type cards in chooser modal.
  - Activity descriptions cached with LEFT JOIN to include tag data (name, color) for performance.
  - Admin pages: `description_tags.php` (manage tags), `activity_descriptions.php` (assign tags to activity types).
- **Admin UX** (`settings.php`, `tag_management.php`, `classes/form/*`)
  - External admin page for tag sets/tags with SVG previews and accent swatches.
  - Only admins (`moodle/site:config`) can manage tags; links exposed under Site administration › Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + design variants (`classic`, `light`, `dark`).
- **Caching** (`cache/definitions.php`)
  - `tagconfigurations`: all tags metadata, keyed per course (`course_tags_{courseid}`).
  - `activitytagmappings`: cm→tag lookup.
  - `activity_descriptions`: cached activity descriptions with tag data (LEFT JOIN on desc_tags).
  - Clear via `tag_manager::clear_course_tags_cache($courseid)` or `activity_description_manager::clear_cache()` whenever data changes.

## Workflows & Entry Points
1. **Course creation**
   - User selects Minimal Moodle Wall format.
   - Must select at least one tag via autocomplete multiselect (can be changed later if needed).
2. **Tag management**
   - Admin page handles create/edit/delete for tags, including SVG uploads.
   - SVGs must be `.svg` (enforced via filemanager options).
   - Tags are global — all courses share the same tag pool; each course selects which tags to use.
3. **Course editing**
  - Teachers see a tag-based activity chooser: clicking the "+" button reveals a dropdown of configured tags.
  - Selecting a tag opens a modal with three options: two quick-create shortcuts (pre-configured activity types) and a link to the full activity chooser.
  - **Activity type cards** in the modal display:
    - Activity icon with purpose-based border color
    - Activity name and description
    - **Description tag pill** (if assigned): appears on top-right, slightly overlapping edge, with custom background color from database
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
- Never bypass selectedtags requirement—every course must have at least one tag selected for the wall to function properly.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` helpers to avoid orphans.
- Update caches after any tag change; otherwise wall rendering will show stale logos/colors. Use `clear_course_tags_cache($courseid)` for course-specific cache invalidation.
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
- `lib.php` – course options (selectedtags autocomplete), validation, navigation tweaks, pluginfile hook.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes. Key methods: `get_all_tags()`, `get_tags_for_course($courseid)`.
- `classes/description_tag_manager.php` – description tag CRUD for activity type categorization.
- `classes/activity_description_manager.php` – activity description CRUD with tag assignment, cached with LEFT JOIN.
- `tag_management.php` – admin UI controller for tags (flat list, no tagsets).
- `description_tags.php` – admin UI for managing description tags (name + hex color).
- `activity_descriptions.php` – admin UI for editing activity type descriptions and assigning description tags.
- `classes/form/tag_form.php` – mform definition for tag create/edit UI.
- `classes/form/description_tag_form.php` – mform for description tag create/edit with color validation.
- `classes/form/activity_descriptions_form.php` – mform with dropdowns for assigning tags to activity types.
- `classes/external/get_tags.php` – webservice for fetching tags by course ID (returns tags matching course's selectedtags option).
- `classes/output/courseformat/content/activitychooserbutton.php` – **Moodle 5.0+ tag chooser button** (extends core class).
- `classes/output/courseformat/content/cm.php` – course module data provider (backward compatible with 4.x).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates.
- `classes/external/get_activity_descriptions.php` – webservice for fetching activity descriptions with tag data for modal.
- `templates/local/content/activitychooserbutton.mustache` – **Moodle 5.0+ tag chooser template**.
- `templates/local/content/cm.mustache` – course module template (uses core or custom chooser button).
- `templates/tagchooserbutton.mustache` – **Legacy Moodle 4.x tag chooser template**.
- `templates/activitytype_chooser_modal.mustache` – modal body for activity type selection.
- `templates/activitytype_card.mustache` – activity type card with optional description tag pill.
- `templates/description_tags_list.mustache` – table view for description tags management page.
- `templates/local/content/*.mustache` – Mustache templates for wall, sections, cards.
- `styles.scss` / `styles.css` – wall styling + design variants + activity card styles + description tag pill styling.
- `amd/src/tagchooserbutton.js` – tag chooser modal handler with activity description fetching (version-agnostic).
- `amd/` – placeholder for JS (filter bar, quick create, etc.).
- `tests/behat/activity_descriptions.feature` – Behat tests for description tags and activity descriptions.
- `tests/description_tag_manager_test.php` – PHPUnit tests for description tag manager.
- `tests/generator/` – data generators for Behat tests (includes description_tag and activity_description).

## Open Questions / TODO Hooks
- Teacher-side workflow for assigning/changing tags per activity is not implemented.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.
- Additional design variants + documentation for recommended SVG sizing still pending.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
