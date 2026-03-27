jQuery(function ($) {

  // Drag-and-drop field ordering
  $('.post-meta-sortable').each(function () {
    var $list = $(this);

    function updateOrder() {
      var order = $list.find('> .post-meta-field-row').map(function () {
        return $(this).data('field');
      }).get().join(',');
      $list.find('> .post-meta-order-input').val(order);
    }

    // Capture initial order on page load so it is saved even without reordering
    updateOrder();

    $list.sortable({
      handle: '.post-meta-drag-handle',
      items: '> .post-meta-field-row',
      placeholder: 'post-meta-sortable-placeholder',
      update: function () {
        updateOrder();
      }
    });
  });


  function getThumbnailSrc(attachment) {
    return attachment.sizes && attachment.sizes.thumbnail
      ? attachment.sizes.thumbnail.url
      : attachment.url;
  }

  $(document).on('click', '.post-meta-upload-image', function (e) {
    e.preventDefault();
    var $btn      = $(this);
    var inputName = $btn.data('input');
    var frame     = wp.media({ title: 'Choose image', button: { text: 'Use image' }, multiple: false });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var src        = getThumbnailSrc(attachment);

      if (inputName) {
        // Top-level field: ID-based
        $('#' + inputName).val(attachment.id);
        $('#' + inputName + '_preview').html('<img src="' + src + '" style="max-width:150px;display:block;" />');
        $btn.text('Change image');
        if (!$('[data-input="' + inputName + '"].post-meta-remove-image').length) {
          $btn.after(' <button type="button" class="button post-meta-remove-image" data-input="' + inputName + '">Remove</button>');
        }
      } else {
        // Repeater subfield: context-based
        var $field = $btn.closest('.post-meta-image-field');
        $field.find('.post-meta-image-input').val(attachment.id);
        $field.find('.post-meta-image-preview').html('<img src="' + src + '" style="max-width:150px;display:block;" />');
        $btn.text('Change image');
        if (!$field.find('.post-meta-remove-image').length) {
          $btn.after(' <button type="button" class="button post-meta-remove-image">Remove</button>');
        }
      }
    });

    frame.open();
  });

  $(document).on('click', '.post-meta-remove-image', function (e) {
    e.preventDefault();
    var $btn      = $(this);
    var inputName = $btn.data('input');

    if (inputName) {
      // Top-level field: ID-based
      $('#' + inputName).val('');
      $('#' + inputName + '_preview').html('');
      $('[data-input="' + inputName + '"].post-meta-upload-image').text('Choose image');
    } else {
      // Repeater subfield: context-based
      var $field = $btn.closest('.post-meta-image-field');
      $field.find('.post-meta-image-input').val('');
      $field.find('.post-meta-image-preview').html('');
      $field.find('.post-meta-upload-image').text('Choose image');
    }

    $btn.remove();
  });
});
