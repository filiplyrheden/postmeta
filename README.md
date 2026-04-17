# Toon Config

A WordPress plugin for defining custom post meta fields and custom post types via `.toon` configuration files kept in your theme.

**How it works:** The plugin reads optional config files from your active theme at runtime. The theme owns all configuration; the plugin stays generic and can be shared across projects without modification.

| Config file | What it does |
|---|---|
| `{theme}/postmeta/post-meta.toon` | Registers metaboxes with custom fields in the post editor |
| `{theme}/cpt-toon/post-types.toon` | Registers custom post types |
| `{theme}/cpt-toon/taxonomies.toon` | Registers custom taxonomies |

All files are optional — the plugin loads gracefully if any are missing.

## Installation

1. Download `toon-config.zip` from the [latest release](../../releases/latest) 
2. Place the `toon-config` folder in `wp-content/plugins/`
3. Activate the plugin in **WP Admin → Plugins**
4. Create `postmeta/post-meta.toon` inside your theme to add meta fields (see below)
5. Optionally create `cpt-toon/post-types.toon` inside your theme to register custom post types (see below)
6. Optionally create `cpt-toon/taxonomies.toon` inside your theme to register custom taxonomies (see below)

## About the .toon format

`.toon` files use a simple indentation-based syntax. Two rules cover most of it:

- Indentation creates nesting (like YAML)
- `[N]:` at the start of a line declares that N items follow

The full syntax is shown in the examples below.

---

## Custom post types — cpt-toon/post-types.toon

Create `{theme}/cpt-toon/post-types.toon` to register custom post types. Adding a row is all that is needed — no PHP required.

### File format

The file is a single columnar table. The first line declares the column names and row count; each subsequent non-empty line is one post type.

```
[N]{post_type,label,singular,menu_icon,public,has_archive,show_in_rest,supports}:
  slug,Plural label,Singular label,dashicons-icon,1,1,1,"title,editor,thumbnail"
```

Values that contain commas (e.g. the `supports` list) must be wrapped in double-quotes.

### Column reference

| Column | Required | Description |
|---|---|---|
| `post_type` | yes | The post type slug, e.g. `services`. Must be unique. |
| `label` | yes | Plural display name shown in the admin menu, e.g. `Services` |
| `singular` | yes | Singular display name used in button labels, e.g. `Service` |
| `menu_icon` | no | Dashicons class or URL, e.g. `dashicons-store` |
| `public` | no | `1` to make the post type publicly accessible, `0` to hide it |
| `has_archive` | no | `1` to enable an archive page at `/{post_type}/` |
| `show_in_rest` | no | `1` to enable the block editor and REST API support |
| `supports` | no | Comma-separated list of features: `title,editor,thumbnail`, etc. Wrap in quotes if more than one value. |

`public`, `has_archive`, and `show_in_rest` treat any non-empty value as truthy — use `1` to enable and `0` (or leave blank) to disable.

### Example

```
[2]{post_type,label,singular,menu_icon,public,has_archive,show_in_rest,supports}:
  services,Services,Service,dashicons-store,1,1,1,"title,editor,thumbnail"
  team,Team,Team member,dashicons-groups,1,0,1,"title,thumbnail,excerpt"
```

### After adding a post type

Go to **WP Admin → Settings → Permalinks** and click **Save Changes**. This flushes WordPress rewrite rules so the new post type's URLs work correctly.

### Querying a custom post type in templates

```php
$query = new WP_Query([
    'post_type'      => 'services',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

while ($query->have_posts()) {
    $query->the_post();
    the_title();
}
wp_reset_postdata();
```

---

## Custom taxonomies — cpt-toon/taxonomies.toon

Create `{theme}/cpt-toon/taxonomies.toon` to register custom taxonomies. No PHP required.

### File format

The file is a single columnar table. The first line declares the column names and row count; each subsequent non-empty line is one taxonomy.

```
[N]{name,label,singular,slug,post_types,hierarchical,show_admin_column}:
  slug,Plural label,Singular label,url-slug,post_type,1,1
```

Values that contain commas (e.g. a list of post types) must be wrapped in double-quotes.

### Column reference

| Column | Required | Description |
|---|---|---|
| `name` | yes | The taxonomy slug, e.g. `style`. Must be unique. |
| `label` | yes | Plural display name, e.g. `Styles` |
| `singular` | yes | Singular display name, e.g. `Style` |
| `slug` | yes | URL rewrite slug, e.g. `style` |
| `post_types` | yes | Post type(s) to attach the taxonomy to. Wrap multiple in quotes: `"post,item"` |
| `hierarchical` | no | `1` for category-style (parent/child terms), `0` for tag-style |
| `show_admin_column` | no | `1` to show a column for this taxonomy in the post list |

