# Post Meta

A WordPress plugin for defining and managing custom post meta fields via a `.toon` configuration file kept in your theme.

## Installation

1. Place the `postmeta` folder in `wp-content/plugins/`
2. Activate the plugin in **WP Admin → Plugins**
3. Create `postmeta/post-meta.toon` inside your theme (see configuration below)

The plugin reads field definitions from `{theme}/postmeta/post-meta.toon` at runtime, so the theme owns the configuration and the plugin stays generic.

## Theme integration

Use `post_meta_get_ordered_sections()` in your templates to render sections in the user-defined order:

```php
$section_map = [
    'hero_image' => 'partials/front/hero-image',
    'intro_text' => 'partials/front/intro-text',
];

foreach (post_meta_get_ordered_sections($section_map, 'front-page') as $field_name) {
    get_template_part($section_map[$field_name]);
}
```

The second argument must match the group slug (the group name lowercased and hyphenated, e.g. `"Front page"` → `"front-page"`).

## Configuration — post-meta.toon

Field groups are defined in `{theme}/postmeta/post-meta.toon`. The file starts with a list count declaration and contains one or more groups.

### Group options

| Key | Type | Description |
|---|---|---|
| `group` | string | Display name shown as the metabox title |
| `sortable` | `true` / `false` | Whether fields can be reordered via drag and drop. Defaults to `true`. |
| `location` | object | Controls where the metabox appears (see below) |
| `fields` | list | The fields belonging to this group |

### Location options

| Key | Type | Description |
|---|---|---|
| `screens` | list | Post types to show the metabox on, e.g. `page` |
| `front_page` | `1` | Restrict to the page set as the static front page |
| `template` | string | Restrict to pages using a specific template file, e.g. `about.php` |

### Field types

| Type | Description |
|---|---|
| `text` | Single-line text input |
| `email` | Email input |
| `url` | URL input |
| `textarea` | Multi-line text area |
| `rich_text` | WordPress TinyMCE editor |
| `tinymce` | Alias for `rich_text` — identical behaviour |
| `image` | Image picker (stores attachment ID) |
| `repeater` | Repeatable group of subfields |

`rich_text` and `tinymce` are interchangeable. Both render the full WordPress visual editor (TinyMCE) and save content through `wp_kses_post`. Use whichever name reads more clearly in your `.toon` file.

Repeater fields require a `subfields` definition with columns `type`, `name`, and `label`.

### Example

```
[2]:
  -
    group: Front page
    sortable: false
    location:
      screens[1]: page
      front_page: 1
    fields[3]:
      -
        type: image
        name: hero_image
        label: Hero image
      -
        type: tinymce
        name: intro_text
        label: Intro text
      -
        type: repeater
        name: contacts
        label: Contacts
        subfields[3]{type,name,label}:
          text,name,Name
          email,email,Email
          url,website,Website
  -
    group: About
    sortable: true
    location:
      screens[1]: page
      template: about.php
    fields[1]:
      -
        type: image
        name: about_hero
        label: About hero
```

## Retrieving field values in templates

Fields are stored as standard WordPress post meta. To avoid key collisions between groups, all fields are **prefixed with their group slug** when saved to the database.

The group slug is the group name lowercased and hyphenated — the same value used in `post_meta_get_ordered_sections()`.

| Group name | Field name | Database key |
|---|---|---|
| `Front page` | `intro_text` | `front-page_intro_text` |
| `About` | `about_hero` | `about_about_hero` |

Use the helper function `post_meta_get()` to avoid writing the prefix manually:
```php
// post_meta_get( $group, $field, $post_id = null )
$intro = post_meta_get('front page', 'intro_text');
```

Or use `get_post_meta()` directly with the full prefixed key:
```php
// Scalar field
$intro = get_post_meta(get_the_ID(), 'front_page_intro_text', true);

// rich_text / tinymce field (HTML content — echo without escaping)
$body = get_post_meta(get_the_ID(), 'front_page_intro_text', true);
echo wp_kses_post($body);

// Image field (stores attachment ID)
$image_id = get_post_meta(get_the_ID(), 'front_page_hero_image', true);
echo wp_get_attachment_image($image_id, 'full');

// Repeater field (stores array of rows)
$contacts = get_post_meta(get_the_ID(), 'front_page_contacts', true);
foreach ($contacts as $contact) {
    echo esc_html($contact['name']);
    echo esc_html($contact['email']);
}
```
