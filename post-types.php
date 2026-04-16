<?php

/**
 * Register custom post types from cpt-toon/post-types.toon in the active theme.
 *
 * File format (columnar toon table without a leading key name):
 *
 *   [N]{post_type,label,singular,menu_icon,public,has_archive,show_in_rest,supports}:
 *     services,Services,Service,dashicons-store,1,1,1,"title,editor,thumbnail"
 *
 * Each non-empty line after the header is one post type row.
 * Values that contain commas must be wrapped in double-quotes.
 */

add_action('init', 'post_types_register_from_toon');

function post_types_register_from_toon()
{
  $toon_path = get_template_directory() . '/cpt-toon/post-types.toon';

  if (!file_exists($toon_path)) {
    return;
  }

  $raw = file_get_contents($toon_path);
  if ($raw === false || trim($raw) === '') {
    return;
  }

  $lines = array_values(array_filter(
    explode("\n", $raw),
    fn($l) => trim($l) !== ''
  ));

  if (empty($lines)) {
    return;
  }

  // Parse header: [N]{col1,col2,...}:
  if (!preg_match('/^\[(\d+)\]\{([^}]+)\}:\s*$/', trim($lines[0]), $m)) {
    return;
  }

  $cols = array_map('trim', explode(',', $m[2]));
  $rows = array_slice($lines, 1);

  foreach ($rows as $row_line) {
    $values = post_types_parse_csv_row(trim($row_line));

    if (count($values) !== count($cols)) {
      continue;
    }

    $entry = array_combine($cols, $values);

    if (empty($entry['post_type']) || empty($entry['label'])) {
      continue;
    }

    $post_type = sanitize_key($entry['post_type']);
    $label     = $entry['label'];
    $singular  = !empty($entry['singular']) ? $entry['singular'] : $label;

    $supports = isset($entry['supports']) && $entry['supports'] !== ''
      ? array_map('trim', explode(',', $entry['supports']))
      : ['title', 'editor', 'thumbnail'];

    $args = [
      'label'        => $label,
      'public'       => !empty($entry['public']),
      'has_archive'  => !empty($entry['has_archive']),
      'show_in_rest' => !empty($entry['show_in_rest']),
      'menu_icon'    => !empty($entry['menu_icon']) ? $entry['menu_icon'] : null,
      'supports'     => $supports,
      'labels'       => [
        'name'          => $label,
        'singular_name' => $singular,
        'add_new_item'  => sprintf(__('Add %s', 'toon-config'), $singular),
        'edit_item'     => sprintf(__('Edit %s', 'toon-config'), $singular),
        'all_items'     => sprintf(__('All %s', 'toon-config'), $label),
        'menu_name'     => $label,
      ],
    ];

    register_post_type($post_type, $args);
  }
}

/**
 * Parse a single CSV row, respecting double-quoted fields that may contain commas.
 *
 * @param  string $line  Raw CSV row string
 * @return array         Ordered array of field values
 */
function post_types_parse_csv_row($line)
{
  return toon_parse_csv_row($line);
}
