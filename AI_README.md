# format_mimo · AI README

> Working note for autonomous agents extending the mimo wall course format.

## TL;DR
- **Goal**: Replace Moodle's section-per-week layout with a single responsive "wall" of activity cards. Optionally supports multi-section mode where each section has its own wall.
- **Core concept**: Courses use an *activity profile* that determines which *tags* are active and how they appear; tags inject SVG art, colors, and category data for each module. Optional filtering lets learners show only activities for a tag.
- **Multi-section mode**: Toggled per-course via `enablemultisection` option. When ON: course index activates, teachers can add/rename/reorder sections, each section displays its own wall with scoped filter bar and completion counts. **Overview landing page**: shows a card grid of all sections (section 0 is always hidden) with activity counts and completion progress; clicking a card navigates to that wall; each wall has a home button ("← Back to overview") in the page header. **Sticky wall**: the last-visited section is remembered per user per course via `set_user_preference`. Returning to the course URL without `?section=` auto-redirects to the remembered wall. The home button passes `?overview=1` which clears the preference and shows the overview. Deep-linking to an activity also stores its section. When OFF (default): classic single-wall behavior with all activities in section 1.
- **Section 0 is always hidden**: Section 0 exists in the DB (required by Moodle core) but is never rendered or accessible to any user. All walls start at section 1. Activities should never be placed in section 0.
- **Imported tag/profile scoping**: On cross-instance restore, a dedicated *imported activity profile* is created to override existing tags to match the backup's configuration. New tag records are only created when the backup has MORE tags than the instance. Imported tags/profiles have `scope='imported'` and are course-bound via `course_tags`. Admins see "Imported" badges in the tag management UI and can promote them to global.
- **Primary touch points**: `lib.php` (format logic + course options + module form callbacks), `classes/tag_manager.php` (tag DB + files + cache + imported tag bindings), `classes/profile_manager.php` (profile CRUD + per-tag overrides + imported profile management), `classes/done_manager.php` (done flags for greying out activities), `classes/courseformat/stateactions.php` (custom state actions: done/undone/duplicate with tag copy), `classes/section_image_manager.php` (section overview card images), `tag_management.php` (admin UI + promote actions), `classes/output/**` + `templates/local/**` (rendering), `styles.scss` (wall CSS), `amd/` (JS helpers).

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
    - `get_remembered_section()` — reads `format_mimo_lastsection_{courseid}` user preference, validates section exists and is visible (section 0 always fails), clears stale preferences. Returns section number or null.
  - Adds course options: `enablemultisection` (PARAM_BOOL, default `0`), `activityprofile` (PARAM_ALPHANUMEXT, selects which profile to use; default `'explore'`), `enablefiltering`, `backgrounddesign` (PARAM_ALPHANUMEXT, default `'default'`; "default" falls back to the style's background, other values — `primary-school`, `darkmode`, `whiteboard`, `pinnwand`, `paper` — apply full-theme overrides to the board, cards, filter bar, navigation, and completion via a `.mimo-bgdesign-wrapper.mimo-bgdesign-{value}` wrapper and `.mimo-bgdesign-{value}` on the `<ul>` board), `distractionfree`.
  - A single gold completion star is always shown (no per-course toggle) when a section has at least one tracked activity AND all are complete. On multi-section overview cards the star appears inline next to the progress text (learner `5 / 5 ⭐`, teacher `100% ⭐`).
  - Course settings form shows a read-only tag preview below the activity profile dropdown, rendered via `form_tag_preview.mustache`. Tags update dynamically when the profile is changed (via `profile_image_switcher.js`).
  - **Module form callbacks** (legacy `get_plugins_with_function` pattern — no PSR-14 hooks exist for `moodleform_mod`):
    - `format_mimo_coursemodule_standard_elements()` — injects a tag `select` dropdown into every module edit form (only for mimo courses). Pre-selects current tag on edit, or pending session tag on create.
    - `format_mimo_coursemodule_edit_post_actions()` — persists the tag selection via `tag_manager::assign_tag_to_cm()` (upsert) or `remove_cm_tag()` on save. Returns `$data` for callback chaining.
    - `format_mimo_coursemodule_definition_after_data()` — pre-populates completion form fields with mimo defaults (from `completion_defaults_manager`) for new activities. Only fires for new modules (`!$current->instance`). Uses `$mform->setDefault()` so teachers see intended values but can override.
- **Tag domain model** (`db/install.xml` + `classes/tag_manager.php`)
  - Table: `*_tags` (name, bgcolor hex, imgplacement center|lower, cardimage, filterimage, activitytype1-3, sortorder, **scope** CHAR(10) DEFAULT 'global' — values: 'global' or 'imported').
  - Table: `*_cmtags` (one tag per `cm`, unique cmid).
  - Table: `*_course_tags` (courseid, tagid, timecreated; unique index on courseid+tagid). Tracks which imported tags are available in which courses.
  - File areas (`FILEAREA_CARDIMAGE = 'tagcard'`, `FILEAREA_FILTERIMAGE = 'tagfilter'`) in system context; served via `format_mimo_pluginfile()`. Accepted types: `.svg`, `.png`.
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
  - **Default profile overrides** (`initialize_default_profiles()`): Uses a generic `$profileoverrides` data structure (profile name → tag index → override fields). Supports any field: `name`, `bgcolor`, `activitytype1-3`, `imgplacement`, `imgsize`, `cardimage`, `filterimage`. Image fields trigger file copy from `pix/tags/` into the profile file area via `copy_default_profile_image()` + DB field update via `apply_default_profile_images()`.
  - The `explore` profile overrides all 7 tags with profile-specific card/filter images (`*_explore.png` from `pix/tags/`): read_explore, explore_explore, write_explore, share_explore, train_explore, teamwork_explore, design_explore (positional mapping matching tag creation order).
  - The `develop` profile overrides first two tags with name overrides: 📖 Read → 📚 Analyze, 🔍 Explore → 🔎 Research.
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
  - **Default activity descriptions** (created on install via `activity_description_manager::initialize_default_activity_descriptions()`): 37 activity types get short student-facing descriptions (`actdesc_{modname}` lang strings, EN + DE) and are assigned to one of the 4 description tags: Input (page, book, resource, url, imscp, scorm, lesson, hvp, h5pactivity, lti, learningmap, unilabel, subcourse), Practice (quiz, game, mootyper, geogebra, qbank), Share (forum, assign, glossary, wiki, board, journal, moodleoverflow, lightboxgallery, data), Think (choice, feedback, workshop, ratingallocate, bigbluebuttonbn, individualfeedback, kanban, aichat, mootimeter, checklist). Only creates for installed modules; skips missing lang strings.
  - Activity descriptions cached with LEFT JOIN to include tag data (name, color) for performance.
  - Admin pages: `description_tags.php` (manage tags), `activity_descriptions.php` (assign tags to activity types).
- **Event observers** (`classes/observer.php` + `db/events.php`)
  - `course_module_created`: Auto-assign pending tag from session (guided creation flow).
  - `course_module_deleted`: Delete cmtag record for the deleted module + delete done flag + clear cache.
  - `course_section_deleted`: Delete section overview card image for the deleted section.
  - `course_deleted`: Delete all orphaned cmtag records + delete all section images for the course + **delete course_tags bindings + cleanup orphaned imported tags + cleanup orphaned imported profiles** + delete done flags for course + clear caches.
- **Done system** (`classes/done_manager.php` + `classes/courseformat/stateactions.php`)
  - Table: `*_cmdone` (cmid unique; timecreated). Flags a course module as "done" — greyed out on wall, excluded from completion progress.
  - Teachers mark activities as done via the visibility dropdown (Show/Hide/Stealth/**Done**) or bulk edit. Done activities remain visible to students but appear greyed out.
  - Switching an activity to Show/Hide/Stealth automatically clears its done flag.
  - Key methods: `is_done($cmid)`, `set_done($cmid)`, `unset_done($cmid)`, `get_done_cmids($courseid)`, `delete_for_cm($cmid)`, `delete_for_course($courseid)`.
  - Request-level cache (`$donecache`) avoids repeated DB hits — primes entire course on first access.
  - Custom visibility class (`classes/output/courseformat/content/cm/visibility.php`): extends core visibility dropdown, adds "Done" option with `cm_done`/`cm_undone` state actions, always renders (so teachers can toggle from any state).
  - Custom state actions (`classes/courseformat/stateactions.php`): extends `core_courseformat\stateactions` with `cm_done()`, `cm_undone()` actions + overrides `cm_show()`/`cm_hide()`/`cm_stealth()` to clear done flag + overrides `cm_duplicate()` to copy tags to clones.
  - Client-side mutations (`amd/src/mutations.js`): `MimoMutations` class extends `DefaultMutations` with `cmDone`/`cmUndone`/`cmShow`/`cmHide`/`cmStealth` that force-reload cmitems after action (needed because done↔show doesn't change `cm.visible`). Also registers `mimoAvailability` action handler.
  - **Overdue indicator**: Activities with automatic completion that have a passed `completionexpected` date and are not yet complete show an hourglass icon on the card (student view). Done activities show the icon greyed out.
- **Bulk edit availability modal** (`classes/output/courseformat/content/bulkedittools.php` + `templates/local/content/cm/availabilitymodal.mustache`)
  - Overrides core's bulk edit `availability` action with `mimoAvailability` which opens a custom modal including the "Done" radio option alongside Show/Hide/Stealth.
  - Modal rendered via `mutations.js` `mimoAvailabilityHandler`: gathers bulk selection IDs, renders modal, dispatches chosen mutation on submit or double-click.
- **Completion defaults override** (`classes/completion_defaults_manager.php` + `completion_defaults.php`)
  - Table: `*_compdefs` (module unique; completion, completionview, completionusegrade, completionpassgrade, completionexpected, customrules JSON).
  - When a teacher opens the module creation form in a mimo course, the `format_mimo_coursemodule_definition_after_data()` callback pre-populates the form fields with mimo defaults (using `$mform->setDefault()`). Teachers see the intended values and can change them before saving. No post-creation override is applied.
  - Comparison logic (retained for upgrade/migration scenarios): checks core fields (completion, completionview, completionpassgrade, completiongradeitemnumber↔completionusegrade) and custom rules on the module instance table.
  - Override applies to both `course_modules` (core fields) and the module instance table (custom rules from JSON blob).
  - Admin page (`completion_defaults.php`): lists all module types, allows editing per-type completion defaults using core's `defaultedit_form`.
  - **Default seeding** (`initialize_default_completion_defaults()`): Seeds ~37 activity types on install/upgrade. Four tiers:
    - **Tier A (custom rule + grade)**: assign (`completionsubmit`), quiz (`completionminattempts`), lesson (`completionendreached`), scorm (`completionstatusrequired=6` passed|completed). Automatic tracking + `completionusegrade=1`.
    - **Tier B (grade only)**: h5pactivity, lti, workshop. Automatic tracking + `completionusegrade=1`, no custom rules.
    - **Tier C (custom rule, no grade)**: choice/feedback (`completionsubmit`), forum (`completionposts`), glossary/data (`completionentries`), board (`completionnotes`), kanban (`completioncreate`), checklist (`completionpercent=100`), ratingallocate (`completionvote`), mootyper (`completionexercise`), subcourse (`completioncourse`), bigbluebuttonbn (`completionattendance`), learningmap (`completiontype=2`).
    - **Tier D (manual)**: page, book, resource, url, imscp, folder, label, unilabel, wiki, hvp, journal, moodleoverflow, lightboxgallery, individualfeedback, aichat, mootimeter, game, geogebra, qbank. `completion=1` (student self-marks).
    - Guard: only seeds when compdefs table is empty; skips modules not installed in the instance.
  - Key methods: `get_default($moduleid)`, `save_default($moduleid, $data)`, `delete_default($moduleid)`, `matches_core_defaults($cm, $coredefaults, $modname)`, `apply_defaults($cm, $mimodefaults, $modname)`, `pack_form_data($formdata, $suffix)`, `initialize_default_completion_defaults()`.
- **Admin UX** (`settings.php`, `tag_management.php`, `classes/form/*`)
  - Tag management: Accordion-based UI with tagsets as expandable sections, tags as forms within. `data-tagset-name` attribute for Behat targeting.
  - **Imported badges**: Tags with `scope='imported'` show a blue "Imported" badge (`bg-info`) in the tag name column. Imported profiles show a blue "Imported" badge next to the profile button. Both have a "Make global" promote button (uses `i/publish` pix icon) that calls `promote_tag_to_global()` / `promote_profile_to_global()`.
  - Only admins (`moodle/site:config`) can manage tags/tagsets/profiles; links exposed under Site administration > Courses.
- **Rendering** (`format.php`, `classes/output/courseformat/*`, `templates/local/content/*.mustache`)
  - Modern Moodle 4.x component-based stack: base content → section → `cmitem` templates.
  - Activities render as Bootstrap card tiles; each card receives tag metadata (icon URL, accent color, activity type labels).
  - `styles.scss` hosts shared wall styles + profile-specific CSS (`explore`, `develop`, `master`).
- **Caching** (`db/caches.php`)
  - `tagconfigurations`: all tags metadata, keyed per course (`course_tags_{courseid}`). Resolved payloads embed profile overrides and pre-computed image URLs, so any mutation of base tags, profile_tags, or profile-tag image uploads must purge this cache.
  - `activitytagmappings`: cm→tag lookup.
  - `activity_descriptions`: cached activity descriptions with tag data (LEFT JOIN on desc_tags).
  - **Cache invariants**:
    - `tag_manager::create_tag()` / `update_tag()` / `delete_tag()` → full `clear_tag_cache()` (purges all `course_tags_*` so new/changed tags appear everywhere immediately).
    - `profile_manager::update_profile_tag()` / `delete_profile()` / profile image upload / `promote_profile_to_global()` / `create_imported_profile()` → `clear_tag_cache()`.
    - `tag_manager::bind/unbind_tag_to_course()` / `unbind_all_tags_from_course()` → per-course `clear_course_tags_cache($courseid)`.
    - `tag_manager::promote_tag_to_global()` → full `clear_tag_cache()` (visibility changes across all courses).
    - `description_tag_manager::update_tag()` / `delete_tag()` → `activity_description_manager::clear_cache()` (joined cache holds name+color).

## Backup & Restore Architecture
- **Backup** (`backup/moodle2/backup_format_mimo_plugin.class.php`)
  - Course-level: Backs up tags (all fields incl. bgcolor, imgplacement, activitytype3, **scope**), profiles (**incl. scope**), profile_tags. File annotations for all image file areas. Also backs up section images (keyed by section ID in course context).
  - Module-level: Backs up cmtag records (cmid → tagid mapping).
  - **Scope of export**: Only tags and profiles that are referenced by at least one tagged course module in this course are exported (joined via `format_mimo_cmtags`). Unused tags or enabled-but-unused profile configuration does NOT transfer. A course with no tagged activities exports no tag/profile data.
  - XML tree: `pluginwrapper → mimo_tags → mimo_tag` + `pluginwrapper → mimo_section_images → mimo_section_image`.
- **Restore** (`backup/moodle2/restore_format_mimo_plugin.class.php`)
  - **Smart matching algorithm** (four-tier, processed per backup tag in sortorder):
    1. **Fingerprint match**: composite comparison of `name + bgcolor + activitytype1 + activitytype2 + activitytype3` (NULL-safe) against all unmatched existing tags. Reuses existing tag; no override needed. Counts as "recognized".
    2. **Name match**: same `name` as an unmatched existing tag but different properties. Reuses existing tag; the target instance's current values win (trusts admin edits since the backup was taken). Counts as "recognized". `profile_tag` records from backup ARE applied (same conceptual tag).
    3. **Positional match**: next unmatched existing tag by sortorder. Reuses existing tag; marks `allrecognized=false`. `profile_tag` from backup is SKIPPED to avoid contaminating target profiles.
    4. **Create new imported tag**: only when backup has MORE tags than existing. Creates with `scope='imported'` + `course_tags` binding to course. Marks `allrecognized=false`. `profile_tag` from backup is SKIPPED.
  - **Imported profile creation** (`after_execute_course()`):
    - If `allrecognized` stays true (ALL tags matched via fingerprint OR name) → no imported profile is created. Zero overhead. Name-match preserves admin-edited target properties.
    - Otherwise (any positional or new-imported match) → creates full imported profile:
      - Creates profile via `profile_manager::create_imported_profile($coursename)` with scope='imported'.
      - Creates explicit `profile_tag` records for EVERY tag (full override values from backup — name, bgcolor, activity types). Makes profile self-contained.
      - For new imported tags: creates `profile_tag` with `enabled=0` in ALL existing global profiles (prevents implicit-enable leaking into other courses).
      - For surplus existing tags: creates `profile_tag` with `enabled=0` in imported profile.
      - Creates `course_tags` bindings for new imported tags.
      - Sets course's `activityprofile` format option to the new profile's name.
    - **Cleanup benefit**: deleting an imported profile = delete its profile_tags. All overrides gone in one step.
  - **Profile_tag from backup**: applied for fingerprint- AND name-matched tags (same conceptual tag). Skipped for positional/new matches.
  - ID mapping: Sets `format_mimo_tag` mappings.
  - `after_execute_course()`: Restores file areas including section images (uses core `course_section` mapping for ID remapping).
  - `after_restore_course()`: Clears all caches.

## Workflows & Entry Points
1. **Course creation**
   - User selects mimo wall format.
   - Selects an activity profile (determines which tags are active and how they appear).
   - A read-only tag preview shows the active tags for the selected profile.
   - Only section 0 (hidden, required by core) and section 1 (the wall) are created by default (single-section mode). If `enablemultisection` is ON, teachers can add more sections.
2. **Tag & profile management**
   - Admin page (`tag_management.php`) manages tags with accordion UI.
   - Tags have forms for name, color, images, activity types.
   - Profiles determine which tags are visible/active per course.
   - Deleting a tag cascades to profile_tags and cmtags.
3. **Course editing**
  - Teachers see a tag-based activity chooser: clicking the "+" button reveals a dropdown of configured tags.
  - Selecting a tag opens a modal with three options: two quick-create shortcuts (pre-configured activity types) and a link to the full activity chooser.
  - **Activity type cards** in the modal display:
    - Activity icon with purpose-based border color
    - Activity name and description
    - **Description tag pill** (if assigned): appears on top-right, slightly overlapping edge, with custom background color from database
  - This workflow ensures mandatory tagging and guides teachers toward recommended activity combinations.
  - **Version Support:** Minimum supported Moodle version is **5.2** (`$plugin->requires = 2026042000`).
    - Uses `format_mimo\output\courseformat\content\activitychooserbutton` class that extends the `core_courseformat\output\local\content\activitychooserbutton` base class (introduced in MDL-86337).
    - `cm.php` keeps a defensive `$CFG->branch` check as a pattern for future branch divergence.
    - JavaScript handles both `data-section-id` and legacy `data-sectionnum` attributes because the legacy `tagchooserbutton.mustache` template is still included via the format's `cm.mustache` (pending rework).
4. **Learner view**
   - **Single-section mode**: Wall shows all activities from section 1 in a responsive grid.
   - **Multi-section mode — overview**: Shows a card grid of all sections. Each card displays section name, optional custom image (replaces miniwall when uploaded), activity mini-tiles (when no image), and completion progress bar. In editing mode: teachers can upload/change section images, delete sections (confirmation modal with activity count warning), and reorder sections via drag-and-drop (whole card is drag surface, interactive elements take priority via `draggable="false"`). Clicking a card navigates to `?section=N`. Shown on first visit (no stored preference) or when the home button is clicked (`?overview=1`).
   - **Multi-section mode — single wall**: Shows one section's wall. A home button appears in the page header, navigating to the overview (`?overview=1`, which clears the stored preference). Visiting a wall stores the section number in the user's preference (`format_mimo_lastsection_{courseid}`). Returning to the plain course URL auto-redirects to the stored wall.
   - **Sticky wall behavior**: User preference `format_mimo_lastsection_{courseid}` tracks last-visited section. Set on wall visit and activity page view. Cleared on home button click. Validated on read (deleted/hidden sections fall through to overview). Cleaned up when course is deleted.
   - Optional filter bar (enabled via course option) lists tags with usage counts; clicking filters the visible cards.
5. **Course index drawer**
   - Disabled in single-section mode (`uses_course_index()` returns `false`).
   - Enabled in multi-section mode — shows all sections for navigation.
6. **Compact secondary navigation** (students only)
   - Users without `moodle/course:update` capability see a compact three-dot (kebab) dropdown in the header actions area instead of the full secondary navigation bar.
   - Body class `format-mimo-compact-secondarynav` hides `.secondary-navigation` via CSS. The nav is still rendered in the DOM so items are available.
   - `compact_nav.js` reads visible nav links and overflow "More" items from the hidden bar and populates a Bootstrap dropdown (`[data-region="mimo-secondarynav-dropdown"]`). If no items are found, the dropdown button is removed entirely.
   - Teachers/editors with `moodle/course:update` always see the standard secondary navigation bar.

## Common Extension Tasks
- **Add teacher UI for tagging**
  - Introduce cm-level setting + form element (probably in `classes/courseformat/local/...` or observer hook).
  - Update `tag_manager::assign_tag_to_cm()` and invalidate caches.
- **Enhance filtering UX**
  - Extend `amd/src/*` to add reactive filtering, track active tag, and animate card visibility.
  - Expose filter data via `section.mustache` context.
- **Testing**
  - Behat: cover tag selection workflow, filter interactions, and admin CRUD.
  - PHPUnit: tag/profile CRUD, backup/restore, observer handlers, cache invalidation.

## Moodle-Specific Linter Considerations
Moodle has several unique patterns that cause issues with standard PHP linters and static analysis tools:

### Dynamic Class Loading & Missing Namespaces
- **Legacy naming convention**: Many Moodle core classes use `plugintype_pluginname_classname` naming (e.g., `backup_format_mimo_plugin`, `restore_moodleform`) without namespaces.
- **File naming pattern**: Classes in special directories use `.class.php` extension (backup/restore subsystems) which linters may not recognize.
- **Autoloading limitations**: Moodle's autoloader expects:
  - Namespaced classes in `classes/` directory follow PSR-4
  - Legacy classes in root or subdirectories require explicit `require_once()`
- **Mixed paradigms in same plugin**:
  - `lib.php`: Legacy global class `format_mimo` (NO namespace, extends `core_courseformat\base`)
  - `classes/`: Modern namespaced classes `format_mimo\tag_manager`, `profile_manager`, `section_image_manager`
  - `backup/moodle2/*.class.php`: Legacy classes `backup_format_mimo_plugin` (NO namespace)
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
- `lib.php` (68 lines): Global `format_mimo` class, extends namespaced base
- `classes/tag_manager.php`: Fully namespaced, modern PSR-4
- `classes/profile_manager.php`: Fully namespaced, profile CRUD and tag resolution
- `classes/form/tag_form.php`: Namespaced but extends global `\moodleform`, requires explicit include
- `backup/moodle2/*.class.php`: Legacy naming, NO namespaces, loaded by Moodle's backup API

## Guardrails for Future Agents
- **Section 0 is always hidden**: Section 0 exists in DB (required by Moodle core — cannot be deleted or moved) but `is_section_visible()` always returns `false` for it. Never place activities in section 0. Never reference section 0 in URLs or navigation.
- **Single-section mode** (default): all activities live in section 1. `get_sectionnum()` returns 1; `is_section_visible()` shows only section 1 and delegated sections. Don't add activities to other sections unless multi-section is enabled.
- **Multi-section mode** (`enablemultisection`): each section (1+) is its own wall. Section 0 is excluded from overview, course index, and all rendering. `get_sectionnum()` returns the currently viewed section (or `null` on the overview landing page). Course index is active. Filter bars, completion counts, and tag usage counts are scoped per-section. Drag-drop is within-section only.
- **Sticky wall** (`format_mimo_lastsection_{courseid}` user preference): Visiting a wall stores the section number. Returning to the course without `?section=` auto-redirects to the last wall. `?overview=1` clears the preference and shows overview. `extend_course_navigation()` also stores the preference when viewing an activity page (deep link and course index activity clicks). `get_remembered_section()` validates the stored section exists and is visible. `delete_format_data()` cleans up preferences for all users on course deletion.
- **Multi-section overview** (landing page): Shown when no `?section=` param AND no stored preference (or `?overview=1`). `format.php` does NOT call `set_sectionnum()`. `content.php` detects `get_sectionid() === null` and builds lightweight section card data via `export_overview()`, using `overview.mustache`. This avoids rendering full walls for every section.
- **Back to overview button**: Home button in page header (SVG icon), rendered by `page_set_course()` via `$page->add_header_action()`. Links to `?overview=1` to clear the stored preference and show the overview.
- **Activity page navigation** (multi-section): Two header buttons: a back arrow (→ returns to the section wall the activity belongs to via `?section=N`) and a home icon (→ returns to overview via `?overview=1`). Single-section: only the home button (→ course URL). Both rendered in `page_set_course()` via `add_back_button()` / `add_home_button()` helper methods.
- When toggling multi-section OFF, activities in sections >1 become hidden. There is no auto-migration — only a help text warning.
- The course index is disabled in single-section mode. Do not re-enable it without also enabling multi-section.
- Never bypass the profile system — `get_tags_for_course()` filters tags through the course's activity profile. All tags are active by default; profiles can disable individual tags.
- Every course should have a valid `activityprofile` option — defaults to `'explore'`.
- Label and unilabel activities are hidden from the activity chooser via CSS (`display: none !important` on `[data-internal="label"]` and `[data-internal="unilabel"]`) since they don't work in the wall format.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` / `profile_manager` helpers to avoid orphans.
- **Done flag lifecycle**: Done flags (`cmdone` table) are cleared when visibility changes (show/hide/stealth) via `stateactions` overrides. The observer also deletes done flags on module deletion and course deletion. Don't add manual cleanup elsewhere.
- Update caches after any tag/profile change; otherwise wall rendering will show stale logos/colors. Use `clear_course_tags_cache($courseid)` for course-specific cache invalidation.
- Tags and profiles are **global by default** — shared across all courses; courses reference profiles via the `activityprofile` format option. **Imported** tags/profiles have `scope='imported'` and are course-scoped via `course_tags` bindings.
- **Imported scope rules**: Imported tags are only visible in courses with explicit `course_tags` bindings. Imported profiles only appear in the course settings dropdown for the course already using them. New imported tags get explicit `profile_tag` with `enabled=0` in ALL global profiles to prevent implicit-enable leaking. Never skip this step when creating imported tags.
- **Imported profile cleanup**: `course_deleted` observer calls `unbind_all_tags_from_course()`, `cleanup_orphaned_imported_tags()`, and `cleanup_orphaned_imported_profiles()`. Don't add manual cleanup elsewhere.
- Backup/restore reuses by fingerprint match on same instance. Don't create duplicate-prevention logic elsewhere.
- Observer cleanup: `course_module_deleted` and `course_deleted` handle orphan cmtag records + imported tag/profile cleanup. Don't add manual cleanup in other code paths.
- `tag_delete_confirm.js` uses event capturing (`addEventListener('click', handler, true)`) to intercept before `stopPropagation` on accordion buttons. Maintain this pattern.
- Keep AI-facing docs (this file + README) updated when adding new options or data flows to minimize forgotten invariants.
- **Version Compatibility**: Minimum supported Moodle version is **5.2** (branch 502). CI runs against `MOODLE_502_STABLE` and `main`. The activity chooser integration builds on core_courseformat's activitychooserbutton (MDL-86337). `cm.php` retains a `$CFG->branch` check as a defensive pattern for future branch divergence.
- **Linter warnings**: Expect false positives for dynamic class loading, missing namespaces in legacy code, and global variable usage—these are intentional Moodle patterns, not bugs.
- **Section heading comments**: Use C-style block comments for visual section separators inside classes and test files. Moodle's inline comment sniffs (`InvalidEndChar`, `NotCapital`) only apply to `//` comments, so block comments pass cleanly. Preferred style:
  ```php
  /* =============== *
   * Profile CRUD.  *
   * =============== */
  ```
  Do NOT use `// -------` or `// =======` separators — they trigger inline comment linter warnings.

## Version Compatibility Details

- **Minimum Moodle version: 5.2** (`$plugin->requires = 2026042000`). Support for 5.0/5.1 was dropped; legacy fallbacks (core_course activity chooser modules, renderer `course_section_add_cm_control` override) have been removed.
- Activity chooser uses `format_mimo\output\courseformat\content\activitychooserbutton` class
- Extends `core_courseformat\output\local\content\activitychooserbutton` (MDL-86337)
- Uses template: `format_mimo/local/content/activitychooserbutton.mustache`
- Data attributes: `data-section-id`, `data-sectionreturnid` (alongside legacy attributes)
- Hook support: Compatible with `\core_course\hook\before_activitychooserbutton_exported`
- **Pending cleanup**: the format's `cm.mustache` still includes the legacy `format_mimo/tagchooserbutton.mustache` template (emits only `data-sectionnum`), so `tagchooserbutton.js` keeps the section-number fallback (deprecated `Repository.getModulesData`) until that template block is reworked to the core partial.

## Quick File Map
- `lib.php` – course options (enablemultisection, activityprofile, enablefiltering, backgrounddesign, distractionfree), `is_multisection_enabled()` helper, conditional `get_sectionnum()` / `is_section_visible()` / `uses_course_index()` / `get_view_url()` / `extend_course_navigation()` (also stores section preference on activity pages), `get_remembered_section()` (validates stored preference), read-only tag preview in form, **profile dropdown filters to global + current course's imported profile**, pluginfile hook, **module form callbacks** (`coursemodule_standard_elements` tag dropdown + `coursemodule_edit_post_actions` tag persistence + `coursemodule_definition_after_data` completion defaults pre-population), preference cleanup in `delete_format_data()`.
- `format.php` – entry point; branches on `is_multisection_enabled()`: multi-section stores preference on wall visit, restores preference on plain visit (redirects to `?section=N`), handles `?overview=1` (clears preference, shows overview); single-section ensures section 1 exists and locks to section 1.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes, **imported tag binding/unbinding/promotion/cleanup, fingerprint matching**. Key methods: `get_all_tags()`, `get_tags_for_course($courseid)` (profile-filtered + imported tags), `get_tag_usage_counts($courseid, $tagids, $sectionid)` (optional section scoping), `find_tag_by_fingerprint()`, `bind_tag_to_course()`, `promote_tag_to_global()`, `cleanup_orphaned_imported_tags()`.
- `classes/profile_manager.php` – profile CRUD, per-tag profile overrides (name, bgcolor, activity types, enabled, images), tag resolution, **imported profile creation/promotion/cleanup**, **default profile override seeding** (`initialize_default_profiles()`, `copy_default_profile_image()`, `apply_default_profile_images()`). Key methods: `resolve_tags_for_profile()`, `resolve_tag_for_profile()`, `get_or_create_profile_tag()`, `create_imported_profile()`, `promote_profile_to_global()`, `cleanup_orphaned_imported_profiles()`, `get_global_profiles()`.
- `classes/description_tag_manager.php` – description tag CRUD for activity type categorization.
- `classes/activity_description_manager.php` – activity description CRUD with tag assignment, cached with LEFT JOIN.
- `classes/observer.php` – event handlers: auto-tag on module create, cleanup on module/course/section delete (including section images, done flags, **imported tag/profile bindings and orphan cleanup**).
- `classes/completion_defaults_manager.php` – CRUD for mimo completion defaults (compdefs table), comparison with core defaults, application to course modules, **default seeding** (`initialize_default_completion_defaults()` — 4-tier defaults for ~37 activity types).
- `classes/done_manager.php` – CRUD for "done" flags (cmdone table). Request-level cache with course-wide priming. Methods: `is_done()`, `set_done()`, `unset_done()`, `get_done_cmids()`, `delete_for_cm()`, `delete_for_course()`.
- `classes/courseformat/stateactions.php` – custom state actions: `cm_done`/`cm_undone` (mark/unmark done), overrides `cm_show`/`cm_hide`/`cm_stealth` (clear done flag on visibility change), overrides `cm_duplicate` (copy tags to clones).
- `classes/privacy/` – Privacy API provider.
- `completion_defaults.php` – admin page for managing per-module-type completion default overrides.
- `tag_management.php` – admin UI controller for tagsets (accordion) and tags, **promote actions for imported tags/profiles**.
- `description_tags.php` – admin UI for managing description tags (name + hex color).
- `activity_descriptions.php` – admin UI for editing activity type descriptions and assigning description tags.
- `classes/form/tag_form.php` – mform definition for tag create/edit (name, color, images, activity types).
- `classes/form/tagset_form.php` – mform for tagset create/edit.
- `classes/form/description_tag_form.php` – mform for description tag create/edit with color validation.
- `classes/form/completion_defaults_form.php` – mform extending core's `defaultedit_form` for mimo completion overrides.
- `classes/form/activity_descriptions_form.php` – mform with dropdowns for assigning tags to activity types.
- `classes/section_image_manager.php` – section overview card image CRUD. File area `sectionimage` in course context, section ID as itemid. No DB table — file existence is truth. Methods: `get_image_url()`, `save_image()`, `delete_image()`, `has_image()`, `delete_all_for_course()`.
- `classes/form/section_image_form.php` – dynamic form (modal) with filepicker for uploading/changing section images (jpg, png, webp, svg). Extends `dynamic_form`.
- `amd/src/section_image_modal.js` – opens the section image dynamic form modal from overview card buttons in editing mode.
- `classes/external/get_tags.php` – webservice for fetching tags by course ID (returns profile-filtered tags).
- `classes/external/get_activity_descriptions.php` – webservice for fetching activity descriptions with tag data for modal.
- `classes/output/courseformat/content/activitychooserbutton.php` – tag chooser button (extends core_courseformat class, MDL-86337).
- `classes/output/courseformat/content/bulkedittools.php` – overrides core bulk edit tools to replace `availability` action with `mimoAvailability` (custom modal with Done option).
- `classes/output/courseformat/content/cm/visibility.php` – overrides core visibility dropdown to add "Done" option (Show/Hide/Stealth/Done). Always visible in mimo format.
- `classes/output/courseformat/content/cm.php` – course module data provider (keeps a defensive `$CFG->branch` check for future branch divergence).
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates. `content.php` detects overview mode (multi-section + no section selected) and returns lightweight section card data via `export_overview()`, switching to `overview.mustache`; in wall view provides `overviewurl`, `showoverviewlink`, `currentsectionname`. `cmitem.php` resolves tags through profile for card rendering.
- `templates/tag_management.mustache` – accordion-based tag admin UI, **imported badges (blue `bg-info`) and promote buttons for imported tags/profiles**.
- `templates/form_tag_preview.mustache` – read-only tag preview for course settings form (shows active tags for selected profile).
- `templates/local/content/activitychooserbutton.mustache` – tag chooser template (used by the activitychooserbutton output class).
- `templates/local/overview.mustache` – **Multi-section overview landing page** with section card grid, activity counts, and completion progress.
- `templates/local/content/cm.mustache` – course module template (uses core or custom chooser button).
- `templates/tagchooserbutton.mustache` – **legacy tag chooser template** — still included via the format's `cm.mustache` (pending rework to the core partial, after which it can be deleted).
- `templates/activitytype_chooser_modal.mustache` – modal body for activity type selection.
- `templates/activitytype_card.mustache` – activity type card with optional description tag pill.
- `templates/local/content/cm/availabilitymodal.mustache` – custom availability modal body with Show/Hide/Stealth/Done radio options for bulk edit.
- `templates/description_tags_list.mustache` – table view for description tags management page.
- `styles.scss` / `styles.css` – wall styling + profile-specific CSS + activity card styles + description tag pill styling.
- `amd/src/tagchooserbutton.js` – tag chooser modal handler with activity description fetching (handles both `data-section-id` and legacy `data-sectionnum` attributes until cm.mustache is reworked).
- `amd/src/mutations.js` – custom course editor mutations: `MimoMutations` class (cmDone, cmUndone, cmShow, cmHide, cmStealth with forced DOM reload) + `mimoAvailabilityHandler` (custom bulk availability modal with Done option).
- `amd/src/courseeditor_watcher.js` – reactive bridge: watches core course editor state and dispatches legacy DOM events for completion changes (used by tag_filter).
- `amd/src/init_courseeditor_watcher.js` – initializer for the courseeditor_watcher component.
- `amd/src/local/wall_state/wall_state.js` – per-section reactive store for wall UI state (filters, pagination, bulk mode, activity order).
- `amd/src/local/wall_state/mutations.js` – mutations for the wall state reactive (filter changes, pagination, bulk toggle, reorder).
- `amd/src/local/wall_state/events.js` – custom event types and dispatch helpers for wall state changes.
- `amd/src/tag_delete_confirm.js` – delete confirmation modals for tags (event capturing phase).
- `amd/src/tag_filter.js` – client-side tag filtering.
- `amd/src/activity_pagination.js` – responsive pagination with swipe.
- `amd/src/activity_dragdrop.js` – drag and drop reordering.
- `amd/src/profile_image_switcher.js` – swaps tag images/names/visibility in course form when activity profile changes.
- `amd/src/section_overview_actions.js` – section overview card delete + drag-and-drop reorder (editing mode). Uses `BaseComponent` + `DragDrop` from `core/reactive`. Whole card is drag surface; interactive children protected via `draggable="false"`. Calls `core_courseformat_update_course` with `section_delete` / `section_move_after`.
- `amd/src/description_tag_management.js` – description tag admin helpers.
- `amd/src/compact_nav.js` – compact secondary navigation: clones hidden secondary nav items into a three-dot header dropdown for students.
- `amd/src/distraction_free.js` – distraction-free mode toggle.
- `backup/moodle2/backup_format_mimo_plugin.class.php` – backup handler (tags **incl. scope**, profiles **incl. scope**, profile_tags, cmtags, files).
- `backup/moodle2/restore_format_mimo_plugin.class.php` – restore handler (**three-tier fingerprint/positional/create matching, imported profile creation with full overrides**, ID mapping, format option remapping).
- `db/install.xml` – **9** tables: tags, cmtags, desc_tags, actdesc, profiles, profile_tags, compdefs, **course_tags**, **cmdone**.
- `db/install.php` – creates default tags and default profiles on install, including per-profile tag overrides (explore images, develop name overrides), default description tags, and default activity descriptions for all activity types.
- `db/upgrade.php` – migration steps including profile introduction, selectedtags removal, and completion defaults table.
- `db/events.php` – observer registrations (module created/deleted, course deleted).
- `db/hooks.php` – hook registrations.
- `db/services.php` – web service definitions.
- `db/caches.php` – cache definitions (tagconfigurations, activitytagmappings, activity_descriptions).
- `tests/behat/tag_management.feature` – 6 scenarios for tag/tagset CRUD (create, edit, delete).
- `tests/behat/activity_tag_edit.feature` – 4 scenarios for changing/removing tags on activities via the module edit form.
- `tests/behat/style_variants.feature` – 2 scenarios for style variant selection during course creation.
- `tests/behat/style_management.feature` – style admin scenarios (legacy, may need update).
- `tests/tag_manager_test.php` – tag CRUD, assignment, caching, profile-based filtering.
- `tests/backup_restore_test.php` – 4 tests: basic cmtag restore, tag field preservation, profile restore.
- `tests/observer_test.php` – 6+ tests: auto-tag, no-assignment scenarios, rejection (invalid tag, profile-disabled tag), module deletion cleanup, course deletion cleanup.

## Open Questions / TODO Hooks
- ~~Teacher-side workflow for assigning/changing tags per activity~~ — **Implemented** via legacy `coursemodule_standard_elements` / `coursemodule_edit_post_actions` callbacks in `lib.php`. Teachers see a tag dropdown in the module edit form.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
