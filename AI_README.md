# format_minimoodlewall · AI README

> Working note for autonomous agents extending the Minimal Moodle Wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards.
- **Core concept**: Every course selects a *tagset* and one or more *tags* from it; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Primary touch points**: `lib.php` (format logic + course options), `classes/tag_manager.php` (tag DB + files + cache), `classes/tagset_manager.php` (tagset CRUD + cascade), `classes/design_manager.php` (design variants + per-tag images), `tag_management.php` (admin UI), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (design variants), `amd/` (JS helpers).

## Architecture Cheatsheet
- **Course format base** (`lib.php`)
  - Enforces single-section behavior, course index support, and hides section crumbs on activity pages.
  - Adds course options: `tagsetid` (PARAM_INT, selects which tagset to use), `selectedtags` (PARAM_SEQUENCE comma-separated tag IDs, required), `enablefiltering`, `designvariant`.
  - `edit_form_validation()` forces at least one tag selection.
- **Tagset domain model** (`classes/tagset_manager.php`)
  - Table: `*_tagsets` (name unique, description, sortorder).
  - Key methods: `create_tagset($name, $description)`, `get_all_tagsets()`, `get_tagset($id)`, `update_tagset($id, $data)`, `delete_tagset($id)` (cascade-deletes all tags via `tag_manager::delete_tag()`).
  - Caching via `clear_tagset_cache()`.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Table: `*_tags` (tagsetid FK, name, bgcolor hex, imgplacement center|lower, cardimage, filterimage, activitytype1-3, sortorder).
  - Table: `*_cmtags` (one tag per `cm`, unique cmid).
  - File areas (`FILEAREA_CARDIMAGE = 'tagcard'`, `FILEAREA_FILTERIMAGE = 'tagfilter'`) in system context; served via `format_minimoodlewall_pluginfile()`.
  - Key methods: `create_tag($tagsetid, $name, ...)`, `get_all_tags()`, `get_tags_for_course($courseid)`, `get_tags_by_tagset($tagsetid)`, `assign_tag_to_cm($cmid, $tagid)`, `get_cm_tag($cmid)`.
  - Cache invalidation: `clear_tag_cache()`, `clear_mapping_cache()`, `clear_course_tags_cache($courseid)`.
- **Design system** (`classes/design_manager.php`)
  - Table: `*_designs` (name unique, displayname, sortorder).
  - Table: `*_tag_images` (tagid+designid unique, cardimage, filterimage filenames).
  - File areas: `FILEAREA_DESIGN_CARDIMAGE = 'designtagcard'`, `FILEAREA_DESIGN_FILTERIMAGE = 'designtagfilter'`.
  - Key methods: `create_design($name, $displayname)`, `get_all_designs()`, `get_design_by_name($name)`, `get_or_create_tag_image($tagid, $designid)`, `get_tag_image_for_design($tagid, $designid)`.
  - Designs allow different card/filter images per tag per visual theme.
- **Description tags system** (`classes/description_tag_manager.php` + `classes/activity_description_manager.php`)
  - Tables: `*_desc_tags` (name + color), `*_actdesc` (activity type descriptions with optional `desctagid`).
  - Description tags provide visual categorization pills on activity type cards in chooser modal.
  - Activity descriptions cached with LEFT JOIN to include tag data (name, color) for performance.
  - Admin pages: `description_tags.php` (manage tags), `activity_descriptions.php` (assign tags to activity types).
- **Event observers** (`classes/observer.php` + `db/events.php`)
  - `course_module_created`: Auto-assign pending tag from session (guided creation flow).
  - `course_module_deleted`: Delete cmtag record for the deleted module + clear cache.
  - `course_deleted`: Delete all orphaned cmtag records (cmid NOT IN course_modules) + clear cache.
- **Admin UX** (`settings.php`, `tag_management.php`, `design_management.php`, `classes/form/*`)
  - Tag management: Accordion-based UI with tagsets as expandable sections, tags as forms within. `data-tagset-name` attribute for Behat targeting.
  - Design management: Tab-based design image management per tag.
  - Only admins (`moodle/site:config`) can manage tags/tagsets/designs; links exposed under Site administration > Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + design variants (`classic`, `light`, `dark`).
- **Caching** (`db/caches.php`)
  - `tagconfigurations`: all tags metadata, keyed per course (`course_tags_{courseid}`).
  - `activitytagmappings`: cm→tag lookup.
  - `activity_descriptions`: cached activity descriptions with tag data (LEFT JOIN on desc_tags).
  - Clear via `tag_manager::clear_course_tags_cache($courseid)` or `activity_description_manager::clear_cache()` whenever data changes.

