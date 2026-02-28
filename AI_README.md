# format_minimoodlewall · AI README

> Working note for autonomous agents extending the Minimal Moodle Wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards. Optionally supports multi-section mode where each section has its own wall.
- **Core concept**: Courses use an *activity profile* that determines which *tags* are active and how they appear; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Multi-section mode**: Toggled per-course via `enablemultisection` option. When ON: course index activates, teachers can add/rename/reorder sections, each section displays its own wall with scoped filter bar and completion counts, navigation is one-section-at-a-time. When OFF (default): classic single-wall behavior with all activities in section 0.
- **Primary touch points**: `lib.php` (format logic + course options), `classes/tag_manager.php` (tag DB + files + cache), `classes/profile_manager.php` (profile CRUD + per-tag overrides), `classes/style_manager.php` (style variants + per-tag images), `tag_management.php` (admin UI), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (style variants), `amd/` (JS helpers).

## Architecture Cheatsheet
- **Course format base** (`lib.php`)
  - Default: single-section behavior (section 0 only), course index disabled, section crumbs hidden on activity pages.
  - **Multi-section mode** (`enablemultisection` course option): when ON, all methods become section-aware:
    - `is_multisection_enabled()` — helper that reads the course option.
    - `get_sectionnum()` returns `0` in single-section mode; returns `$this->singlesection` (nullable, set by `format.php`) in multi-section mode.
    - `is_section_visible()` — single-section: only section 0 + delegated; multi-section: delegates to parent (all non-orphan sections visible).
    - `uses_course_index()` — returns `true` in multi-section mode, `false` otherwise.
    - `get_view_url()` — single-section: plain course URL; multi-section: section-specific URL via `/course/section.php` for navigation.
    - `extend_course_navigation()` — single-section: hides section breadcrumbs; multi-section: shows section breadcrumbs + expands selected section in navigation.
  - Adds course options: `enablemultisection` (PARAM_BOOL, default `0`), `activityprofile` (PARAM_ALPHANUMEXT, selects which profile to use; default `'classic'`), `enablefiltering`, `wallcolor` (PARAM_ALPHANUMEXT, default `'default'`; "default" falls back to the style's background, other values — `green`, `white`, `dark` — override only the wall background via CSS class `mmw-wallcolor-{value}`), `enablecompletionstars`, `distractionfree`.
  - Course settings form shows a read-only tag preview below the activity profile dropdown, rendered via `form_tag_preview.mustache`. Tags update dynamically when the profile is changed (via `profile_image_switcher.js`).
  - **Module form callbacks** (legacy `get_plugins_with_function` pattern — no PSR-14 hooks exist for `moodleform_mod`):
    - `format_minimoodlewall_coursemodule_standard_elements()` — injects a tag `select` dropdown into every module edit form (only for minimoodlewall courses). Pre-selects current tag on edit, or pending session tag on create.
    - `format_minimoodlewall_coursemodule_edit_post_actions()` — persists the tag selection via `tag_manager::assign_tag_to_cm()` (upsert) or `remove_cm_tag()` on save. Returns `$data` for callback chaining.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Table: `*_tags` (name, bgcolor hex, imgplacement center|lower, cardimage, filterimage, activitytype1-3, sortorder).
  - Table: `*_cmtags` (one tag per `cm`, unique cmid).
  - File areas (`FILEAREA_CARDIMAGE = 'tagcard'`, `FILEAREA_FILTERIMAGE = 'tagfilter'`) in system context; served via `format_minimoodlewall_pluginfile()`.
  - Key methods: `create_tag($name, ...)`, `get_all_tags()`, `get_tags_for_course($courseid)` (returns profile-filtered tags), `assign_tag_to_cm($cmid, $tagid)`, `get_cm_tag($cmid)`.
  - `get_tags_for_course()` reads the course's `activityprofile` option, resolves tags through `profile_manager::resolve_tags_for_profile()`, and returns only enabled tags with profile overrides applied.
  - Cache invalidation: `clear_tag_cache()`, `clear_mapping_cache()`, `clear_course_tags_cache($courseid)`.
- **Profile system** (`classes/profile_manager.php`)
  - Table: `*_profiles` (name unique, displayname, sortorder).
  - Table: `*_profile_tags` (tagid+profileid unique; nullable overrides for name, bgcolor, activitytype1-3; `enabled` flag).
  - Key methods: `create_profile($name, $displayname)`, `get_profile_by_name($name)`, `get_or_create_profile_tag($tagid, $profileid)`, `update_profile_tag($id, $data)`, `resolve_tags_for_profile($tags, $profileid, $onlyenabled)`, `resolve_tag_for_profile($tag, $profileid)`.
  - Profiles determine which tags are active for a course and can override tag names, colors, and activity types per profile.
- **Style system** (`classes/style_manager.php`)
  - Table: `*_styles` (name unique, displayname, sortorder).
  - Table: `*_tag_images` (tagid+styleid unique, cardimage, filterimage filenames).
  - File areas: `FILEAREA_STYLE_CARDIMAGE = 'styletagcard'`, `FILEAREA_STYLE_FILTERIMAGE = 'styletagfilter'`.
  - Key methods: `create_style($name, $displayname)`, `get_all_styles()`, `get_style_by_name($name)`, `get_or_create_tag_image($tagid, $styleid)`, `get_tag_image_for_style($tagid, $styleid)`.
  - Styles allow different card/filter images per tag per visual theme.
- **Description tags system** (`classes/description_tag_manager.php` + `classes/activity_description_manager.php`)
  - Tables: `*_desc_tags` (name + color), `*_actdesc` (activity type descriptions with optional `desctagid`).
  - Description tags provide visual categorization pills on activity type cards in chooser modal.
  - Activity descriptions cached with LEFT JOIN to include tag data (name, color) for performance.
  - Admin pages: `description_tags.php` (manage tags), `activity_descriptions.php` (assign tags to activity types).
- **Event observers** (`classes/observer.php` + `db/events.php`)
  - `course_module_created`: Auto-assign pending tag from session (guided creation flow) + apply minimoodlewall completion default overrides (see below).
  - `course_module_deleted`: Delete cmtag record for the deleted module + clear cache.
  - `course_deleted`: Delete all orphaned cmtag records (cmid NOT IN course_modules) + clear cache.
- **Completion defaults override** (`classes/completion_defaults_manager.php` + `completion_defaults.php`)
  - Table: `*_compdefs` (module unique; completion, completionview, completionusegrade, completionpassgrade, completionexpected, customrules JSON).
  - When a module is created in a minimoodlewall course and its completion matches Moodle's core defaults (meaning the teacher did not customize), the observer silently replaces completion with the minimoodlewall override.
  - Comparison logic: checks core fields (completion, completionview, completionpassgrade, completiongradeitemnumber↔completionusegrade) and custom rules on the module instance table.
  - Override applies to both `course_modules` (core fields) and the module instance table (custom rules from JSON blob).
  - Admin page (`completion_defaults.php`): lists all module types, allows editing per-type completion defaults using core's `defaultedit_form`.
  - Key methods: `get_default($moduleid)`, `save_default($moduleid, $data)`, `delete_default($moduleid)`, `matches_core_defaults($cm, $coredefaults, $modname)`, `apply_defaults($cm, $mmwdefaults, $modname)`, `pack_form_data($formdata, $suffix)`.
- **Admin UX** (`settings.php`, `tag_management.php`, `style_management.php`, `classes/form/*`)
  - Tag management: Accordion-based UI with tagsets as expandable sections, tags as forms within. `data-tagset-name` attribute for Behat targeting.
  - Style management: Tab-based style image management per tag.
  - Only admins (`moodle/site:config`) can manage tags/tagsets/styles; links exposed under Site administration > Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + style variants (`classic`, `light`, `dark`).
- **Caching** (`db/caches.php`)
  - `tagconfigurations`: all tags metadata, keyed per course (`course_tags_{courseid}`).
  - `activitytagmappings`: cm→tag lookup.
  - `activity_descriptions`: cached activity descriptions with tag data (LEFT JOIN on desc_tags).
  - Clear via `tag_manager::clear_course_tags_cache($courseid)` or `activity_description_manager::clear_cache()` whenever data changes.

## Backup & Restore Architecture
- **Backup** (`backup/moodle2/backup_format_minimoodlewall_plugin.class.php`)
  - Course-level: Backs up styles (only those referenced by course tags), tags (all fields incl. bgcolor, imgplacement, activitytype3), profiles, profile_tags, and tag_images. File annotations for all image file areas.
  - Module-level: Backs up cmtag records (cmid → tagid mapping).
  - XML tree: `pluginwrapper → mmw_styles → mmw_style` + `pluginwrapper → mmw_tags → mmw_tag → mmw_tag_images → mmw_tag_image`.
- **Restore** (`backup/moodle2/restore_format_minimoodlewall_plugin.class.php`)
  - **Same-instance**: Reuses existing tags/styles/tag_images/profiles by name/unique-combo (no duplicates).
  - **Cross-instance**: Creates new records when matching names don't exist.
  - ID mapping: Sets `format_minimoodlewall_tag`, `format_minimoodlewall_style`, `format_minimoodlewall_tag_image` mappings.
  - `after_execute_course()`: Restores file areas.
  - `after_restore_course()`: Clears all caches.

## Workflows & Entry Points
1. **Course creation**
   - User selects Minimal Moodle Wall format.
   - Selects an activity profile (determines which tags are active and how they appear).
   - A read-only tag preview shows the active tags for the selected profile.
   - Only section 0 is created by default (single-section mode). If `enablemultisection` is ON, teachers can add more sections.
2. **Tag & profile management**
   - Admin page (`tag_management.php`) manages tags with accordion UI.
   - Tags have forms for name, color, images, activity types.
   - Profiles determine which tags are visible/active per course.
   - Deleting a tag cascades to profile_tags and cmtags.
3. **Style management**
   - Admin page (`style_management.php`) manages style variants.
   - Per-tag images for each style variant via `tag_images` table.
4. **Course editing**
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
5. **Learner view**
   - **Single-section mode**: Wall shows all activities from section 0 in a responsive grid.
   - **Multi-section mode**: One section visible at a time; navigate via course index. Each section has its own wall, filter bar, and completion counts scoped to that section's activities.
   - Optional filter bar (enabled via course option) lists tags with usage counts; clicking filters the visible cards.
6. **Course index drawer**
   - Disabled in single-section mode (`uses_course_index()` returns `false`).
   - Enabled in multi-section mode — shows all sections for navigation.

## Common Extension Tasks
- **Add teacher UI for tagging**
  - Introduce cm-level setting + form element (probably in `classes/courseformat/local/...` or observer hook).
  - Update `tag_manager::assign_tag_to_cm()` and invalidate caches.
- **Enhance filtering UX**
  - Extend `amd/src/*` to add reactive filtering, track active tag, and animate card visibility.
  - Expose filter data via `section.mustache` context.
- **Style variants**
  - Expand `stylevariant` option and `styles.scss` tokens (background, accent, typography).
  - Upload style-specific tag images via `style_manager`.
- **Testing**
  - Behat: cover tag selection workflow, filter interactions, and admin CRUD.
  - PHPUnit: tag/tagset/style CRUD, backup/restore, observer handlers, cache invalidation.

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
  - `classes/`: Modern namespaced classes `format_minimoodlewall\tag_manager`, `tagset_manager`, `style_manager`
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
- `classes/profile_manager.php`: Fully namespaced, profile CRUD and tag resolution
- `classes/style_manager.php`: Fully namespaced, manages styles + tag_images
- `classes/form/tag_form.php`: Namespaced but extends global `\moodleform`, requires explicit include
- `backup/moodle2/*.class.php`: Legacy naming, NO namespaces, loaded by Moodle's backup API

## Guardrails for Future Agents
- **Single-section mode** (default): all activities live in section 0. `get_sectionnum()` returns 0; `is_section_visible()` hides all non-0, non-delegated sections. Don't add activities to other sections unless multi-section is enabled.
- **Multi-section mode** (`enablemultisection`): each section is its own wall. `get_sectionnum()` returns the currently viewed section (or `null` for all). Course index is active. Filter bars, completion counts, and tag usage counts are scoped per-section. Drag-drop is within-section only.
- When toggling multi-section OFF, activities in sections >0 become hidden. There is no auto-migration — only a help text warning.
- The course index is disabled in single-section mode. Do not re-enable it without also enabling multi-section.
- Never bypass the profile system — `get_tags_for_course()` filters tags through the course's activity profile. All tags are active by default; profiles can disable individual tags.
- Every course should have a valid `activityprofile` option — defaults to `'classic'`.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` / `style_manager` / `profile_manager` helpers to avoid orphans.
- Update caches after any tag/profile/style change; otherwise wall rendering will show stale logos/colors. Use `clear_course_tags_cache($courseid)` for course-specific cache invalidation.
- Tags and profiles are **global** — shared across all courses; courses reference profiles via the `activityprofile` format option.
- Backup/restore reuses by name on same instance. Don't create duplicate-prevention logic elsewhere.
- Observer cleanup: `course_module_deleted` and `course_deleted` handle orphan cmtag records. Don't add manual cleanup in other code paths.
- `tag_delete_confirm.js` uses event capturing (`addEventListener('click', handler, true)`) to intercept before `stopPropagation` on accordion buttons. Maintain this pattern.
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
- `lib.php` – course options (enablemultisection, activityprofile, enablefiltering, wallcolor, enablecompletionstars, distractionfree), `is_multisection_enabled()` helper, conditional `get_sectionnum()` / `is_section_visible()` / `uses_course_index()` / `get_view_url()` / `extend_course_navigation()`, read-only tag preview in form, pluginfile hook, **module form callbacks** (`coursemodule_standard_elements` tag dropdown + `coursemodule_edit_post_actions` tag persistence).
- `format.php` – entry point; branches on `is_multisection_enabled()`: multi-section uses `$displaysection` from core, single-section redirects non-0 and locks to section 0.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes. Key methods: `get_all_tags()`, `get_tags_for_course($courseid)` (profile-filtered), `get_tag_usage_counts($courseid, $tagids, $sectionid)` (optional section scoping).
- `classes/profile_manager.php` – profile CRUD, per-tag profile overrides (name, bgcolor, activity types, enabled), tag resolution. Key methods: `resolve_tags_for_profile()`, `resolve_tag_for_profile()`, `get_or_create_profile_tag()`.
- `classes/style_manager.php` – style CRUD, per-tag style images (tag_images table), file areas for style card/filter images.
- `classes/description_tag_manager.php` – description tag CRUD for activity type categorization.
- `classes/activity_description_manager.php` – activity description CRUD with tag assignment, cached with LEFT JOIN.
- `classes/observer.php` – event handlers: auto-tag on module create, **completion default override on module create**, cleanup on module/course delete.
- `classes/completion_defaults_manager.php` – CRUD for minimoodlewall completion defaults (compdefs table), comparison with core defaults, application to course modules.
- `classes/privacy/` – Privacy API provider.
- `completion_defaults.php` – admin page for managing per-module-type completion default overrides.
- `tag_management.php` – admin UI controller for tagsets (accordion) and tags.
- `style_management.php` – admin UI for style variants and per-tag images.
- `description_tags.php` – admin UI for managing description tags (name + hex color).
- `activity_descriptions.php` – admin UI for editing activity type descriptions and assigning description tags.
- `classes/form/tag_form.php` – mform definition for tag create/edit (name, color, images, activity types).
- `classes/form/tagset_form.php` – mform for tagset create/edit.
- `classes/form/description_tag_form.php` – mform for description tag create/edit with color validation.
- `classes/form/completion_defaults_form.php` – mform extending core's `defaultedit_form` for minimoodlewall completion overrides.
- `classes/form/activity_descriptions_form.php` – mform with dropdowns for assigning tags to activity types.
- `classes/external/get_tags.php` – webservice for fetching tags by course ID (returns profile-filtered tags).
- `classes/external/get_activity_descriptions.php` – webservice for fetching activity descriptions with tag data for modal.
- `classes/output/courseformat/content/activitychooserbutton.php` – **Moodle 5.1+ tag chooser button** (extends core class).
- `classes/output/courseformat/content/cm.php` – course module data provider (backward compatible with 4.x).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates. `cmitem.php` resolves tags through profile for card rendering.
- `templates/tag_management.mustache` – accordion-based tag admin UI.
- `templates/form_tag_preview.mustache` – read-only tag preview for course settings form (shows active tags for selected profile).
- `templates/style_management.mustache` – style admin with per-tag image tabs.
- `templates/local/content/activitychooserbutton.mustache` – **Moodle 5.1+ tag chooser template**.
- `templates/local/content/cm.mustache` – course module template (uses core or custom chooser button).
- `templates/tagchooserbutton.mustache` – **Legacy Moodle 4.x tag chooser template**.
- `templates/activitytype_chooser_modal.mustache` – modal body for activity type selection.
- `templates/activitytype_card.mustache` – activity type card with optional description tag pill.
- `templates/description_tags_list.mustache` – table view for description tags management page.
- `styles.scss` / `styles.css` – wall styling + style variants + activity card styles + description tag pill styling.
- `amd/src/tagchooserbutton.js` – tag chooser modal handler with activity description fetching (version-agnostic).
- `amd/src/tag_delete_confirm.js` – delete confirmation modals for tags (event capturing phase).
- `amd/src/tag_filter.js` – client-side tag filtering.
- `amd/src/activity_pagination.js` – responsive pagination with swipe.
- `amd/src/activity_dragdrop.js` – drag and drop reordering.
- `amd/src/profile_image_switcher.js` – swaps tag images/names/visibility in course form when activity profile changes.
- `amd/src/style_delete_confirm.js` – style deletion confirmation modal.
- `amd/src/style_image_switcher.js` – style image tab switching.
- `amd/src/description_tag_management.js` – description tag admin helpers.
- `amd/src/distraction_free.js` – distraction-free mode toggle.
- `backup/moodle2/backup_format_minimoodlewall_plugin.class.php` – backup handler (tagsets, tags, styles, tag_images, cmtags, files).
- `backup/moodle2/restore_format_minimoodlewall_plugin.class.php` – restore handler (reuse-by-name, ID mapping, format option remapping).
- `db/install.xml` – 9 tables: tags, cmtags, styles, tag_images, desc_tags, actdesc, profiles, profile_tags, compdefs.
- `db/install.php` – creates default tags, default styles, and default profiles on install.
- `db/upgrade.php` – migration steps including profile introduction, selectedtags removal, and completion defaults table.
- `db/events.php` – observer registrations (module created/deleted, course deleted).
- `db/hooks.php` – hook registrations.
- `db/services.php` – web service definitions.
- `db/caches.php` – cache definitions (tagconfigurations, activitytagmappings, activity_descriptions).
- `tests/behat/tag_management.feature` – 6 scenarios for tag/tagset CRUD (create, edit, delete).
- `tests/behat/activity_tag_edit.feature` – 4 scenarios for changing/removing tags on activities via the module edit form.
- `tests/behat/style_variants.feature` – 2 scenarios for style variant selection during course creation.
- `tests/behat/style_management.feature` – style admin scenarios.
- `tests/tag_manager_test.php` – tag CRUD, assignment, caching, profile-based filtering.
- `tests/backup_restore_test.php` – 4 tests: basic cmtag restore, tag field preservation, style/tag_image restore, profile restore.
- `tests/observer_test.php` – 6+ tests: auto-tag, no-assignment scenarios, rejection (invalid tag, profile-disabled tag), module deletion cleanup, course deletion cleanup.

## Open Questions / TODO Hooks
- ~~Teacher-side workflow for assigning/changing tags per activity~~ — **Implemented** via legacy `coursemodule_standard_elements` / `coursemodule_edit_post_actions` callbacks in `lib.php`. Teachers see a tag dropdown in the module edit form.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.
- Additional style variants + documentation for recommended SVG sizing still pending.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
