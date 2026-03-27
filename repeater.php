<?php

add_action('admin_head', function () { ?>
    <style>
        .Repeater {
            display: flex;
            flex-direction: column;
            gap: 0.75em;
        }

        .Repeater-row {
            display: flex;
            gap: 0.5em;
            align-items: flex-end;
            padding: 0.75em;
            border: 1px solid #ddd;
            background: #f9f9f9;
        }

        .Repeater-field {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
        }

        .Repeater-field label {
            font-weight: 500;
        }

        .Repeater-field input {
            width: 100%;
        }

        .Repeater .Repeater-add-button.button-primary {
            width: 8rem;
            display: block;
            margin-inline: auto;
        }
    </style>
<?php });

add_action('admin_footer', function () { ?>
    <script>
        document.querySelectorAll('.js-repeater').forEach(function(repeater) {
            function reindex() {
                repeater.querySelectorAll('.js-row').forEach(function(row, i) {
                    row.querySelectorAll('[data-repeater-name]').forEach(function(el) {
                        el.name = el.dataset.repeaterName.replace('__i__', i);
                    });
                });
            }

            repeater.addEventListener('click', function(e) {
                if (e.target.classList.contains('js-addRow')) {
                    var tmpl = repeater.querySelector('template').content.cloneNode(true);
                    e.target.before(tmpl);
                    reindex();
                } else if (e.target.classList.contains('js-removeRow')) {
                    e.target.closest('.js-row').remove();
                    reindex();
                }
            });
        });
    </script>
<?php });

function render_repeater_subfield($sub, $field_name, $index, $value, $is_template = false)
{
    $name_attr      = $is_template ? '' : esc_attr("{$field_name}[{$index}][{$sub['name']}]");
    $data_name_attr = esc_attr("{$field_name}[__i__][{$sub['name']}]");
    $label          = esc_html($sub['label'] ?? $sub['name']);
?>
    <div class="Repeater-field">
        <label><?= $label ?></label>
        <?php if ($sub['type'] === 'image') : ?>
            <?php
            $attachment_id = absint($value);
            $image_src     = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
            ?>
            <div class="post-meta-image-field">
                <input type="hidden"
                    class="post-meta-image-input"
                    data-repeater-name="<?= $data_name_attr ?>"
                    name="<?= $name_attr ?>"
                    value="<?= esc_attr($attachment_id ?: '') ?>" />
                <div class="post-meta-image-preview" style="margin-bottom:4px;">
                    <?php if ($image_src) : ?>
                        <img src="<?= esc_url($image_src) ?>" style="max-width:150px;display:block;" />
                    <?php endif; ?>
                </div>
                <button type="button" class="button post-meta-upload-image"><?= $image_src ? 'Change image' : 'Choose image' ?></button>
                <?php if ($image_src) : ?>
                    <button type="button" class="button post-meta-remove-image">Remove</button>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <input
                type="<?= esc_attr($sub['type']) ?>"
                data-repeater-name="<?= $data_name_attr ?>"
                name="<?= $name_attr ?>"
                value="<?= esc_attr($value) ?>" />
        <?php endif; ?>
    </div>
<?php
}

function render_repeater($post, $field, $meta_key = null)
{
    $name      = $field['name'];
    $meta_key  = $meta_key ?? $name;
    $subfields = $field['subfields'] ?? [];
    $rows      = get_post_meta($post->ID, $meta_key, true) ?: [];
?>
    <div class="Repeater js-repeater">

        <?php foreach ($rows as $i => $row) : ?>
            <div class="Repeater-row js-row">
                <?php foreach ($subfields as $sub) : ?>
                    <?php render_repeater_subfield($sub, $name, $i, $row[$sub['name']] ?? ''); ?>
                <?php endforeach; ?>
                <button type="button" class="button js-removeRow">Remove</button>
            </div>
        <?php endforeach; ?>

        <template>
            <div class="Repeater-row js-row">
                <?php foreach ($subfields as $sub) : ?>
                    <?php render_repeater_subfield($sub, $name, '__i__', '', true); ?>
                <?php endforeach; ?>
                <button type="button" class="button js-removeRow">Remove</button>
            </div>
        </template>

        <button type="button" class="Repeater-add-button button-primary js-addRow">+ Add row</button>

    </div>
<?php
}