## Backup & Restore Architecture
- **Backup** (`backup/moodle2/backup_format_minimoodlewall_plugin.class.php`)
  - Course-level: Backs up designs (only those referenced by course tags), tagsets, tags (all fields incl. bgcolor, imgplacement, activitytype3), and tag_images. File annotations for all image file areas.
  - Module-level: Backs up cmtag records (cmid → tagid mapping).
  - XML tree: `pluginwrapper → mmw_designs → mmw_design` + `pluginwrapper → mmw_tagsets → mmw_tagset → mmw_tags → mmw_tag → mmw_tag_images → mmw_tag_image`.
- **Restore** (`backup/moodle2/restore_format_minimoodlewall_plugin.class.php`)
  - **Same-instance**: Reuses existing tagsets/tags/designs/tag_images by name/unique-combo (no duplicates).
  - **Cross-instance**: Creates new records when matching names don't exist.
  - ID mapping: Sets `format_minimoodlewall_tagset`, `format_minimoodlewall_tag`, `format_minimoodlewall_design`, `format_minimoodlewall_tag_image` mappings.
  - `after_execute_course()`: Restores file areas + updates `tagsetid` and `selectedtags` course format options with remapped IDs.
  - `after_restore_course()`: Clears all caches.

## Workflows & Entry Points
1. **Course creation**
   - User selects Minimal Moodle Wall format.
   - Must select a tagset and at least one tag from it.
2. **Tag & tagset management**
   - Admin page (`tag_management.php`) uses accordion UI for tagsets.
   - Tags within each tagset have forms for name, color, images, activity types.
   - Deleting a tagset cascade-deletes all its tags (via `tagset_manager::delete_tagset()`).
   - `tag_delete_confirm.js` uses event capturing phase to intercept clicks before `stopPropagation` on accordion buttons.
3. **Design management**
   - Admin page (`design_management.php`) manages design variants.
   - Per-tag images for each design variant via `tag_images` table.
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
   - Wall shows all activities from section 0 in a responsive grid.
   - Optional filter bar (enabled via course option) lists tags with usage counts; clicking filters the visible cards.

## Common Extension Tasks
- **Add teacher UI for tagging**
  - Introduce cm-level setting + form element (probably in `classes/courseformat/local/...` or observer hook).
  - Update `tag_manager::assign_tag_to_cm()` and invalidate caches.
- **Enhance filtering UX**
  - Extend `amd/src/*` to add reactive filtering, track active tag, and animate card visibility.
  - Expose filter data via `section.mustache` context.
- **Design variants**
  - Expand `designvariant` option and `styles.scss` tokens (background, accent, typography).
  - Upload design-specific tag images via `design_manager`.
- **Testing**
  - Behat: cover tag selection workflow, filter interactions, and admin CRUD.
  - PHPUnit: tag/tagset/design CRUD, backup/restore, observer handlers, cache invalidation.

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
  - `classes/`: Modern namespaced classes `format_minimoodlewall\tag_manager`, `tagset_manager`, `design_manager`
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
- `classes/tagset_manager.php`: Fully namespaced, cascade-deletes tags
- `classes/design_manager.php`: Fully namespaced, manages designs + tag_images
- `classes/form/tag_form.php`: Namespaced but extends global `\moodleform`, requires explicit include
- `backup/moodle2/*.class.php`: Legacy naming, NO namespaces, loaded by Moodle's backup API

