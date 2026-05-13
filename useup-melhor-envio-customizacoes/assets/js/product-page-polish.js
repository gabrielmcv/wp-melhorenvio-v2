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

  function relocateShippingCalculator() {
    $('.useup-product-polish .summary, .useup-product-polish .summary.entry-summary').each(function () {
      var $summary = $(this);
      var $shipping = $summary.find('.useup-me-product-shipping').first();
      var $target = $summary.find('.useup-short-description').last();

      if (!$shipping.length) {
        return;
      }

      if ($target.length) {
        $target.after($shipping);
        return;
      }

      var $trust = $summary.find('.useup-product-trust').last();

      if ($trust.length) {
        $trust.after($shipping);
      }
    });
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

      lineHeight = parseFloat(window.getComputedStyle($content[0]).lineHeight || '28');
      collapsedHeight = lineHeight * 3.1;

      $content.css('--useup-short-description-expanded-height', $content[0].scrollHeight + 'px');

      if ($content[0].scrollHeight <= collapsedHeight + 6) {
        $toggle.attr('hidden', true);
        $wrapper.removeClass('useup-short-description--collapsed useup-short-description--expanded');
        return;
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

  function updatePriceBlock($scope, currentValue, regularValue) {
    var $block = $scope.find('.useup-price-block').first();

    if (!$block.length) {
      return;
    }

    var current = Number(currentValue || 0);
    var regular = Number(regularValue || 0);
    var $secondary = $block.find('.useup-price-block__secondary').first();

    $block.find('.useup-price-block__amount').text(formatMoney(current));

    if (regular > current) {
      $secondary.removeAttr('hidden');
      $secondary.find('.useup-price-block__secondary-amount').text(formatMoney(regular));
      return;
    }

    $secondary.attr('hidden', true);
  }

  function setupVariationPriceSync() {
    $('.variations_form').each(function () {
      var $form = $(this);
      var $summary = $form.closest('.summary, .summary.entry-summary');
      var $block = $summary.find('.useup-price-block').first();

      if (!$block.length) {
        return;
      }

      $form.on('show_variation', function (event, variation) {
        if (!variation) {
          return;
        }

        updatePriceBlock($summary, variation.display_price, variation.display_regular_price);
      });

      $form.on('hide_variation reset_data', function () {
        updatePriceBlock(
          $summary,
          $block.data('baseCurrent'),
          $block.data('baseRegular')
        );
      });
    });
  }

  function init() {
    relocateShippingCalculator();
    setupShortDescriptions();
    setupVariationPriceSync();
  }

  $(document).ready(init);

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
