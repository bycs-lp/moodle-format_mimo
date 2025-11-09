# Minimal Moodle Wall Course Format

A minimal course format for Moodle that displays all activities in a single section using a card-based wall layout.

## Features

- **Single Section**: Uses only one section to keep things simple
- **Card Layout**: Displays activities in a responsive card grid
- **Mustache Templates**: Uses modern Moodle templating system
- **Moodle 4.0+ Compatible**: Supports the component-based course editor

## Installation

1. Copy this plugin directory to `course/format/minimoodlewall`
2. Visit Site administration > Notifications to complete the installation
3. Create a new course or edit an existing course
4. Select "Minimal Moodle Wall" as the course format

## File Structure

```
format_minimoodlewall/
├── version.php                     # Plugin version and dependencies
├── lib.php                         # Main format class
├── format.php                      # Entry point for displaying the course
├── lang/
│   └── en/
│       └── format_minimoodlewall.php   # English language strings
├── classes/
│   └── output/
│       ├── renderer.php            # Main renderer class
│       └── courseformat/
│           ├── content.php         # Content output class
│           └── content/
│               ├── section.php     # Section output class
│               └── section/
│                   └── cmitem.php  # Course module item output class
└── templates/
    └── local/
        ├── content.mustache        # Main content template
        └── content/
            ├── section.mustache    # Section template (displays activities)
            └── section/
                └── cmitem.mustache # Activity item template
```

## How It Works

1. **Format Class** (`lib.php`): Defines the format behavior, restricts to one section
2. **Output Classes**: Extend core classes to use custom templates
3. **Mustache Templates**: Override specific parts to display activities in a card grid
4. **Responsive Grid**: Uses Bootstrap 5 grid system for responsive layout

## Customization

### Modify the Activity Layout

Edit `templates/local/content/section.mustache` to change the grid layout:
- `col-12 col-sm-6 col-md-4 col-lg-3` creates 4 columns on large screens
- Change these classes to adjust the number of columns at different breakpoints

### Add Custom Styles

Create a `styles.css` file in the plugin root and add custom CSS for the `.minimoodlewall-activities` class.

### Modify Activity Cards

Edit `templates/local/content/section/cmitem.mustache` to customize how each activity is displayed within the card.

## Development Notes

This plugin follows Moodle's course format architecture:
- Extends `core_courseformat\base` for the format class
- Uses output classes and mustache templates (Moodle 4.0+ pattern)
- Overrides templates using mustache blocks pattern
- Compatible with the course editor and reactive components

## Documentation References

- [Course Format Plugin Development](https://moodledev.io/docs/5.1/apis/plugintypes/format)
- [Moodle Templates](https://moodledev.io/docs/guides/templates)
- [Course Format Output Classes](https://moodledev.io/docs/5.1/apis/plugintypes/format#format-output-classes-and-templates)

## License

GPL v3 or later

## Author

Your Name - 2025
