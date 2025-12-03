# Minimal Moodle Wall Course Format

A modern course format for Moodle that displays activities in a card-based wall layout with tagging, filtering, and pagination features.

## Features

### Core Features
- **Single Section Design**: Uses one section to keep navigation simple
- **Card Layout**: Responsive grid layout for activity cards
- **Tagging System**: Organize activities with custom tags (tagsets and tags)
- **Tag-Based Filtering**: Students can filter activities by tags
- **Pagination**: Automatic pagination with responsive breakpoints (XL: 8 items, LG: 6, MD: 4, SM: 3 per page)
- **Drag & Drop**: Reorder activities in editing mode
- **Guided Activity Creation**: Tag-based chooser with 1-3 recommended activity types per tag, each with custom instructional descriptions
- **Design Variants**: Multiple visual themes (standard, compact, modern)

### Technical Features
- **Performance Optimized**: Application-level caching with granular invalidation
- **Accessibility**: WCAG 2.1 compliant with ARIA live regions, semantic roles, and screen reader support
- **Moodle 5.1+ Compatible**: Uses modern course editor and reactive components
- **Touch Gestures**: Swipe support for pagination on mobile devices
- **Bulk Mode Support**: Pagination automatically disables during bulk operations

## Installation

1. Copy this plugin directory to `course/format/minimoodlewall`
2. Visit Site administration > Notifications to complete the installation
3. Create a new course or edit an existing course
4. Select "Minimal Moodle Wall" as the course format

## Configuration

### Course Settings
- **Design Variant**: Choose between standard, compact, or modern layouts
- **Enable Filtering**: Toggle tag-based filtering on/off
- **Tagset**: Select which tagset to use for this course

### Tag Management
1. Navigate to course > Manage tags
2. Create tagsets to organize your tags
3. Create tags with:
   - Name and background color
   - Up to 3 activity types (primary, secondary, tertiary)
   - Card image (required): Displayed on activity cards
   - Filter image (optional): Displayed in filter bar (falls back to tag name text)

## File Structure

```
format_minimoodlewall/
├── version.php                     # Plugin version and dependencies
├── lib.php                         # Main format class
├── format.php                      # Entry point for displaying the course
├── lang/en/                        # Language strings
├── classes/
│   ├── tag_manager.php             # Tag and tagset CRUD operations
│   ├── description_tag_manager.php # Activity-tag associations
│   ├── observer.php                # Event observers for auto-tagging
│   ├── external/                   # Web service APIs
│   │   └── get_activity_descriptions.php
│   ├── form/                       # Moodle forms
│   │   ├── tag_form.php
│   │   └── tagset_form.php
│   └── output/                     # Output renderers and classes
├── amd/src/                        # JavaScript AMD modules (ES6)
│   ├── activity_pagination.js      # Pagination with swipe support
│   ├── tag_filter.js               # Tag filtering logic
│   ├── tagchooserbutton.js         # Activity creation with tags
│   ├── activity_dragdrop.js        # Drag and drop reordering
│   ├── description_tag_management.js
│   └── tag_delete_confirm.js
├── templates/                      # Mustache templates
└── tests/                          # PHPUnit and Behat tests
    ├── behat/                      # Acceptance tests
    └── *.php                       # Unit tests
```

## JavaScript Architecture

All modules follow consistent patterns:
- **ES6 modules** compiled with Grunt/Rollup
- **JSDoc documentation** for all functions
- **Error handling** with `Notification.exception()`
- **Named constants** instead of magic numbers
- **Accessibility** with live region announcements

### Key Modules
- `activity_pagination.js`: Responsive pagination (11 breakpoint/timing constants)
- `tag_filter.js`: Client-side filtering with activity reordering
- `tagchooserbutton.js`: Enhanced activity chooser with tag selection

## Caching Strategy

The plugin uses Moodle's application cache with granular invalidation:
- **Tag/tagset changes**: Invalidates affected course sections only
- **Activity updates**: Invalidates specific section cache
- **Performance**: 60%+ reduction in database queries

Cache is automatically managed - no manual purging needed in production.

## Testing

### PHPUnit Tests
```bash
vendor/bin/phpunit --testsuite format_minimoodlewall_testsuite
```

Tests include:
- Tag/tagset CRUD operations
- Description-tag associations
- Cache behavior
- External API validation
- Defensive tests for Moodle core constant mappings

### Behat Tests
```bash
php admin/tool/behat/cli/run.php --tags=@format_minimoodlewall
```

35 scenarios covering:
- Course creation and display
- Tag management UI
- Filtering functionality
- Pagination controls
- Drag and drop
- Activity creation with auto-tagging

## Development Guidelines

### Adding New Features
1. Follow Moodle coding standards (use `local_moodlecheck` and `local_codechecker`)
2. Add JSDoc comments to all JavaScript functions
3. Include PHPUnit tests for PHP logic
4. Add Behat tests for user-facing features
5. Update language strings in `lang/en/format_minimoodlewall.php`

### JavaScript Development
```bash
# Compile AMD modules
grunt amd

# Watch for changes
grunt watch
```

### Cache Management in Development
Static cache properties in tests require explicit reset:
```php
protected function tearDown(): void {
    $reflection = new \ReflectionClass(tag_manager::class);
    $property = $reflection->getProperty('tagcache');
    $property->setAccessible(true);
    $property->setValue(null, null);
    parent::tearDown();
}
```

## Accessibility Features

- **Live Regions**: Announce filter and pagination changes to screen readers
- **ARIA Labels**: Contextual labels for icon-only buttons
- **Keyboard Navigation**: Full keyboard support for all interactions
- **Semantic HTML**: Proper use of `<nav>`, `role="status"`, `aria-pressed`

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Touch gestures on mobile devices
- Graceful degradation for older browsers (fallback bulk mode detection)

## Known Limitations

- Single section only (by design)
- Reactive editor features require Moodle 5.1+
- Filter images should be SVG for best quality

## Troubleshooting

### Tests Failing
- **Cache contamination**: Ensure tearDown() resets static properties
- **Behat failures**: Run `php admin/tool/behat/cli/init.php` after JS changes

### JavaScript Not Loading
- Purge all caches: Site administration > Development > Purge all caches
- Recompile: `grunt amd` in plugin directory

### Pagination Not Working
- Check browser console for errors
- Verify reactive editor is available (Moodle 5.1+)
- Falls back to MutationObserver if reactive features unavailable

## Documentation References

- [Course Format Plugin Development](https://moodledev.io/docs/apis/plugintypes/format)
- [Moodle Templates](https://moodledev.io/docs/guides/templates)
- [JavaScript Modules](https://moodledev.io/docs/guides/javascript)
- [Accessibility](https://moodledev.io/docs/guides/accessibility)

## License

GPL v3 or later

## Author

Your Name - 2025
