(function ($, window) {
  'use strict';

  var config = window.useupMeProductGalleryMediaAdmin || {};
  var frame = null;

  function normalizeIds(ids) {
    return ids.filter(function (id) {
      return String(id || '').trim() !== '';
    });
  }

  function getManagers() {
    return $('[data-useup-gallery-media-manager]');
  }

  function getList($manager) {
    return $manager.find('.useup-me-product-gallery-media__list').first();
  }

  function getIdsField($manager) {
    return $manager.find('.useup-me-product-gallery-media__ids').first();
  }

  function getCurrentIds($manager) {
    var value = getIdsField($manager).val() || '';

    return normalizeIds(value.split(','));
  }

  function setCurrentIds($manager, ids) {
    getIdsField($manager).val(normalizeIds(ids).join(',')).trigger('change');
  }

  function getPreviewUrl(attachment) {
    if (attachment.image && attachment.image.src) {
      return attachment.image.src;
    }

    if (attachment.sizes) {
      if (attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
        return attachment.sizes.thumbnail.url;
      }

      if (attachment.sizes.medium && attachment.sizes.medium.url) {
        return attachment.sizes.medium.url;
      }
    }

    return attachment.icon || attachment.url || '';
  }

  function renderGalleryItem(attachment) {
    var id = String(attachment.id || '');
    var previewUrl = getPreviewUrl(attachment);

    if (!id || !previewUrl) {
      return '';
    }

    return '' +
      '<li class="image useup-me-gallery-item--video" data-attachment_id="' + id + '">' +
        '<span class="useup-me-gallery-item__preview">' +
          '<video muted playsinline preload="metadata" src="' + previewUrl + '"></video>' +
        '</span>' +
        '<span class="useup-me-gallery-item__badge">' + (config.videoBadge || 'MP4') + '</span>' +
        '<ul class="actions">' +
          '<li><a href="#" class="delete" aria-label="' + (config.deleteLabel || 'Remover item da galeria') + '">' + (config.deleteLabel || 'Remover item da galeria') + '</a></li>' +
        '</ul>' +
      '</li>';
  }

  function appendAttachmentToManager($manager, attachment) {
    var id = String(attachment.id || '');
    var ids = getCurrentIds($manager);
    var $list = getList($manager);

    if (!id || ids.indexOf(id) !== -1 || !$list.length) {
      return;
    }

    $list.append(renderGalleryItem(attachment));
    ids.push(id);
    setCurrentIds($manager, ids);
  }

  function syncIdsFromDom($manager) {
    var ids = [];

    getList($manager).find('li.image').each(function () {
      var id = String($(this).attr('data-attachment_id') || '').trim();

      if (id) {
        ids.push(id);
      }
    });

    setCurrentIds($manager, ids);
  }

  function ensureSortable($manager) {
    var $list = getList($manager);

    if (!$list.length || !$.fn.sortable || $list.data('useupGalleryMediaSortableBound')) {
      return;
    }

    $list.sortable({
      items: 'li.image',
      cursor: 'move',
      scrollSensitivity: 40,
      forcePlaceholderSize: true,
      forceHelperSize: false,
      helper: 'clone',
      opacity: 0.65,
      placeholder: 'woocommerce-metabox-sortable-placeholder',
      start: function (event, ui) {
        ui.item.css('background-color', '#f6f6f6');
      },
      stop: function (event, ui) {
        ui.item.removeAttr('style');
        syncIdsFromDom($manager);
      }
    });

    $list.data('useupGalleryMediaSortableBound', true);
  }

  function restoreItems($manager) {
    var ids = getCurrentIds($manager);
    var existing = {};

    if (!ids.length || !window.wp || !window.wp.media || !window.wp.media.attachment) {
      return;
    }

    getList($manager).find('li.image').each(function () {
      existing[String($(this).attr('data-attachment_id') || '')] = true;
    });

    ids.forEach(function (id) {
      if (existing[id]) {
        return;
      }

      var attachment = window.wp.media.attachment(id);

      if (!attachment) {
        return;
      }

      attachment.fetch().done(function () {
        appendAttachmentToManager($manager, attachment.toJSON());
      });
    });
  }

  function openMediaFrame($manager) {
    if (!window.wp || !window.wp.media) {
      return;
    }

    if (frame) {
      frame.off('select');
    } else {
      frame = window.wp.media({
        title: config.frameTitle || 'Adicionar videos MP4 a galeria do produto',
        button: {
          text: config.frameButtonLabel || 'Adicionar a galeria'
        },
        library: {
          type: ['video']
        },
        multiple: true
      });
    }

    frame.on('select', function () {
      var selection = frame.state().get('selection');

      selection.each(function (attachmentModel) {
        appendAttachmentToManager($manager, attachmentModel.toJSON());
      });
    });

    frame.open();
  }

  $(function () {
    getManagers().each(function () {
      var $manager = $(this);

      ensureSortable($manager);
      restoreItems($manager);
    });

    $(document).on('click.useupGalleryMediaAdmin', '.useup-me-open-product-gallery-media', function (event) {
      event.preventDefault();
      openMediaFrame($(this).closest('[data-useup-gallery-media-manager]'));
    });

    $(document).on('click.useupGalleryMediaAdmin', '[data-useup-gallery-media-manager] .delete', function (event) {
      var $manager = $(this).closest('[data-useup-gallery-media-manager]');

      event.preventDefault();
      $(this).closest('li.image').remove();
      syncIdsFromDom($manager);
    });
  });
})(jQuery, window);
