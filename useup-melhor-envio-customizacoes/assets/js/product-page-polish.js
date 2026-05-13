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
    var retail = Number(displayPrice || 0);

    if (retail <= 0) {
      return { wholesale: 0, retail: 0 };
    }

    return {
      wholesale: Number((retail * 0.60).toFixed(2)),
      retail: retail
    };
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
        var values;

        if (!variation) {
          return;
        }

        values = normalizePriceValues(variation.display_price);
        updatePriceBlock($summary, values.wholesale, values.retail);
      });

      $form.on('hide_variation reset_data', function () {
        updatePriceBlock(
          $summary,
          $block.data('baseWholesale'),
          $block.data('baseRetail')
        );
      });
    });
  }

  function init() {
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
