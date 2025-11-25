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
  - `styles.scss` hosts shared wall styles + design variants (`default`, `starters`, `colorful`).
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
  - The core activity-adder Mustache is overridden. Teachers first see a grid of tag tiles; selecting one fires a modal that shows two pre-aligned quick-create activity shortcuts plus a link into the standard activity chooser. This keeps tagging mandatory and nudges teachers into curated combos.
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

## Guardrails for Future Agents
- Respect single-section assumption; avoid introducing multiple sections unless architecture is revisited.
- Never bypass tagset requirement—other logic assumes every course module can resolve a tag.
- When touching SVG/file handling, keep files in system context and reuse `tag_manager` helpers to avoid orphans.
- Update caches after any tag/tagset change; otherwise wall rendering will show stale logos/colors.
- Keep AI-facing docs (this file + README) updated when adding new options or data flows to minimize forgotten invariants.

## Quick File Map
- `lib.php` – course options, validation, navigation tweaks, pluginfile hook.
- `classes/tag_manager.php` – tag CRUD, file prep, caching, default palettes.
- `tag_management.php` – admin UI controller.
- `classes/form/tag*_form.php` – mform definitions for UI.
- `classes/output/courseformat/{content,section,cmitem}.php` – data providers for templates.
- `templates/local/content/*.mustache` – Mustache templates for wall, sections, cards.
- `styles.scss` / `styles.css` – wall styling + design variants.
- `amd/` – placeholder for JS (filter bar, quick create, etc.).

## Open Questions / TODO Hooks
- Teacher-side workflow for assigning/changing tags per activity is not implemented.
- JS filter bar enhancements (persist active filter, animate cards) planned but not shipped.
- Additional design variants + documentation for recommended SVG sizing still pending.

Keep this document synchronized with functional changes so future AI runs can reason about intent without re-deriving it from the whole codebase.