## Guardrails for Future Agents
- Respect single-section assumption; avoid introducing multiple sections unless architecture is revisited.
- Never bypass selectedtags requirement—every course must have at least one tag selected for the wall to function properly.
- Every course must have a valid `tagsetid` — the upgrade step (Step 8 in `db/upgrade.php`) sets this on existing courses; new courses get it from the course edit form.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` / `design_manager` helpers to avoid orphans.
- Update caches after any tag/tagset/design change; otherwise wall rendering will show stale logos/colors. Use `clear_course_tags_cache($courseid)` for course-specific cache invalidation.
- Tagsets/tags/designs are **global** — shared across all courses; courses reference them via format options.
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
- `lib.php` – course options (tagsetid, selectedtags autocomplete), validation, navigation tweaks, pluginfile hook.
- `classes/tagset_manager.php` – tagset CRUD, cascade delete to tags, cache management.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes. Key methods: `get_all_tags()`, `get_tags_for_course($courseid)`, `get_tags_by_tagset($tagsetid)`.
- `classes/design_manager.php` – design CRUD, per-tag design images (tag_images table), file areas for design card/filter images.
- `classes/description_tag_manager.php` – description tag CRUD for activity type categorization.
- `classes/activity_description_manager.php` – activity description CRUD with tag assignment, cached with LEFT JOIN.
- `classes/observer.php` – event handlers: auto-tag on module create, cleanup on module/course delete.
- `classes/privacy/` – Privacy API provider.
- `tag_management.php` – admin UI controller for tagsets (accordion) and tags.
- `design_management.php` – admin UI for design variants and per-tag images.
- `description_tags.php` – admin UI for managing description tags (name + hex color).
- `activity_descriptions.php` – admin UI for editing activity type descriptions and assigning description tags.
- `classes/form/tag_form.php` – mform definition for tag create/edit (name, color, images, activity types).
- `classes/form/tagset_form.php` – mform for tagset create/edit.
- `classes/form/description_tag_form.php` – mform for description tag create/edit with color validation.
- `classes/form/activity_descriptions_form.php` – mform with dropdowns for assigning tags to activity types.
- `classes/external/get_tags.php` – webservice for fetching tags by course ID (returns tags matching course's selectedtags option).
- `classes/external/get_activity_descriptions.php` – webservice for fetching activity descriptions with tag data for modal.
- `classes/output/courseformat/content/activitychooserbutton.php` – **Moodle 5.1+ tag chooser button** (extends core class).
- `classes/output/courseformat/content/cm.php` – course module data provider (backward compatible with 4.x).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates.
- `templates/tag_management.mustache` – accordion-based tagset/tag admin UI with `data-tagset-name` attributes.
- `templates/design_management.mustache` – design admin with per-tag image tabs.
- `templates/local/content/activitychooserbutton.mustache` – **Moodle 5.1+ tag chooser template**.
- `templates/local/content/cm.mustache` – course module template (uses core or custom chooser button).
- `templates/tagchooserbutton.mustache` – **Legacy Moodle 4.x tag chooser template**.
- `templates/activitytype_chooser_modal.mustache` – modal body for activity type selection.
- `templates/activitytype_card.mustache` – activity type card with optional description tag pill.
- `templates/description_tags_list.mustache` – table view for description tags management page.
- `styles.scss` / `styles.css` – wall styling + design variants + activity card styles + description tag pill styling.
- `amd/src/tagchooserbutton.js` – tag chooser modal handler with activity description fetching (version-agnostic).
- `amd/src/tag_delete_confirm.js` – delete confirmation modals for tags and tagsets (event capturing phase).
- `amd/src/tag_filter.js` – client-side tag filtering.
- `amd/src/tagset_tag_filter.js` – tagset-aware filter grouping.
- `amd/src/activity_pagination.js` – responsive pagination with swipe.
- `amd/src/activity_dragdrop.js` – drag and drop reordering.
- `amd/src/tag_checkbox_sync.js` – tag selection checkbox sync.
- `amd/src/design_delete_confirm.js` – design deletion confirmation modal.
- `amd/src/design_image_switcher.js` – design image tab switching.
- `amd/src/description_tag_management.js` – description tag admin helpers.
- `amd/src/distraction_free.js` – distraction-free mode toggle.
- `backup/moodle2/backup_format_minimoodlewall_plugin.class.php` – backup handler (tagsets, tags, designs, tag_images, cmtags, files).
- `backup/moodle2/restore_format_minimoodlewall_plugin.class.php` – restore handler (reuse-by-name, ID mapping, format option remapping).
- `db/install.xml` – 7 tables: tagsets, tags, cmtags, designs, tag_images, desc_tags, actdesc.
- `db/install.php` – creates default tagset, default tags, and default designs on install.
- `db/upgrade.php` – migration steps including tagset introduction (Step 8: sets tagsetid on existing courses).
- `db/events.php` – observer registrations (module created/deleted, course deleted).
- `db/hooks.php` – hook registrations.
- `db/services.php` – web service definitions.
- `db/caches.php` – cache definitions (tagconfigurations, activitytagmappings, activity_descriptions).
- `tests/behat/tag_management.feature` – 6 scenarios for tag/tagset CRUD (create, edit, delete).
- `tests/behat/design_variants.feature` – 2 scenarios for design variant selection during course creation.
- `tests/behat/design_management.feature` – design admin scenarios.
- `tests/tag_manager_test.php` – tag CRUD, assignment, caching, defaults.
- `tests/tagset_manager_test.php` – tagset CRUD, cascade delete, caching.
- `tests/design_manager_test.php` – design CRUD, tag_images.
- `tests/backup_restore_test.php` – 4 tests: basic cmtag restore, tag field preservation, design/tag_image restore, tagsetid restore.
- `tests/observer_test.php` – 6+ tests: auto-tag, no-assignment scenarios, rejection, module deletion cleanup, course deletion cleanup.

## Open Questions / TODO Hooks
- Teacher-side workflow for assigning/changing tags per activity is not implemented.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.
- Additional design variants + documentation for recommended SVG sizing still pending.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
