document.addEventListener('DOMContentLoaded', function () {
  var container = document.getElementById('useup-me-rules');
  var addButton = document.getElementById('useup-me-add-rule');
  var template = document.getElementById('useup-me-rule-template');
  var displayModeSelect = document.querySelector('.useup-me-complementary-display-mode');

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

  if (window.jQuery) {
    window.jQuery(function ($) {
      $(document.body).trigger('wc-enhanced-select-init');
    });
  }

  if (displayModeSelect) {
    displayModeSelect.addEventListener('change', syncComplementaryTargets);
    syncComplementaryTargets();
  }

  if (!container || !addButton || !template) {
    return;
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
      valueLabel.textContent = isBetween ? 'Valor mínimo' : 'Valor';
    }

    if (valueToWrap) {
      valueToWrap.hidden = !isBetween;
    }
  }

  function syncAllRuleCards() {
    container.querySelectorAll('.useup-me-rule').forEach(syncRuleCard);
  }

  addButton.addEventListener('click', function () {
    var nextIndex = parseInt(container.dataset.nextIndex || '0', 10);
    var markup = template.innerHTML.replace(/__index__/g, String(nextIndex));
    container.insertAdjacentHTML('beforeend', markup);
    container.dataset.nextIndex = String(nextIndex + 1);
    syncAllRuleCards();
  });

  container.addEventListener('click', function (event) {
    var target = event.target;

    if (!target.classList.contains('useup-me-remove-rule')) {
      return;
    }

    var rule = target.closest('.useup-me-rule');

    if (rule) {
      rule.remove();
    }
  });

  container.addEventListener('change', function (event) {
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

  syncAllRuleCards();
});
