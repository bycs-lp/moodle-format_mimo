# format_minimoodlewall · AI README

> Working note for autonomous agents extending the Minimal Moodle Wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards. Optionally supports multi-section mode where each section has its own wall.
- **Core concept**: Courses use an *activity profile* that determines which *tags* are active and how they appear; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Multi-section mode**: Toggled per-course via `enablemultisection` option. When ON: course index activates, teachers can add/rename/reorder sections, each section displays its own wall with scoped filter bar and completion counts. **Overview landing page**: shows a card grid of all sections (section 0 is always hidden) with activity counts and completion progress; clicking a card navigates to that wall; each wall has a home button ("← Back to overview") in the page header. **Sticky wall**: the last-visited section is remembered per user per course via `set_user_preference`. Returning to the course URL without `?section=` auto-redirects to the remembered wall. The home button passes `?overview=1` which clears the preference and shows the overview. Deep-linking to an activity also stores its section. When OFF (default): classic single-wall behavior with all activities in section 1.
- **Section 0 is always hidden**: Section 0 exists in the DB (required by Moodle core) but is never rendered or accessible to any user. All walls start at section 1. Activities should never be placed in section 0.
- **Imported tag/profile scoping**: On cross-instance restore, a dedicated *imported activity profile* is created to override existing tags to match the backup's configuration. New tag records are only created when the backup has MORE tags than the instance. Imported tags/profiles have `scope='imported'` and are course-bound via `course_tags`. Admins see "Imported" badges in the tag management UI and can promote them to global.
- **Primary touch points**: `lib.php` (format logic + course options), `classes/tag_manager.php` (tag DB + files + cache + imported tag bindings), `classes/profile_manager.php` (profile CRUD + per-tag overrides + imported profile management), `classes/style_manager.php` (style variants + per-tag images), `classes/section_image_manager.php` (section overview card images), `tag_management.php` (admin UI + promote actions), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (style variants), `amd/` (JS helpers).

