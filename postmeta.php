<?php
/*
 * Plugin Name: Post Meta
 * Description: Custom post meta fields defined via post-meta.toon
 * Version: 1.0.0
 */

require __DIR__ . '/repeater.php';
require __DIR__ . '/toon.php';

/**
 * Returns field names in saved order, filtered to only known keys.
 * Used by theme templates to retrieve fields in their user-defined order.
 *
 * @param array  $section_map  Map of field_name => anything (only keys are used)
 * @param string $group_slug   The group slug used when saving the order
 * @return array
 */
function post_meta_get_ordered_sections($section_map, $group_slug)
{
  $post_id = get_the_ID();
  $saved   = get_post_meta($post_id, 'post_meta_order_' . $group_slug, true);

  return (!empty($saved))
    ? array_filter(explode(',', $saved), fn($k) => isset($section_map[$k]))
    : array_keys($section_map);
}

if (is_admin()) :

  $post_meta_model = parse_toon_file(get_template_directory() . '/postmeta/post-meta.toon');

  if ($post_meta_model) :

    $post_meta_fields = [];

    /**
     * Render fields
     */
    function post_meta_boxes_html($post, $group)
    {

      global $post_meta_model;
      $group_fields = [];
      $group_slug   = sanitize_title($group['title']);
      $sortable     = $group['args']['sortable'] ?? true;

      if (is_array($post_meta_model) && !empty($post_meta_model)) :
        foreach ($post_meta_model as $post_meta_group) :
          if ($post_meta_group['group'] == $group['title'] && isset($post_meta_group['fields'])) :
            $group_fields = $post_meta_group['fields'];
          endif;
        endforeach;
      endif;

      // Apply saved field order
      $saved_order = get_post_meta($post->ID, 'post_meta_order_' . $group_slug, true);
      if (!empty($saved_order)) :
        $order = array_map('sanitize_key', explode(',', $saved_order));
        usort($group_fields, function ($a, $b) use ($order) {
          $ia = array_search($a['name'], $order);
          $ib = array_search($b['name'], $order);
          if ($ia === false) $ia = PHP_INT_MAX;
          if ($ib === false) $ib = PHP_INT_MAX;
          return $ia - $ib;
        });
      endif;

      if (is_array($group_fields) && !empty($group_fields)) :
        if ($sortable) :
          print '<ul class="post-meta-sortable" data-group="' . esc_attr($group_slug) . '">';
          print '<input type="hidden" name="post_meta_order[' . esc_attr($group_slug) . ']" class="post-meta-order-input" />';
        else :
          print '<ul>';
        endif;

        foreach ($group_fields as $field) :
          $type = $field['type'];
          $name = $group_slug . '_' . $field['name'];

          if (isset($type) && isset($name)) :
            $label = isset($field['label']) ? $field['label'] : $name;
            $placeholder = isset($field['placeholder']) ? $field['placeholder'] : "";
            $rows = isset($field['rows']) ? $field['rows'] : 3;

            $field_data = get_post_meta($post->ID, $name, true);

            print '<li class="post-meta-field-row" data-field="' . esc_attr($name) . '">';
            if ($sortable) :
              print '<span class="post-meta-drag-handle dashicons dashicons-menu" title="Drag and drop to change order of sections"></span>';
            endif;
            print '<p class="post-attributes-label-wrapper page-template-label-wrapper"><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label></p>';

            if ($type == 'text' || $type == 'email' || $type == 'url') :
              print '<input type="' . esc_attr($type) . '" placeholder="' . esc_attr($placeholder) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($field_data) . '" class="large-text" />';
            elseif ($type == 'textarea') :
              print '<textarea placeholder="' . esc_attr($placeholder) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="large-text" rows="' . (int) $rows . '">' . esc_textarea($field_data) . '</textarea></p>';
            elseif ($type == 'rich_text') :
              $editor_id = sanitize_key($name);
              wp_editor($field_data, $editor_id, [
                'textarea_name' => $name,
                'textarea_rows' => $rows,
              ]);
            elseif ($type == 'image') :
              $attachment_id = absint($field_data);
              $image_src = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
              print '<div class="post-meta-image-field" style="margin-bottom:8px;">';
              print '<input type="hidden" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . $attachment_id . '" />';
              print '<div id="' . esc_attr($name) . '_preview" style="margin-bottom:8px;">';
              if ($image_src) :
                print '<img src="' . esc_url($image_src) . '" style="max-width:150px;display:block;" />';
              endif;
              print '</div>';
              print '<button type="button" class="button post-meta-upload-image" data-input="' . esc_attr($name) . '">' . ($image_src ? __('Change image', 'postmeta') : __('Choose image', 'postmeta')) . '</button>';
              if ($image_src) :
                print ' <button type="button" class="button post-meta-remove-image" data-input="' . esc_attr($name) . '">' . __('Remove', 'postmeta') . '</button>';
              endif;
              print '</div>';
            elseif ($type == 'repeater') :
              render_repeater($post, $field);
            endif;

            print '</li>';
          endif;

        endforeach;

        print '</ul>';
      endif;
    }

    /**
     * Render Meta Boxes
     */
    function post_meta_add_custom_box()
    {

      global $post_meta_model;

      if (is_array($post_meta_model) && !empty($post_meta_model)) :

        global $post;

        foreach ($post_meta_model as $post_meta_group) :

          $group_label = trim($post_meta_group['group']);
          $group_id = "post_meta_group_" . sanitize_title($group_label);
          $group_screens = $post_meta_group['location']['screens'];
          $group_front_page = $post_meta_group['location']['front_page'] ?? 0;
          $group_template = $post_meta_group['location']['template'] ?? '';

          $post_meta_box_valid = true;

          if ($group_front_page) :
            $front_page_id = get_option('page_on_front');
            if ($post->ID != $front_page_id) :
              $post_meta_box_valid = false;
            endif;
          endif;

          if ($group_template) :
            $template = get_post_meta($post->ID, '_wp_page_template', true);
            if (!str_contains($template, $group_template)) {
              $post_meta_box_valid = false;
            }
          endif;

          $group_sortable = ($post_meta_group['sortable'] ?? 'true') !== 'false';

          if (is_array($group_screens) && $post_meta_box_valid) :
            add_meta_box($group_id, $group_label, 'post_meta_boxes_html', $group_screens, 'normal', 'high', ['sortable' => $group_sortable]);
          endif;

        endforeach;

      endif;
    }
    add_action('add_meta_boxes', 'post_meta_add_custom_box');

    /**
     * Enqueue media uploader scripts
     */
    function post_meta_enqueue_scripts()
    {
      wp_enqueue_media();
      wp_enqueue_style('post-meta', plugin_dir_url(__FILE__) . 'post-meta.css', [], null);
      wp_enqueue_script('post-meta', plugin_dir_url(__FILE__) . 'post-meta.js', ['jquery', 'jquery-ui-sortable'], null, true);
    }
    add_action('admin_enqueue_scripts', 'post_meta_enqueue_scripts');

    /**
     * Prepare fields for saving
     */
    if (is_array($post_meta_model) && !empty($post_meta_model)) :

      foreach ($post_meta_model as $post_meta_group) :

        $group_fields = $post_meta_group['fields'];

        if (is_array($group_fields)) :
          foreach ($group_fields as $group_field) :
            array_push($post_meta_fields, [
              'name'      => $group_field['name'],
              'type'      => $group_field['type'],
              'subfields' => $group_field['subfields'] ?? [],
              'group_slug' => sanitize_title($post_meta_group['group']),
            ]);
          endforeach;
        endif;

      endforeach;

    endif;

    /**
     * Save fields
     */
    if (is_array($post_meta_fields) && !empty($post_meta_fields)) :

      function post_meta_save_data($post_id)
      {
        global $post_meta_fields;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save field order per group
        if (isset($_POST['post_meta_order']) && is_array($_POST['post_meta_order'])) :
          foreach ($_POST['post_meta_order'] as $slug => $order_string) :
            $clean_slug  = sanitize_key($slug);
            $order_items = array_map('sanitize_key', explode(',', $order_string));
            update_post_meta($post_id, 'post_meta_order_' . $clean_slug, implode(',', $order_items));
          endforeach;
        endif;

        foreach ($post_meta_fields as $post_meta_field) :
          $name = $post_meta_field['name'];
          $type = $post_meta_field['type'];
          $group_slug = $post_meta_field['group_slug'];
          $prefixed_name = $group_slug . '_' . $name;

          if ($type === 'repeater') :
            $subfields = $post_meta_field['subfields'];
            $raw = isset($_POST[$name]) && is_array($_POST[$name]) ? $_POST[$name] : [];
            $data = [];
            foreach ($raw as $row) :
              if (!is_array($row)) continue;
              $sanitized = [];
              foreach ($subfields as $sub) :
                $val = $row[$sub['name']] ?? '';
                if ($sub['type'] === 'email') :
                  $sanitized[$sub['name']] = sanitize_email($val);
                elseif ($sub['type'] === 'image') :
                  $sanitized[$sub['name']] = absint($val);
                else :
                  $sanitized[$sub['name']] = sanitize_text_field($val);
                endif;
              endforeach;
              $data[] = $sanitized;
            endforeach;
            update_post_meta($post_id, $prefixed_name, $data);
          elseif (array_key_exists($name, $_POST)) :
            if ($type === 'image') :
              $value = absint($_POST[$name]);
            elseif ($type === 'rich_text') :
              $value = wp_kses_post($_POST[$name]);
            else :
              $value = sanitize_text_field($_POST[$name]);
            endif;
            update_post_meta($post_id, $prefixed_name, $value);
          endif;
        endforeach;
      }
      add_action('save_post', 'post_meta_save_data');

    endif;

  endif;

endif;