### Example

```
[2]{name,label,singular,slug,post_types,hierarchical,show_admin_column}:
  style,Styles,Style,style,item,1,1
  genre,Genres,Genre,genre,"book,movie",1,1
```

### After adding a taxonomy

Go to **WP Admin → Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

### Querying by taxonomy in templates

```php
$query = new WP_Query([
    'post_type'      => 'item',
    'posts_per_page' => -1,
    'tax_query'      => [
        [
            'taxonomy' => 'style',
            'field'    => 'slug',
            'terms'    => 'minimalist',
        ],
    ],
]);

while ($query->have_posts()) {
    $query->the_post();
    the_title();
}
wp_reset_postdata();
```

Retrieve the terms assigned to a post:

```php
$terms = get_the_terms(get_the_ID(), 'style');
if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
        echo esc_html($term->name);
    }
}
```

---

## Meta fields — postmeta/post-meta.toon

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
| `button` | Two inputs — a label and a URL — stored as an associative array |
| `select` | Dropdown with predefined options (stores the selected value) |
| `repeater` | Repeatable group of subfields |

`rich_text` and `tinymce` are interchangeable. Both render the full WordPress visual editor and save content through `wp_kses_post`. Use whichever name reads more clearly in your `.toon` file.

Select fields require an `options` definition with columns `value` and `label`. `value` is what gets stored in the database; `label` is what the editor sees in the dropdown. Only values declared in `options` can be saved — anything else is rejected on save.

```
- type: select
  name: logo
  label: Logotyp
  options[8]{value,label}:
    acme, ACME Corp
    globex, Globex
    initech, Initech
    umbrella, Umbrella Corp
    wayne, Wayne Enterprises
    stark, Stark Industries
    oscorp, OsCorp
    cyberdyne, Cyberdyne Systems
```

Option labels that contain a comma must be wrapped in double-quotes:

```
options[2]{value,label}:
  classic, Classic
  "bold,italic", Bold & Italic
```

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
    fields[5]:
      -
        type: image
        name: hero_image
        label: Hero image
      -
        type: tinymce
        name: intro_text
        label: Intro text
      -
        type: button
        name: cta_button
        label: Call to action
      -
        type: select
        name: partner_logo
        label: Partner logo
        options[3]{value,label}:
          acme, ACME Corp
          globex, Globex
          initech, Initech
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

---

## Theme integration

### Rendering fields in order

Use `post_meta_get_ordered_sections()` to render sections in the user-defined drag-drop order:

```php
$section_map = [
    'hero_image' => 'partials/front/hero-image',
    'intro_text' => 'partials/front/intro-text',
];

foreach (post_meta_get_ordered_sections($section_map, 'front-page') as $field_name) {
    get_template_part($section_map[$field_name]);
}
```

The second argument must match the group slug: the group name lowercased and hyphenated (e.g. `"Front page"` → `"front-page"`).

### Retrieving field values

Fields are stored as standard WordPress post meta. To avoid key collisions between groups, all fields are **prefixed with their group slug** when saved.

| Group name | Field name | Database key |
|---|---|---|
| `Front page` | `intro_text` | `front-page_intro_text` |
| `About` | `about_hero` | `about_about_hero` |

Use the helper function to retrieve a value without writing the prefix manually:

```php
// post_meta_get( $group, $field, $post_id = null )
$intro = post_meta_get('Front page', 'intro_text');
```

Or use `get_post_meta()` directly with the full prefixed key:

```php
// Scalar field
$intro = get_post_meta(get_the_ID(), 'front-page_intro_text', true);

// rich_text / tinymce field (HTML content)
$body = get_post_meta(get_the_ID(), 'front-page_intro_text', true);
echo wp_kses_post($body);

// Image field (stores attachment ID)
$image_id = get_post_meta(get_the_ID(), 'front-page_hero_image', true);
echo wp_get_attachment_image($image_id, 'full');

// Repeater field (stores array of rows)
$contacts = get_post_meta(get_the_ID(), 'front-page_contacts', true);
foreach ($contacts as $contact) {
    echo esc_html($contact['name']);
    echo esc_html($contact['email']);
}

// Button field (stores ['label' => string, 'url' => string])
$btn = get_post_meta(get_the_ID(), 'front-page_cta_button', true);
if (!empty($btn['url'])) {
    printf(
        '<a href="%s">%s</a>',
        esc_url($btn['url']),
        esc_html($btn['label'])
    );
}

// Select field (stores the selected value string)
$logo = get_post_meta(get_the_ID(), 'front-page_partner_logo', true);
if ($logo) {
    get_template_part('partials/logos/' . $logo);
}
```
