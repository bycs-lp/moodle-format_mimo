# Behat Tests for format_minimoodlewall

This directory contains comprehensive Behat acceptance tests for the Minimal Moodle Wall course format.

## Test Coverage

### 1. tag_management.feature
Tests for admin tag and tagset management interface:
- Creating tag sets
- Adding tags to tag sets
- Editing existing tags
- Deleting tags
- Access control (teachers cannot access)

### 2. course_creation.feature
Tests for course creation workflow:
- Creating a course with minimoodlewall format
- Tag set selection (required)
- Tag set locked after creation
- Activities displayed in wall format

### 3. tag_chooser.feature
Tests for the tag-based activity chooser:
- Tag dropdown visibility in editing mode
- Creating activities with pre-selected tag and type
- Opening standard activity chooser
- Automatic tag assignment after creation
- Multiple activities with different tags

### 4. tag_filtering.feature
Tests for tag filtering functionality:
- Filter bar visibility (when enabled)
- Usage counts display
- Filtering activities by tag
- Clearing filters (Show All)
- Active filter button highlighting
- Filter bar hidden when disabled
- Teacher view mode filtering

### 5. pagination.feature
Tests for activity pagination:
- Pagination controls appear (>8 activities)
- Navigation between pages
- Previous/next button functionality
- Pagination with filtering
- No controls when ≤8 activities

### 6. drag_and_drop.feature
Tests for activity reordering:
- Activities draggable in editing mode
- Not draggable when not editing
- Drag operation reorders activities
- Visual feedback (cursor, highlighting)

### 7. design_variants.feature
Tests for design customization:
- Classic design variant
- Light design variant
- Dark design variant
- Changing design in course settings

## Running the Tests

### Run all format_minimoodlewall tests:
```bash
bin/moodle-docker-compose exec webserver php admin/tool/behat/cli/run.php \
  --tags=@format_minimoodlewall
```

### Run specific feature:
```bash
bin/moodle-docker-compose exec webserver php admin/tool/behat/cli/run.php \
  --tags=@format_minimoodlewall \
  tests/behat/tag_chooser.feature
```

### Initialize Behat (if needed):
```bash
bin/moodle-docker-compose exec webserver php admin/tool/behat/cli/init.php
```

## Test Data Generators

The format includes custom Behat generators for creating test data:

### Tagsets:
```gherkin
Given the following "format_minimoodlewall > tagsets" exist:
  | name         | description     |
  | Default Tags | Default tag set |
```

### Tags:
```gherkin
Given the following "format_minimoodlewall > tags" exist:
  | tagset       | name    | description | activitytype1 | activitytype2 |
  | Default Tags | Reading | Materials   | page          | book          |
```

### Course Module Tags:
```gherkin
Given the following "format_minimoodlewall > cmtags" exist:
  | cm           | tag     |
  | Assignment 1 | Reading |
```

## Notes

- All tests are marked with `@javascript` as they test interactive features
- Tests use `@format` and `@format_minimoodlewall` tags for filtering
- Generators automatically resolve names to IDs (tagset names → tagsetid, etc.)
- Some drag-and-drop tests may require custom step definitions

## Extending Tests

When adding new features, consider:
1. Add new .feature file in this directory
2. Update generators in `tests/generator/lib.php` if needed
3. Add Behat step definitions in `behat_format_minimoodlewall_generator.php` if custom steps required
4. Update this README with the new test coverage
