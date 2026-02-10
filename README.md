# Minimal Moodle Wall Course Format

A modern Moodle course format that displays activities in a card-based wall layout with tagging, filtering, and pagination.

## Features

- **Card Wall Layout** — Single-section responsive grid of activity cards
- **Tagsets & Tags** — Organize activities with tagsets (groups) containing tags with custom colors, images, and up to 3 recommended activity types
- **Tag-Based Filtering** — Students filter activities by tag
- **Pagination** — Responsive breakpoints with swipe support on mobile
- **Drag & Drop** — Reorder activities in editing mode
- **Guided Activity Creation** — Tag-based chooser recommends activity types per tag
- **Style Variants** — Multiple visual themes with per-style tag images
- **Backup & Restore** — Full backup of tagsets, tags, styles, and mappings; reuses existing data on same instance, creates new records on different instances
- **Accessibility** — WCAG 2.1 compliant with ARIA live regions and keyboard navigation

## Installation

1. Copy plugin to `course/format/minimoodlewall`
2. Visit Site administration > Notifications
3. Select "Minimal Moodle Wall" as course format when creating/editing a course

## Configuration

### Course Settings
- **Style Variant** — Visual theme (standard, compact, modern)
- **Tagset** — Which tagset to use (determines available tags)
- **Enable Filtering** — Toggle tag filter bar for students

### Tag Management (Site Admin > Courses > Manage tags)
- **Tagsets** — Grouping containers for tags (accordion UI)
- **Tags** — Name, background color, image placement, card/filter images, up to 3 activity types

### Style Management (Site Admin > Courses > Manage styles)
- Create style variants with per-tag card/filter images

## How It Works

1. Admin creates **tagsets** and **tags** (global, shared across courses)
2. Teacher selects a **tagset** and **tags** for their course
3. When adding activities, teachers pick a tag first — the chooser suggests matching activity types
4. Students see a **wall of cards** with optional tag filtering and pagination

## Testing

```bash
# PHPUnit (75 tests)
vendor/bin/phpunit --filter format_minimoodlewall

# Behat (10 feature files)
php admin/tool/behat/cli/run.php --tags=@format_minimoodlewall
```

## Requirements

- Moodle 5.1+
- PHP 8.2+

## License

GPL v3 or later
