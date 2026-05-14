(function ($, window, document) {
  'use strict';

  var config = window.useupMeProductPagePolish || {};

  function formatMoney(amount) {
    var decimals = Number(config.decimals || 2);
    var number = Number(amount || 0);
    var negative = number < 0 ? '-' : '';
    var fixed = Math.abs(number).toFixed(decimals);
    var parts = fixed.split('.');
    var integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, config.thousandSep || '.');
    var decimal = parts[1] ? (config.decimalSep || ',') + parts[1] : '';
    var formatted = negative + integer + decimal;
    var priceFormat = String(config.priceFormat || '%1$s%2$s');

    return priceFormat
      .replace('%1$s', config.currencySymbol || 'R$')
      .replace('%2$s', formatted);
  }

  function setupShortDescriptions() {
    $('.useup-short-description').each(function () {
      var $wrapper = $(this);
      var $content = $wrapper.find('.useup-short-description__content').first();
      var $toggle = $wrapper.find('.useup-short-description__toggle').first();
      var lineHeight;
      var collapsedHeight;

      if (!$content.length || !$toggle.length) {
        return;
      }

      lineHeight = parseFloat(window.getComputedStyle($content[0]).lineHeight || '24');
      collapsedHeight = lineHeight * 3;

      $content.css('--useup-short-description-expanded-height', $content[0].scrollHeight + 'px');

      if ($content[0].scrollHeight <= collapsedHeight + 6) {
        $toggle.attr('hidden', true);
        $wrapper.removeClass('useup-short-description--collapsed useup-short-description--expanded');
        return;
      }

      if (!$wrapper.hasClass('useup-short-description--expanded')) {
        $wrapper.addClass('useup-short-description--collapsed');
      }

      $toggle.removeAttr('hidden');
    });
  }

  function toggleShortDescription(button) {
    var $toggle = $(button);
    var $wrapper = $toggle.closest('.useup-short-description');
    var $content = $wrapper.find('.useup-short-description__content').first();
    var isExpanded = $wrapper.hasClass('useup-short-description--expanded');

    if (!$content.length) {
      return;
    }

    $content.css('--useup-short-description-expanded-height', $content[0].scrollHeight + 'px');

    $wrapper.toggleClass('useup-short-description--expanded', !isExpanded);
    $wrapper.toggleClass('useup-short-description--collapsed', isExpanded);
    $toggle.attr('aria-expanded', isExpanded ? 'false' : 'true');
    $toggle.text(isExpanded ? (config.expandLabel || 'Ler descrição') : (config.collapseLabel || 'Ocultar descrição'));
  }

  function closeWholesalePopovers(except) {
    $('.useup-wholesale-info-trigger').each(function () {
      var $trigger = $(this);
      var $info = $trigger.closest('.useup-wholesale-info');
      var $popover = $info.find('.useup-wholesale-popover').first();

      if (except && this === except) {
        return;
      }

      $trigger.attr('aria-expanded', 'false');
      $info.removeClass('is-open');
      $popover.attr('hidden', true);
    });
  }

  function toggleWholesalePopover(button) {
    var $trigger = $(button);
    var $info = $trigger.closest('.useup-wholesale-info');
    var $popover = $info.find('.useup-wholesale-popover').first();
    var isExpanded = $trigger.attr('aria-expanded') === 'true';

    closeWholesalePopovers(button);

    $trigger.attr('aria-expanded', isExpanded ? 'false' : 'true');
    $info.toggleClass('is-open', !isExpanded);
    $popover.attr('hidden', isExpanded);
  }

  function hideVariationLoosePrice($scope) {
    var $root = $scope && $scope.length ? $scope : $(document);

    $root.find('.woocommerce-variation .woocommerce-variation-price, .woocommerce-variation-price').hide();
  }

  function updatePriceBlock($scope, wholesaleValue, retailValue) {
    var $block = $scope.find('.useup-price-block').first();
    var wholesale = Number(wholesaleValue || 0);
    var retail = Number(retailValue || 0);
    var $main = $block.find('.useup-price-block__amount').first();
    var $secondary = $block.find('.useup-price-block__retail, .useup-price-block__secondary').first();
    var $secondaryAmount = $secondary.find('.useup-price-block__secondary-amount').first();

    if (!$block.length || !$main.length) {
      return;
    }

    $main.text(formatMoney(wholesale));

    if (retail > 0) {
      $secondaryAmount.text(formatMoney(retail));
      $secondary.show();
      return;
    }

    $secondary.hide();
  }

  function normalizePriceValues(displayPrice) {
    var wholesale = Number(displayPrice || 0);
    var percent = Number(config.retailMarkupPercent || 0);
    var fixed = Number(config.retailMarkupFixed || 0);
    var decimals = Number(config.decimals || 2);
    var retail;

    if (wholesale <= 0) {
      return { wholesale: 0, retail: 0 };
    }

    retail = Number(((wholesale * (1 + (percent / 100))) + fixed).toFixed(decimals));

    return {
      wholesale: wholesale,
      retail: retail
    };
  }

  function getVariationWholesalePrice(variation, $block) {
    var wholesale = 0;

    if (variation && variation.display_price !== undefined && variation.display_price !== null && variation.display_price !== '') {
      wholesale = Number(variation.display_price);
    }

    if (wholesale <= 0 && variation && variation.display_regular_price !== undefined && variation.display_regular_price !== null && variation.display_regular_price !== '') {
      wholesale = Number(variation.display_regular_price);
    }

    if (wholesale <= 0 && $block && $block.length) {
      wholesale = Number($block.data('baseWholesale') || 0);
    }

    return wholesale > 0 ? wholesale : 0;
  }

  function syncVariationPriceBlock($summary, $block, variation) {
    var wholesale = getVariationWholesalePrice(variation, $block);
    var values = normalizePriceValues(wholesale);

    updatePriceBlock($summary, values.wholesale, values.retail);
  }

  function setupVariationPriceSync() {
    $('.variations_form').each(function () {
      var $form = $(this);
      var $summary = $form.closest('.summary, .summary.entry-summary');
      var $block = $summary.find('.useup-price-block').first();

      if (!$block.length) {
        return;
      }

      $form.off('.useupPricePolish');

      $form.on('found_variation.useupPricePolish show_variation.useupPricePolish', function (event, variation) {
        if (!variation) {
          return;
        }

        syncVariationPriceBlock($summary, $block, variation);
        hideVariationLoosePrice($summary);
        window.setTimeout(function () {
          syncVariationPriceBlock($summary, $block, variation);
          hideVariationLoosePrice($summary);
        }, 0);
      });

      $form.on('hide_variation.useupPricePolish reset_data.useupPricePolish woocommerce_variation_has_changed.useupPricePolish', function () {
        window.setTimeout(function () {
          updatePriceBlock(
            $summary,
            $block.data('baseWholesale'),
            $block.data('baseRetail')
          );
          hideVariationLoosePrice($summary);
        }, 0);
      });
    });
  }

  function setLoopCardMediaBackground(media, image) {
    var src;

    if (!media || !image) {
      return;
    }

    src = image.currentSrc || image.getAttribute('src') || image.getAttribute('data-src') || image.getAttribute('data-lazy-src');

    if (!src) {
      return;
    }

    media.style.backgroundImage = 'url("' + String(src).replace(/"/g, '\\"') + '")';
    media.setAttribute('data-useup-bg-ready', '1');
  }

  function getLoopCardMedia(card, image) {
    var media = card.querySelector('.useup-loop-card__media, .et-product-thumbnail, .product-thumbnail, .product-image-wrapper, .mf-product-thumbnail, .box-image');
    var imageParent;

    if (media) {
      media.classList.add('useup-loop-card__media');
      return media;
    }

    if (!image) {
      return null;
    }

    imageParent = image.parentElement;

    if (imageParent && imageParent.tagName === 'A' && imageParent.children.length === 1) {
      imageParent.classList.add('useup-loop-card__media');
      return imageParent;
    }

    media = document.createElement('span');
    media.className = 'useup-loop-card__media';
    image.parentNode.insertBefore(media, image);
    media.appendChild(image);

    return media;
  }

  function getLoopCardContent(card, media) {
    var content = card.querySelector('.useup-loop-card__content, .caption, .product-caption, .product-content, .content-product-imagin, .mf-product-content, .box-text, .product-details');
    var children;

    if (content) {
      content.classList.add('useup-loop-card__content');
      content.classList.add('useup-loop-card__caption');
      return content;
    }

    content = document.createElement('div');
    content.className = 'useup-loop-card__content useup-loop-card__caption';

    if (media && media.parentNode === card) {
      if (media.nextSibling) {
        card.insertBefore(content, media.nextSibling);
      } else {
        card.appendChild(content);
      }
    } else {
      card.appendChild(content);
    }

    children = Array.prototype.slice.call(card.children);

    children.forEach(function (child) {
      if (child === media || child === content) {
        return;
      }

      if (child.matches('.onsale, .sale, .wc-block-grid__product-onsale')) {
        return;
      }

      content.appendChild(child);
    });

    return content;
  }

  function isShopArchivePage() {
    var body = document.body;

    return body.classList.contains('post-type-archive-product') ||
      body.classList.contains('tax-product_cat') ||
      body.classList.contains('tax-product_tag') ||
      body.classList.contains('woocommerce-shop');
  }

  function isSingleProductPage() {
    return document.body.classList.contains('single-product');
  }

  function prepareLoopCard(card, containerClassName) {
    var container;
    var image;
    var media;
    var content;
    var title;

    if (!card || card.nodeType !== 1) {
      return;
    }

    card.classList.add('useup-loop-card');
    container = card.closest('ul.products, .products');

    if (container) {
      container.classList.add(containerClassName || 'useup-loop-cards-enabled');
    }

    image = card.querySelector('img.attachment-woocommerce_thumbnail, img.wp-post-image, .et-product-thumbnail img, img');

    if (!image) {
      return;
    }

    media = getLoopCardMedia(card, image);

    if (!media) {
      return;
    }

    content = getLoopCardContent(card, media);

    if (content) {
      content.classList.add('useup-loop-card__content');
      content.classList.add('useup-loop-card__caption');
    }

    title = card.querySelector('.woocommerce-loop-product__title');
    if (title) {
      title.classList.add('useup-loop-card__title');
    }

    setLoopCardMediaBackground(media, image);

    if (!image.dataset.useupLoopBgBound) {
      image.dataset.useupLoopBgBound = '1';
      image.addEventListener('load', function () {
        setLoopCardMediaBackground(media, image);
      });
    }
  }

  function applyLoopCardBackgrounds(root) {
    var scope = root && root.querySelectorAll ? root : document;

    if (isSingleProductPage() || !isShopArchivePage()) {
      return;
    }

    scope.querySelectorAll('.woocommerce ul.products li.product, ul.products li.product').forEach(function (card) {
      prepareLoopCard(card);
    });
  }

  function applyExternalLoopGaps(root) {
    var scope = root && root.querySelectorAll ? root : document;

    if (isSingleProductPage() || isShopArchivePage()) {
      return;
    }

    scope.querySelectorAll('.products, ul.products').forEach(function (container) {
      var cards = [];

      if (container.matches('ul.products')) {
        cards = Array.prototype.filter.call(container.children, function (child) {
          return child && child.nodeType === 1 && child.classList.contains('product');
        });
      }

      if (!cards.length) {
        cards = Array.prototype.slice.call(container.querySelectorAll('.slick-track > li.product'));
      }

      if (!cards.length) {
        return;
      }

      container.classList.add('useup-loop-cards-carousel-enabled');

      cards.forEach(function (card) {
        prepareLoopCard(card, 'useup-loop-cards-carousel-enabled');
      });
    });
  }

  function applyRelatedProductCards(root) {
    var scope = root && root.querySelectorAll ? root : document;

    if (!isSingleProductPage()) {
      return;
    }

    scope.querySelectorAll('.related .products, .related ul.products').forEach(function (container) {
      var cards = [];

      if (container.matches('ul.products')) {
        cards = Array.prototype.filter.call(container.children, function (child) {
          return child && child.nodeType === 1 && child.classList.contains('product');
        });
      }

      if (!cards.length) {
        cards = Array.prototype.slice.call(container.querySelectorAll('.slick-track > li.product'));
      }

      if (!cards.length) {
        return;
      }

      if (cards.some(function (card) { return card.classList.contains('slick-slide'); })) {
        container.classList.add('useup-related-loop-cards-carousel-enabled');

        cards.forEach(function (card) {
          prepareLoopCard(card, 'useup-related-loop-cards-carousel-enabled');
        });

        return;
      }

      container.classList.add('useup-related-loop-cards-enabled');

      cards.forEach(function (card) {
        prepareLoopCard(card, 'useup-related-loop-cards-enabled');
      });
    });
  }

  function setupLoopCardObserver() {
    var scheduled = false;
    var observer;

    if (!window.MutationObserver || !document.body) {
      return;
    }

    observer = new window.MutationObserver(function (mutations) {
      var shouldRefresh = mutations.some(function (mutation) {
        return Array.prototype.some.call(mutation.addedNodes, function (node) {
          if (!node || node.nodeType !== 1) {
            return false;
          }

          return node.matches('.woocommerce ul.products li.product, ul.products li.product') ||
            Boolean(node.querySelector && node.querySelector('.woocommerce ul.products li.product, ul.products li.product'));
        });
      });

      if (!shouldRefresh || scheduled) {
        return;
      }

      scheduled = true;
      window.requestAnimationFrame(function () {
        scheduled = false;
        applyLoopCardBackgrounds(document);
        applyExternalLoopGaps(document);
        applyRelatedProductCards(document);
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function init() {
    setupShortDescriptions();
    setupVariationPriceSync();
    applyLoopCardBackgrounds(document);
    applyExternalLoopGaps(document);
    applyRelatedProductCards(document);
    setupLoopCardObserver();
    hideVariationLoosePrice($('.single-product'));
  }

  $(document).ready(init);
  $(window).on('load', function () {
    applyLoopCardBackgrounds(document);
    applyExternalLoopGaps(document);
    applyRelatedProductCards(document);
  });

  $(document).on('click', '.useup-short-description__toggle', function () {
    toggleShortDescription(this);
  });

  $(document).on('click', '.useup-wholesale-info-trigger', function (event) {
    event.preventDefault();
    event.stopPropagation();
    toggleWholesalePopover(this);
  });

  $(document).on('click', function (event) {
    if ($(event.target).closest('.useup-wholesale-info').length) {
      return;
    }

    closeWholesalePopovers();
  });

  $(document).on('keyup', function (event) {
    if (event.key === 'Escape') {
      closeWholesalePopovers();
    }
  });

  $(window).on('resize', setupShortDescriptions);
})(jQuery, window, document);
