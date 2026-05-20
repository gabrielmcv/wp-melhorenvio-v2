document.addEventListener('DOMContentLoaded', function () {
  var ruleContainer = document.getElementById('useup-me-rules');
  var addRuleButton = document.getElementById('useup-me-add-rule');
  var ruleTemplate = document.getElementById('useup-me-rule-template');
  var displayModeSelect = document.querySelector('.useup-me-complementary-display-mode');
  var shopFilterContainer = document.getElementById('useup-me-shop-filter-items');
  var addShopFilterButton = document.getElementById('useup-me-add-shop-filter-item');
  var shopFilterTemplate = document.getElementById('useup-me-shop-filter-item-template');
  var quantityPriceContainer = document.getElementById('useup-me-quantity-price-adjustments');
  var addQuantityPriceButton = document.getElementById('useup-me-add-quantity-price-adjustment');
  var quantityPriceTemplate = document.getElementById('useup-me-quantity-price-adjustment-template');

  function syncComplementaryTargets() {
    var mode;

    if (!displayModeSelect) {
      return;
    }

    mode = displayModeSelect.value;

    document.querySelectorAll('.useup-me-complementary-display-target').forEach(function (target) {
      target.hidden = target.getAttribute('data-display-mode') !== mode;
    });
  }

  function syncRuleCard(ruleCard) {
    var parameterSelect = ruleCard.querySelector('.useup-me-rule-parameter');
    var comparisonSelect = ruleCard.querySelector('.useup-me-rule-comparison-operator');
    var parameterFields = ruleCard.querySelectorAll('.useup-me-rule-parameter-fields');
    var valueToWrap = ruleCard.querySelector('.useup-me-rule-value-to-wrap');
    var valueLabel = ruleCard.querySelector('.useup-me-rule-value-label');
    var hasParameter;
    var isBetween;

    if (!parameterSelect) {
      return;
    }

    hasParameter = parameterSelect.value !== '';
    isBetween = hasParameter && comparisonSelect && comparisonSelect.value === 'between';

    parameterFields.forEach(function (field) {
      field.hidden = !hasParameter;
    });

    if (valueLabel) {
      valueLabel.textContent = isBetween ? 'Valor minimo' : 'Valor';
    }

    if (valueToWrap) {
      valueToWrap.hidden = !isBetween;
    }
  }

  function syncShopFilterItem(itemCard) {
    var typeSelect = itemCard.querySelector('.useup-me-shop-filter-item-type');
    var type;

    if (!typeSelect) {
      return;
    }

    type = typeSelect.value;

    itemCard.querySelectorAll('.useup-me-shop-filter-item-target').forEach(function (target) {
      target.hidden = target.getAttribute('data-shop-filter-target') !== type;
    });
  }

  if (window.jQuery) {
    window.jQuery(function ($) {
      $(document.body).trigger('wc-enhanced-select-init');
    });
  }

  if (displayModeSelect) {
    displayModeSelect.addEventListener('change', syncComplementaryTargets);
    syncComplementaryTargets();
  }

  if (ruleContainer) {
    ruleContainer.querySelectorAll('.useup-me-rule').forEach(syncRuleCard);

    if (addRuleButton && ruleTemplate) {
      addRuleButton.addEventListener('click', function () {
        var nextIndex = parseInt(ruleContainer.dataset.nextIndex || '0', 10);
        var markup = ruleTemplate.innerHTML.replace(/__index__/g, String(nextIndex));

        ruleContainer.insertAdjacentHTML('beforeend', markup);
        ruleContainer.dataset.nextIndex = String(nextIndex + 1);
        ruleContainer.querySelectorAll('.useup-me-rule').forEach(syncRuleCard);
      });
    }

    ruleContainer.addEventListener('click', function (event) {
      var target = event.target;
      var rule;

      if (!target.classList.contains('useup-me-remove-rule')) {
        return;
      }

      rule = target.closest('.useup-me-rule');

      if (rule) {
        rule.remove();
      }
    });

    ruleContainer.addEventListener('change', function (event) {
      var target = event.target;
      var ruleCard;

      if (!target.classList.contains('useup-me-rule-parameter') && !target.classList.contains('useup-me-rule-comparison-operator')) {
        return;
      }

      ruleCard = target.closest('.useup-me-rule');

      if (ruleCard) {
        syncRuleCard(ruleCard);
      }
    });
  }

  if (shopFilterContainer) {
    shopFilterContainer.querySelectorAll('.useup-me-shop-filter-item').forEach(syncShopFilterItem);

    if (addShopFilterButton && shopFilterTemplate) {
      addShopFilterButton.addEventListener('click', function () {
        var nextIndex = parseInt(shopFilterContainer.dataset.nextIndex || '0', 10);
        var markup = shopFilterTemplate.innerHTML.replace(/__index__/g, String(nextIndex));

        shopFilterContainer.insertAdjacentHTML('beforeend', markup);
        shopFilterContainer.dataset.nextIndex = String(nextIndex + 1);
        shopFilterContainer.querySelectorAll('.useup-me-shop-filter-item').forEach(syncShopFilterItem);
      });
    }

    shopFilterContainer.addEventListener('click', function (event) {
      var target = event.target;
      var itemCard;

      if (!target.classList.contains('useup-me-remove-shop-filter-item')) {
        return;
      }

      itemCard = target.closest('.useup-me-shop-filter-item');

      if (itemCard) {
        itemCard.remove();
      }
    });

    shopFilterContainer.addEventListener('change', function (event) {
      var target = event.target;
      var itemCard;

      if (!target.classList.contains('useup-me-shop-filter-item-type')) {
        return;
      }

      itemCard = target.closest('.useup-me-shop-filter-item');

      if (itemCard) {
        syncShopFilterItem(itemCard);
      }
    });
  }

  if (quantityPriceContainer) {
    if (addQuantityPriceButton && quantityPriceTemplate) {
      addQuantityPriceButton.addEventListener('click', function () {
        var nextIndex = parseInt(quantityPriceContainer.dataset.nextIndex || '0', 10);
        var markup = quantityPriceTemplate.innerHTML.replace(/__index__/g, String(nextIndex));

        quantityPriceContainer.insertAdjacentHTML('beforeend', markup);
        quantityPriceContainer.dataset.nextIndex = String(nextIndex + 1);
      });
    }

    quantityPriceContainer.addEventListener('click', function (event) {
      var target = event.target;
      var itemCard;

      if (!target.classList.contains('useup-me-remove-quantity-price-adjustment')) {
        return;
      }

      itemCard = target.closest('.useup-me-quantity-price-adjustment');

      if (itemCard) {
        itemCard.remove();
      }
    });
  }
});