## Architecture Cheatsheet
- **Course format base** (`lib.php`)
  - Default: single-section behavior (section 1 is the wall; section 0 is always hidden), course index disabled, section crumbs hidden on activity pages.
  - **Multi-section mode** (`enablemultisection` course option): when ON, all methods become section-aware:
    - `is_multisection_enabled()` — helper that reads the course option.
    - `get_sectionnum()` returns `1` in single-section mode; returns `$this->singlesection` (nullable, set by `format.php`) in multi-section mode. Returns `null` on the overview landing page (no section selected).
    - `is_section_visible()` — section 0 is always hidden. Single-section: only section 1 + delegated; multi-section: all non-orphan sections except 0 are visible.
  - `can_delete_section()` — returns `true` for sections > 0 in multi-section mode; `false` in single-section mode.
    - `uses_course_index()` — returns `true` in multi-section mode, `false` otherwise.
    - `get_view_url()` — single-section: plain course URL; multi-section: section-specific URL via `/course/view.php` for navigation.
    - `extend_course_navigation()` — single-section: hides section breadcrumbs; multi-section: shows section breadcrumbs + expands selected section in navigation + stores section preference when viewing an activity page (deep link support).
    - `get_remembered_section()` — reads `format_minimoodlewall_lastsection_{courseid}` user preference, validates section exists and is visible (section 0 always fails), clears stale preferences. Returns section number or null.
  - Adds course options: `enablemultisection` (PARAM_BOOL, default `0`), `activityprofile` (PARAM_ALPHANUMEXT, selects which profile to use; default `'explore'`), `enablefiltering`, `backgrounddesign` (PARAM_ALPHANUMEXT, default `'default'`; "default" falls back to the style's background, other values — `primary-school`, `darkmode`, `whiteboard`, `pinnwand`, `paper` — apply full-theme overrides to the board, cards, filter bar, navigation, and completion via a `.mmw-bgdesign-wrapper.mmw-bgdesign-{value}` wrapper and `.mmw-bgdesign-{value}` on the `<ul>` board), `enablecompletionstars`, `distractionfree`.
  - Course settings form shows a read-only tag preview below the activity profile dropdown, rendered via `form_tag_preview.mustache`. Tags update dynamically when the profile is changed (via `profile_image_switcher.js`).
  - **Module form callbacks** (legacy `get_plugins_with_function` pattern — no PSR-14 hooks exist for `moodleform_mod`):
    - `format_minimoodlewall_coursemodule_standard_elements()` — injects a tag `select` dropdown into every module edit form (only for minimoodlewall courses). Pre-selects current tag on edit, or pending session tag on create.
    - `format_minimoodlewall_coursemodule_edit_post_actions()` — persists the tag selection via `tag_manager::assign_tag_to_cm()` (upsert) or `remove_cm_tag()` on save. Returns `$data` for callback chaining.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Table: `*_tags` (name, bgcolor hex, imgplacement center|lower, cardimage, filterimage, activitytype1-3, sortorder, **scope** CHAR(10) DEFAULT 'global' — values: 'global' or 'imported').
  - Table: `*_cmtags` (one tag per `cm`, unique cmid).
  - Table: `*_course_tags` (courseid, tagid, timecreated; unique index on courseid+tagid). Tracks which imported tags are available in which courses.
  - File areas (`FILEAREA_CARDIMAGE = 'tagcard'`, `FILEAREA_FILTERIMAGE = 'tagfilter'`) in system context; served via `format_minimoodlewall_pluginfile()`.
  - Key methods: `create_tag($name, ..., $scope = 'global')`, `get_all_tags()`, `get_tags_for_course($courseid)` (returns profile-filtered tags including imported tags bound to the course), `assign_tag_to_cm($cmid, $tagid)`, `get_cm_tag($cmid)`.
  - **Imported tag methods**: `bind_tag_to_course($tagid, $courseid)`, `unbind_tag_from_course($tagid, $courseid)`, `unbind_all_tags_from_course($courseid)`, `promote_tag_to_global($tagid)` (sets scope='global', deletes all bindings), `get_imported_tags_for_course($courseid)`, `cleanup_orphaned_imported_tags()` (deletes imported tags with zero bindings and zero cmtags), `find_tag_by_fingerprint($data, $excludeids)` (NULL-safe composite match on name+bgcolor+activitytype1-3).
  - `get_tags_for_course()` reads the course's `activityprofile` option, fetches imported tags bound to the course via `get_imported_tags_for_course()`, merges them with global tags, then resolves through `profile_manager::resolve_tags_for_profile()`.
  - `delete_tag()` cascades to profile_tags, cmtags, **and course_tags bindings**.
  - Cache invalidation: `clear_tag_cache()`, `clear_mapping_cache()`, `clear_course_tags_cache($courseid)`.
- **Profile system** (`classes/profile_manager.php`)
  - Table: `*_profiles` (name unique, displayname, sortorder, **scope** CHAR(10) DEFAULT 'global' — values: 'global' or 'imported').
  - Table: `*_profile_tags` (tagid+profileid unique; nullable overrides for name, bgcolor, activitytype1-3; `enabled` flag).
  - Key methods: `create_profile($name, $displayname, $scope = 'global')`, `get_profile_by_name($name)`, `get_or_create_profile_tag($tagid, $profileid)`, `update_profile_tag($id, $data)`, `resolve_tags_for_profile($tags, $profileid, $onlyenabled)`, `resolve_tag_for_profile($tag, $profileid)`.
  - **Imported profile methods**: `create_imported_profile($coursename)` (scope='imported', auto-generated slug name, displayname "📦 {name} (imported)"), `promote_profile_to_global($profileid)` (sets scope='global', promotes referenced imported tags), `cleanup_orphaned_imported_profiles()` (deletes imported profiles not referenced by any course's activityprofile option), `get_global_profiles()` (returns only scope='global' profiles for dropdown filtering).
  - Profiles determine which tags are active for a course and can override tag names, colors, and activity types per profile.
  - **Profile dropdown filtering**: `lib.php` course_format_options uses `get_global_profiles()` for the dropdown, then adds the current course's imported profile if already assigned. Other courses don't see imported profiles in their dropdowns.
  - Default profiles (created on install): `explore` (🌱 Explore Level, sortorder 0), `develop` (🌿 Develop Level, sortorder 1), `master` (🌳 Master Level, sortorder 2).
  - The `develop` profile overrides first two tags with name overrides: 📖 Read → 📚 Analyze, 🔍 Explore → 🔎 Research.
- **Style system** (`classes/style_manager.php`)
  - Table: `*_styles` (name unique, displayname, sortorder).
  - Table: `*_tag_images` (tagid+styleid unique, cardimage, filterimage filenames).
  - File areas: `FILEAREA_STYLE_CARDIMAGE = 'styletagcard'`, `FILEAREA_STYLE_FILTERIMAGE = 'styletagfilter'`.
  - Key methods: `create_style($name, $displayname)`, `get_all_styles()`, `get_style_by_name($name)`, `get_or_create_tag_image($tagid, $styleid)`, `get_tag_image_for_style($tagid, $styleid)`.
  - Styles allow different card/filter images per tag per visual theme.
- **Section image system** (`classes/section_image_manager.php`)
  - No dedicated table — uses Moodle File API only. File area: `FILEAREA = 'sectionimage'` in **course context** with `course_sections.id` as itemid.
  - Accepted types: jpg, png, webp, svg.
  - Key methods: `get_image_url($courseid, $sectionid)`, `save_image($courseid, $sectionid, $draftitemid)`, `delete_image($courseid, $sectionid)`, `has_image($courseid, $sectionid)`, `delete_all_for_course($courseid)`.
  - When a section has an uploaded image, it replaces the miniwall tiles on the overview card.
  - Upload/change UX: Dynamic form modal (`classes/form/section_image_form.php`, extends `dynamic_form`) opened via `amd/src/section_image_modal.js`. Teacher clicks an "Upload image" / "Change image" button on the overview card in editing mode. The filepicker in the modal handles upload, change, and removal.
  - Cleanup: `course_section_deleted` and `course_deleted` observers remove orphaned images.
- **Description tags system** (`classes/description_tag_manager.php` + `classes/activity_description_manager.php`)
  - Tables: `*_desc_tags` (name + color), `*_actdesc` (activity type descriptions with optional `desctagid`).
  - Description tags provide visual categorization pills on activity type cards in chooser modal.
  - Default description tags (created on install): 🟡 📥 Input (#FFF176), 🟢 🔁 Practice (#81C784), 🟣 📤 Share (#CE93D8), 🔵 🧠 Think (#64B5F6).
  - Activity descriptions cached with LEFT JOIN to include tag data (name, color) for performance.
  - Admin pages: `description_tags.php` (manage tags), `activity_descriptions.php` (assign tags to activity types).
- **Event observers** (`classes/observer.php` + `db/events.php`)
  - `course_module_created`: Auto-assign pending tag from session (guided creation flow) + apply minimoodlewall completion default overrides (see below).
  - `course_module_deleted`: Delete cmtag record for the deleted module + clear cache.
  - `course_section_deleted`: Delete section overview card image for the deleted section.
  - `course_deleted`: Delete all orphaned cmtag records + delete all section images for the course + **delete course_tags bindings + cleanup orphaned imported tags + cleanup orphaned imported profiles** + clear caches.
- **Completion defaults override** (`classes/completion_defaults_manager.php` + `completion_defaults.php`)
  - Table: `*_compdefs` (module unique; completion, completionview, completionusegrade, completionpassgrade, completionexpected, customrules JSON).
  - When a module is created in a minimoodlewall course and its completion matches Moodle's core defaults (meaning the teacher did not customize), the observer silently replaces completion with the minimoodlewall override.
  - Comparison logic: checks core fields (completion, completionview, completionpassgrade, completiongradeitemnumber↔completionusegrade) and custom rules on the module instance table.
  - Override applies to both `course_modules` (core fields) and the module instance table (custom rules from JSON blob).
  - Admin page (`completion_defaults.php`): lists all module types, allows editing per-type completion defaults using core's `defaultedit_form`.
  - Key methods: `get_default($moduleid)`, `save_default($moduleid, $data)`, `delete_default($moduleid)`, `matches_core_defaults($cm, $coredefaults, $modname)`, `apply_defaults($cm, $mmwdefaults, $modname)`, `pack_form_data($formdata, $suffix)`.
- **Admin UX** (`settings.php`, `tag_management.php`, `style_management.php`, `classes/form/*`)
  - Tag management: Accordion-based UI with tagsets as expandable sections, tags as forms within. `data-tagset-name` attribute for Behat targeting.
  - **Imported badges**: Tags with `scope='imported'` show a blue "Imported" badge (`bg-info`) in the tag name column. Imported profiles show a blue "Imported" badge next to the profile button. Both have a "Make global" promote button (uses `i/publish` pix icon) that calls `promote_tag_to_global()` / `promote_profile_to_global()`.
  - Style management: Tab-based style image management per tag.
  - Only admins (`moodle/site:config`) can manage tags/tagsets/styles; links exposed under Site administration > Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + style variants (`explore`, `develop`, `master`).
- **Caching** (`db/caches.php`)
  - `tagconfigurations`: all tags metadata, keyed per course (`course_tags_{courseid}`).
  - `activitytagmappings`: cm→tag lookup.
  - `activity_descriptions`: cached activity descriptions with tag data (LEFT JOIN on desc_tags).
  - Clear via `tag_manager::clear_course_tags_cache($courseid)` or `activity_description_manager::clear_cache()` whenever data changes.

## Backup & Restore Architecture
- **Backup** (`backup/moodle2/backup_format_minimoodlewall_plugin.class.php`)
  - Course-level: Backs up styles (only those referenced by course tags), tags (all fields incl. bgcolor, imgplacement, activitytype3, **scope**), profiles (**incl. scope**), profile_tags, and tag_images. File annotations for all image file areas. Also backs up section images (keyed by section ID in course context).
  - Module-level: Backs up cmtag records (cmid → tagid mapping).
  - XML tree: `pluginwrapper → mmw_styles → mmw_style` + `pluginwrapper → mmw_tags → mmw_tag → mmw_tag_images → mmw_tag_image` + `pluginwrapper → mmw_section_images → mmw_section_image`.
- **Restore** (`backup/moodle2/restore_format_minimoodlewall_plugin.class.php`)
  - **Smart matching algorithm** (three-tier, processed per backup tag in sortorder):
    1. **Fingerprint match**: composite comparison of `name + bgcolor + activitytype1 + activitytype2 + activitytype3` (NULL-safe) against all unmatched existing tags. Reuses existing tag; no override needed.
    2. **Positional match**: takes the next unmatched existing tag (by sortorder). Reuses existing tag; full profile override needed.
    3. **Create new imported tag**: only when backup has MORE tags than existing. Creates with `scope='imported'` + `course_tags` binding to course.
  - **Imported profile creation** (`after_execute_course()`):
    - If ALL tags fingerprint-match AND count is equal → no imported profile is created. Zero overhead.
    - Otherwise (any mismatch or count difference) → creates full imported profile:
      - Creates profile via `profile_manager::create_imported_profile($coursename)` with scope='imported'.
      - Creates explicit `profile_tag` records for EVERY tag (full override values from backup — name, bgcolor, activity types). Makes profile self-contained.
      - For new imported tags: creates `profile_tag` with `enabled=0` in ALL existing global profiles (prevents implicit-enable leaking into other courses).
      - For surplus existing tags: creates `profile_tag` with `enabled=0` in imported profile.
      - Creates `course_tags` bindings for new imported tags.
      - Sets course's `activityprofile` format option to the new profile's name.
    - **Cleanup benefit**: deleting an imported profile = delete its profile_tags. All overrides gone in one step.
  - **Profile_tag from backup**: applied only for fingerprint-matched tags (safe — same conceptual tag). Skipped for positional/new matches to avoid contaminating target profiles.
  - ID mapping: Sets `format_minimoodlewall_tag`, `format_minimoodlewall_style`, `format_minimoodlewall_tag_image` mappings.
  - `after_execute_course()`: Restores file areas including section images (uses core `course_section` mapping for ID remapping).
  - `after_restore_course()`: Clears all caches.

## Workflows & Entry Points
1. **Course creation**
   - User selects Minimal Moodle Wall format.
   - Selects an activity profile (determines which tags are active and how they appear).
   - A read-only tag preview shows the active tags for the selected profile.
   - Only section 0 (hidden, required by core) and section 1 (the wall) are created by default (single-section mode). If `enablemultisection` is ON, teachers can add more sections.
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
   - **Single-section mode**: Wall shows all activities from section 1 in a responsive grid.
   - **Multi-section mode — overview**: Shows a card grid of all sections. Each card displays section name, optional custom image (replaces miniwall when uploaded), activity mini-tiles (when no image), and completion progress bar. In editing mode: teachers can upload/change section images, delete sections (confirmation modal with activity count warning), and reorder sections via drag-and-drop (whole card is drag surface, interactive elements take priority via `draggable="false"`). Clicking a card navigates to `?section=N`. Shown on first visit (no stored preference) or when the home button is clicked (`?overview=1`).
   - **Multi-section mode — single wall**: Shows one section's wall. A home button appears in the page header, navigating to the overview (`?overview=1`, which clears the stored preference). Visiting a wall stores the section number in the user's preference (`format_minimoodlewall_lastsection_{courseid}`). Returning to the plain course URL auto-redirects to the stored wall.
   - **Sticky wall behavior**: User preference `format_minimoodlewall_lastsection_{courseid}` tracks last-visited section. Set on wall visit and activity page view. Cleared on home button click. Validated on read (deleted/hidden sections fall through to overview). Cleaned up when course is deleted.
   - Optional filter bar (enabled via course option) lists tags with usage counts; clicking filters the visible cards.
6. **Course index drawer**
   - Disabled in single-section mode (`uses_course_index()` returns `false`).
   - Enabled in multi-section mode — shows all sections for navigation.
7. **Compact secondary navigation** (students only)
   - Users without `moodle/course:update` capability see a compact three-dot (kebab) dropdown in the header actions area instead of the full secondary navigation bar.
   - Body class `format-mmw-compact-secondarynav` hides `.secondary-navigation` via CSS. The nav is still rendered in the DOM so items are available.
   - `compact_nav.js` reads visible nav links and overflow "More" items from the hidden bar and populates a Bootstrap dropdown (`[data-region="mmw-secondarynav-dropdown"]`). If no items are found, the dropdown button is removed entirely.
   - Teachers/editors with `moodle/course:update` always see the standard secondary navigation bar.

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
- **Section 0 is always hidden**: Section 0 exists in DB (required by Moodle core — cannot be deleted or moved) but `is_section_visible()` always returns `false` for it. Never place activities in section 0. Never reference section 0 in URLs or navigation.
- **Single-section mode** (default): all activities live in section 1. `get_sectionnum()` returns 1; `is_section_visible()` shows only section 1 and delegated sections. Don't add activities to other sections unless multi-section is enabled.
- **Multi-section mode** (`enablemultisection`): each section (1+) is its own wall. Section 0 is excluded from overview, course index, and all rendering. `get_sectionnum()` returns the currently viewed section (or `null` on the overview landing page). Course index is active. Filter bars, completion counts, and tag usage counts are scoped per-section. Drag-drop is within-section only.
- **Sticky wall** (`format_minimoodlewall_lastsection_{courseid}` user preference): Visiting a wall stores the section number. Returning to the course without `?section=` auto-redirects to the last wall. `?overview=1` clears the preference and shows overview. `extend_course_navigation()` also stores the preference when viewing an activity page (deep link and course index activity clicks). `get_remembered_section()` validates the stored section exists and is visible. `delete_format_data()` cleans up preferences for all users on course deletion.
- **Multi-section overview** (landing page): Shown when no `?section=` param AND no stored preference (or `?overview=1`). `format.php` does NOT call `set_sectionnum()`. `content.php` detects `get_sectionid() === null` and builds lightweight section card data via `export_overview()`, using `overview.mustache`. This avoids rendering full walls for every section.
- **Back to overview button**: Home button in page header (SVG icon), rendered by `page_set_course()` via `$page->add_header_action()`. Links to `?overview=1` to clear the stored preference and show the overview.
- When toggling multi-section OFF, activities in sections >1 become hidden. There is no auto-migration — only a help text warning.
- The course index is disabled in single-section mode. Do not re-enable it without also enabling multi-section.
- Never bypass the profile system — `get_tags_for_course()` filters tags through the course's activity profile. All tags are active by default; profiles can disable individual tags.
- Every course should have a valid `activityprofile` option — defaults to `'explore'`.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` / `style_manager` / `profile_manager` helpers to avoid orphans.
- Update caches after any tag/profile/style change; otherwise wall rendering will show stale logos/colors. Use `clear_course_tags_cache($courseid)` for course-specific cache invalidation.
- Tags and profiles are **global by default** — shared across all courses; courses reference profiles via the `activityprofile` format option. **Imported** tags/profiles have `scope='imported'` and are course-scoped via `course_tags` bindings.
- **Imported scope rules**: Imported tags are only visible in courses with explicit `course_tags` bindings. Imported profiles only appear in the course settings dropdown for the course already using them. New imported tags get explicit `profile_tag` with `enabled=0` in ALL global profiles to prevent implicit-enable leaking. Never skip this step when creating imported tags.
- **Imported profile cleanup**: `course_deleted` observer calls `unbind_all_tags_from_course()`, `cleanup_orphaned_imported_tags()`, and `cleanup_orphaned_imported_profiles()`. Don't add manual cleanup elsewhere.
- Backup/restore reuses by fingerprint match on same instance. Don't create duplicate-prevention logic elsewhere.
- Observer cleanup: `course_module_deleted` and `course_deleted` handle orphan cmtag records + imported tag/profile cleanup. Don't add manual cleanup in other code paths.
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
- `lib.php` – course options (enablemultisection, activityprofile, enablefiltering, backgrounddesign, enablecompletionstars, distractionfree), `is_multisection_enabled()` helper, conditional `get_sectionnum()` / `is_section_visible()` / `uses_course_index()` / `get_view_url()` / `extend_course_navigation()` (also stores section preference on activity pages), `get_remembered_section()` (validates stored preference), read-only tag preview in form, **profile dropdown filters to global + current course's imported profile**, pluginfile hook, **module form callbacks** (`coursemodule_standard_elements` tag dropdown + `coursemodule_edit_post_actions` tag persistence), preference cleanup in `delete_format_data()`.
- `format.php` – entry point; branches on `is_multisection_enabled()`: multi-section stores preference on wall visit, restores preference on plain visit (redirects to `?section=N`), handles `?overview=1` (clears preference, shows overview); single-section ensures section 1 exists and locks to section 1.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes, **imported tag binding/unbinding/promotion/cleanup, fingerprint matching**. Key methods: `get_all_tags()`, `get_tags_for_course($courseid)` (profile-filtered + imported tags), `get_tag_usage_counts($courseid, $tagids, $sectionid)` (optional section scoping), `find_tag_by_fingerprint()`, `bind_tag_to_course()`, `promote_tag_to_global()`, `cleanup_orphaned_imported_tags()`.
- `classes/profile_manager.php` – profile CRUD, per-tag profile overrides (name, bgcolor, activity types, enabled), tag resolution, **imported profile creation/promotion/cleanup**. Key methods: `resolve_tags_for_profile()`, `resolve_tag_for_profile()`, `get_or_create_profile_tag()`, `create_imported_profile()`, `promote_profile_to_global()`, `cleanup_orphaned_imported_profiles()`, `get_global_profiles()`.
- `classes/style_manager.php` – style CRUD, per-tag style images (tag_images table), file areas for style card/filter images.
- `classes/description_tag_manager.php` – description tag CRUD for activity type categorization.
- `classes/activity_description_manager.php` – activity description CRUD with tag assignment, cached with LEFT JOIN.
- `classes/observer.php` – event handlers: auto-tag on module create, **completion default override on module create**, cleanup on module/course/section delete (including section images, **imported tag/profile bindings and orphan cleanup**).
- `classes/completion_defaults_manager.php` – CRUD for minimoodlewall completion defaults (compdefs table), comparison with core defaults, application to course modules.
- `classes/privacy/` – Privacy API provider.
- `completion_defaults.php` – admin page for managing per-module-type completion default overrides.
- `tag_management.php` – admin UI controller for tagsets (accordion) and tags, **promote actions for imported tags/profiles**.
- `style_management.php` – admin UI for style variants and per-tag images.
- `description_tags.php` – admin UI for managing description tags (name + hex color).
- `activity_descriptions.php` – admin UI for editing activity type descriptions and assigning description tags.
- `classes/form/tag_form.php` – mform definition for tag create/edit (name, color, images, activity types).
- `classes/form/tagset_form.php` – mform for tagset create/edit.
- `classes/form/description_tag_form.php` – mform for description tag create/edit with color validation.
- `classes/form/completion_defaults_form.php` – mform extending core's `defaultedit_form` for minimoodlewall completion overrides.
- `classes/form/activity_descriptions_form.php` – mform with dropdowns for assigning tags to activity types.
- `classes/section_image_manager.php` – section overview card image CRUD. File area `sectionimage` in course context, section ID as itemid. No DB table — file existence is truth. Methods: `get_image_url()`, `save_image()`, `delete_image()`, `has_image()`, `delete_all_for_course()`.
- `classes/form/section_image_form.php` – dynamic form (modal) with filepicker for uploading/changing section images (jpg, png, webp, svg). Extends `dynamic_form`.
- `amd/src/section_image_modal.js` – opens the section image dynamic form modal from overview card buttons in editing mode.
- `classes/external/get_tags.php` – webservice for fetching tags by course ID (returns profile-filtered tags).
- `classes/external/get_activity_descriptions.php` – webservice for fetching activity descriptions with tag data for modal.
- `classes/output/courseformat/content/activitychooserbutton.php` – **Moodle 5.1+ tag chooser button** (extends core class).
- `classes/output/courseformat/content/cm.php` – course module data provider (backward compatible with 4.x).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates. `content.php` detects overview mode (multi-section + no section selected) and returns lightweight section card data via `export_overview()`, switching to `overview.mustache`; in wall view provides `overviewurl`, `showoverviewlink`, `currentsectionname`. `cmitem.php` resolves tags through profile for card rendering.
- `templates/tag_management.mustache` – accordion-based tag admin UI, **imported badges (blue `bg-info`) and promote buttons for imported tags/profiles**.
- `templates/form_tag_preview.mustache` – read-only tag preview for course settings form (shows active tags for selected profile).
- `templates/style_management.mustache` – style admin with per-tag image tabs.
- `templates/local/content/activitychooserbutton.mustache` – **Moodle 5.1+ tag chooser template**.
- `templates/local/overview.mustache` – **Multi-section overview landing page** with section card grid, activity counts, and completion progress.
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
- `amd/src/section_overview_actions.js` – section overview card delete + drag-and-drop reorder (editing mode). Uses `BaseComponent` + `DragDrop` from `core/reactive`. Whole card is drag surface; interactive children protected via `draggable="false"`. Calls `core_courseformat_update_course` with `section_delete` / `section_move_after`.
- `amd/src/style_image_switcher.js` – style image tab switching.
- `amd/src/description_tag_management.js` – description tag admin helpers.
- `amd/src/compact_nav.js` – compact secondary navigation: clones hidden secondary nav items into a three-dot header dropdown for students.
- `amd/src/distraction_free.js` – distraction-free mode toggle.
- `backup/moodle2/backup_format_minimoodlewall_plugin.class.php` – backup handler (tagsets, tags **incl. scope**, styles, tag_images, profiles **incl. scope**, cmtags, files).
- `backup/moodle2/restore_format_minimoodlewall_plugin.class.php` – restore handler (**three-tier fingerprint/positional/create matching, imported profile creation with full overrides**, ID mapping, format option remapping).
- `db/install.xml` – **10** tables: tags, cmtags, styles, tag_images, desc_tags, actdesc, profiles, profile_tags, compdefs, **course_tags**.
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
