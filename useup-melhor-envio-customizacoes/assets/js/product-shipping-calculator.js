(function (window, document, $) {
  'use strict';

  var localized = window.useupMeProductShipping || {};
  var strings = localized.i18n || {};

  function sanitizePostcode(value) {
    return String(value || '').replace(/\D+/g, '').slice(0, 8);
  }

  function formatPostcode(value) {
    var digits = sanitizePostcode(value);

    if (digits.length <= 5) {
      return digits;
    }

    return digits.slice(0, 5) + '-' + digits.slice(5);
  }

  function debounce(fn, delay) {
    var timer = null;

    return function () {
      var args = arguments;
      var context = this;

      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(context, args);
      }, delay);
    };
  }

  function ProductShippingCalculator(element) {
    this.element = element;
    this.body = element.querySelector('.useup-me-product-shipping__body');
    this.savedBlock = element.querySelector('.useup-me-product-shipping__saved');
    this.manualBlock = element.querySelector('.useup-me-product-shipping__manual');
    this.postcodeValue = element.querySelector('.useup-me-product-shipping__postcode-value');
    this.changeButton = element.querySelector('.useup-me-product-shipping__change-cep');
    this.input = element.querySelector('.useup-me-product-shipping__input');
    this.button = element.querySelector('.useup-me-product-shipping__button');
    this.toggle = element.querySelector('.useup-me-product-shipping__toggle');
    this.results = element.querySelector('.useup-me-product-shipping__results');
    this.error = element.querySelector('.useup-me-product-shipping__error');
    this.form = element.closest('form.cart');
    this.variationForm = this.form && this.form.classList.contains('variations_form') ? this.form : null;
    this.quantityInput = this.form ? this.form.querySelector('input.qty') : null;
    this.initialButtonText = this.button ? this.button.textContent : (strings.seeOptions || 'Ver opções');
    this.state = {
      hasSavedPostcode: element.getAttribute('data-has-postcode') === '1',
      currentPostcode: sanitizePostcode(element.getAttribute('data-postcode')),
      hasCalculated: false,
      isLoading: false
    };
    this.debouncedRecalculate = debounce(this.recalculate.bind(this), 350);

    this.bindEvents();

    if (this.input && this.state.currentPostcode) {
      this.input.value = formatPostcode(this.state.currentPostcode);
    }

    if (this.state.hasSavedPostcode && this.state.currentPostcode) {
      this.quote(this.state.currentPostcode, true);
    }
  }

  ProductShippingCalculator.prototype.bindEvents = function () {
    if (this.input) {
      this.input.addEventListener('input', this.handleInput.bind(this));
      this.input.addEventListener('keydown', this.handleInputKeydown.bind(this));
    }

    if (this.button) {
      this.button.addEventListener('click', this.handleManualSubmit.bind(this));
    }

    if (this.changeButton) {
      this.changeButton.addEventListener('click', this.showManualMode.bind(this));
    }

    if (this.toggle) {
      this.toggle.addEventListener('click', this.toggleBody.bind(this));
    }

    if (this.quantityInput) {
      this.quantityInput.addEventListener('input', this.debouncedRecalculate);
      this.quantityInput.addEventListener('change', this.debouncedRecalculate);
    }

    if (this.variationForm && $) {
      $(this.variationForm).on('show_variation', this.handleVariationChange.bind(this));
      $(this.variationForm).on('hide_variation reset_data', this.handleVariationReset.bind(this));
    }

    this.element.addEventListener('click', this.handleLocalClick.bind(this));
    document.addEventListener('click', this.handleDocumentClick.bind(this));
    document.addEventListener('keyup', this.handleDocumentKeyup.bind(this));
  };

  ProductShippingCalculator.prototype.handleInput = function (event) {
    event.target.value = formatPostcode(event.target.value);
  };

  ProductShippingCalculator.prototype.handleInputKeydown = function (event) {
    if (event.key !== 'Enter') {
      return;
    }

    event.preventDefault();
    this.handleManualSubmit();
  };

  ProductShippingCalculator.prototype.handleManualSubmit = function () {
    this.quote(this.input ? this.input.value : '', false);
  };

  ProductShippingCalculator.prototype.handleVariationChange = function () {
    this.clearError();
    this.clearResults();

    if (this.state.currentPostcode) {
      this.quote(this.state.currentPostcode, true);
    }
  };

  ProductShippingCalculator.prototype.handleVariationReset = function () {
    this.clearError();
    this.clearResults();
  };

  ProductShippingCalculator.prototype.handleLocalClick = function (event) {
    var fastestTrigger = event.target.closest('.useup-me-product-shipping__fastest-badge');

    if (!fastestTrigger) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    this.toggleFastestTooltip(fastestTrigger);
  };

  ProductShippingCalculator.prototype.handleDocumentClick = function (event) {
    if (event.target.closest('.useup-me-product-shipping__fastest-badge')) {
      return;
    }

    this.closeFastestTooltips();
  };

  ProductShippingCalculator.prototype.handleDocumentKeyup = function (event) {
    if (event.key === 'Escape') {
      this.closeFastestTooltips();
    }
  };

  ProductShippingCalculator.prototype.recalculate = function () {
    if (!this.state.currentPostcode || !this.state.hasCalculated) {
      return;
    }

    this.quote(this.state.currentPostcode, true);
  };

  ProductShippingCalculator.prototype.toggleBody = function () {
    if (!this.body || !this.toggle) {
      return;
    }

    var expanded = this.toggle.getAttribute('aria-expanded') !== 'false';
    this.toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    this.body.hidden = expanded;
  };

  ProductShippingCalculator.prototype.showManualMode = function () {
    if (this.savedBlock) {
      this.savedBlock.hidden = true;
    }

    if (this.manualBlock) {
      this.manualBlock.hidden = false;
    }

    if (this.input) {
      this.input.value = formatPostcode(this.state.currentPostcode);
      this.input.focus();
      this.input.select();
    }
  };

  ProductShippingCalculator.prototype.showSavedMode = function (formattedPostcode) {
    if (this.savedBlock) {
      this.savedBlock.hidden = false;
    }

    if (this.manualBlock) {
      this.manualBlock.hidden = true;
    }

    if (this.postcodeValue) {
      this.postcodeValue.textContent = formattedPostcode;
    }
  };

  ProductShippingCalculator.prototype.getQuantity = function () {
    if (!this.quantityInput) {
      return 1;
    }

    var quantity = parseInt(this.quantityInput.value, 10);

    return quantity > 0 ? quantity : 1;
  };

  ProductShippingCalculator.prototype.getVariationId = function () {
    if (!this.form) {
      return 0;
    }

    var variationInput = this.form.querySelector('input[name="variation_id"]');

    if (!variationInput) {
      return 0;
    }

    var variationId = parseInt(variationInput.value, 10);

    return variationId > 0 ? variationId : 0;
  };

  ProductShippingCalculator.prototype.requiresVariation = function () {
    return !!this.variationForm;
  };

  ProductShippingCalculator.prototype.setLoading = function (isLoading) {
    this.state.isLoading = isLoading;

    if (isLoading) {
      this.element.classList.add('useup-me-product-shipping__loading');
      if (this.button) {
        this.button.textContent = strings.calculating || 'Calculando...';
      }
      return;
    }

    this.element.classList.remove('useup-me-product-shipping__loading');

    if (this.button) {
      this.button.textContent = this.initialButtonText;
    }
  };

  ProductShippingCalculator.prototype.showError = function (message) {
    if (!this.error) {
      return;
    }

    this.error.textContent = message;
    this.error.hidden = false;
  };

  ProductShippingCalculator.prototype.clearError = function () {
    if (!this.error) {
      return;
    }

    this.error.textContent = '';
    this.error.hidden = true;
  };

  ProductShippingCalculator.prototype.clearResults = function () {
    if (!this.results) {
      return;
    }

    this.results.innerHTML = '';
    this.results.hidden = true;
    this.state.hasCalculated = false;
  };

  ProductShippingCalculator.prototype.quote = function (postcode, isAutomatic) {
    if (this.state.isLoading) {
      return;
    }

    var normalizedPostcode = sanitizePostcode(postcode);

    if (normalizedPostcode.length !== 8) {
      if (!isAutomatic) {
        this.showError(strings.invalidPostcode || 'Informe um CEP válido com 8 números.');
      }
      return;
    }

    if (this.requiresVariation() && this.getVariationId() === 0) {
      if (!isAutomatic) {
        this.showError(strings.selectVariation || 'Selecione uma variação para calcular a entrega.');
      }
      return;
    }

    this.clearError();
    this.closeFastestTooltips();
    this.setLoading(true);

    var requestBody = new window.URLSearchParams();
    requestBody.append('action', 'useup_me_product_shipping_quote');
    requestBody.append('nonce', localized.nonce || '');
    requestBody.append('product_id', this.element.getAttribute('data-product-id') || localized.productId || '');
    requestBody.append('variation_id', this.getVariationId());
    requestBody.append('quantity', this.getQuantity());
    requestBody.append('postcode', normalizedPostcode);

    window.fetch(localized.ajaxUrl || window.ajaxurl || '', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      body: requestBody.toString()
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : (strings.genericError || 'Não foi possível calcular o frete agora. Tente novamente em instantes.'));
        }

        this.state.currentPostcode = sanitizePostcode(payload.data.raw_postcode || normalizedPostcode);
        this.state.hasSavedPostcode = true;
        this.state.hasCalculated = true;
        this.element.setAttribute('data-has-postcode', '1');
        this.element.setAttribute('data-postcode', formatPostcode(this.state.currentPostcode));
        this.showSavedMode(payload.data.postcode || formatPostcode(this.state.currentPostcode));
        this.renderResults(payload.data);
      }.bind(this))
      .catch(function (error) {
        this.clearResults();
        this.showError(error && error.message ? error.message : (strings.genericError || 'Não foi possível calcular o frete agora. Tente novamente em instantes.'));
      }.bind(this))
      .finally(function () {
        this.setLoading(false);
      }.bind(this));
  };

  ProductShippingCalculator.prototype.renderResults = function (data) {
    if (!this.results) {
      return;
    }

    this.results.innerHTML = '';

    if (data.estimate_label) {
      this.results.appendChild(this.buildEstimateNode(data.estimate_label));
    }

    var rates = Array.isArray(data.rates) ? data.rates.slice() : [];
    var fastestIndex = this.findFastestRateIndex(rates);

    rates.forEach(function (rate, index) {
      this.results.appendChild(this.buildRateNode(rate, index === fastestIndex));
    }.bind(this));

    if (data.free_shipping_note) {
      var notice = document.createElement('div');
      notice.className = 'useup-me-product-shipping__free-shipping';
      notice.textContent = data.free_shipping_note;
      this.results.appendChild(notice);
    }

    this.results.hidden = false;
  };

  ProductShippingCalculator.prototype.buildEstimateNode = function (text) {
    var node = document.createElement('div');
    var icon = document.createElement('span');
    var content = document.createElement('span');
    var betweenMatch = text.match(/^Receba entre (.+) e (.+)\.$/);
    var untilMatch = text.match(/^Chega até (.+)\.$/);

    node.className = 'useup-me-product-shipping__estimate';
    icon.className = 'useup-me-product-shipping__estimate-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = this.getCalendarIconSvg();
    content.className = 'useup-me-product-shipping__estimate-text';

    if (betweenMatch) {
      content.appendChild(document.createTextNode('Receba entre '));
      content.appendChild(this.buildStrongNode(betweenMatch[1]));
      content.appendChild(document.createTextNode(' e '));
      content.appendChild(this.buildStrongNode(betweenMatch[2]));
      content.appendChild(document.createTextNode('.'));
    } else if (untilMatch) {
      content.appendChild(document.createTextNode('Chega até '));
      content.appendChild(this.buildStrongNode(untilMatch[1]));
      content.appendChild(document.createTextNode('.'));
    } else {
      content.textContent = text;
    }

    node.appendChild(icon);
    node.appendChild(content);

    return node;
  };

  ProductShippingCalculator.prototype.buildStrongNode = function (text) {
    var strong = document.createElement('strong');
    strong.textContent = text;
    return strong;
  };

  ProductShippingCalculator.prototype.buildRateNode = function (rate, isFastest) {
    var node = document.createElement('div');
    var main = document.createElement('span');
    var methodWrap = document.createElement('span');
    var methodIcon = document.createElement('span');
    var methodLabel = document.createElement('span');
    var cost = document.createElement('strong');

    node.className = 'useup-me-product-shipping__rate';
    main.className = 'useup-me-product-shipping__rate-main';
    methodWrap.className = 'useup-me-product-shipping__rate-label';

    if (isFastest) {
      methodWrap.appendChild(this.buildFastestBadge());
    } else {
      methodIcon.className = 'useup-me-product-shipping__method-icon';
      methodIcon.setAttribute('aria-hidden', 'true');
      methodIcon.innerHTML = this.getMethodTruckIconSvg();
      methodWrap.appendChild(methodIcon);
    }

    methodLabel.textContent = rate && rate.label ? rate.label : 'Entrega';
    cost.textContent = rate && rate.cost ? rate.cost : '';

    methodWrap.appendChild(methodLabel);
    main.appendChild(methodWrap);
    node.appendChild(main);
    node.appendChild(cost);

    return node;
  };

  ProductShippingCalculator.prototype.buildFastestBadge = function () {
    var badge = document.createElement('button');
    var icon = document.createElement('span');
    var tooltip = document.createElement('span');

    badge.type = 'button';
    badge.className = 'useup-me-product-shipping__fastest-badge';
    badge.setAttribute('aria-expanded', 'false');
    badge.setAttribute('aria-label', 'Entrega mais rápida');

    icon.className = 'useup-me-product-shipping__fastest-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = this.getFastTruckIconSvg();

    tooltip.className = 'useup-me-tooltip useup-me-tooltip--fastest';
    tooltip.hidden = true;
    tooltip.textContent = 'Entrega mais rápida';

    badge.appendChild(icon);
    badge.appendChild(tooltip);

    return badge;
  };

  ProductShippingCalculator.prototype.toggleFastestTooltip = function (trigger) {
    var isExpanded = trigger.getAttribute('aria-expanded') === 'true';

    this.closeFastestTooltips(trigger);

    if (isExpanded) {
      return;
    }

    trigger.setAttribute('aria-expanded', 'true');
    trigger.classList.add('is-open');
    this.getFastestTooltip(trigger).hidden = false;
  };

  ProductShippingCalculator.prototype.closeFastestTooltips = function (except) {
    var triggers = this.element.querySelectorAll('.useup-me-product-shipping__fastest-badge');

    Array.prototype.forEach.call(triggers, function (trigger) {
      if (except && trigger === except) {
        return;
      }

      trigger.setAttribute('aria-expanded', 'false');
      trigger.classList.remove('is-open');
      this.getFastestTooltip(trigger).hidden = true;
    }.bind(this));
  };

  ProductShippingCalculator.prototype.getFastestTooltip = function (trigger) {
    return trigger.querySelector('.useup-me-tooltip--fastest');
  };

  ProductShippingCalculator.prototype.findFastestRateIndex = function (rates) {
    var bestIndex = -1;
    var bestDate = null;
    var bestDays = null;

    rates.forEach(function (rate, index) {
      var text = rate && rate.delivery_time ? String(rate.delivery_time) : '';
      var nearestDate = this.extractNearestDate(text);
      var days = this.extractBusinessDays(text);

      if (nearestDate) {
        if (!bestDate || nearestDate < bestDate) {
          bestDate = nearestDate;
          bestIndex = index;
        }
        return;
      }

      if (bestDate) {
        return;
      }

      if (days !== null && (bestDays === null || days < bestDays)) {
        bestDays = days;
        bestIndex = index;
      }
    }.bind(this));

    return bestIndex;
  };

  ProductShippingCalculator.prototype.extractNearestDate = function (text) {
    var matches = String(text || '').match(/(\d{2})\/(\d{2})/g);
    var now = new Date();
    var nearest = null;

    if (!matches || !matches.length) {
      return null;
    }

    matches.forEach(function (match) {
      var parts = match.split('/');
      var day = parseInt(parts[0], 10);
      var month = parseInt(parts[1], 10) - 1;
      var year = now.getFullYear();
      var candidate = new Date(year, month, day, 12, 0, 0, 0);

      if (candidate < new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 0, 0, 0, 0)) {
        candidate.setFullYear(year + 1);
      }

      if (!nearest || candidate < nearest) {
        nearest = candidate;
      }
    });

    return nearest;
  };

  ProductShippingCalculator.prototype.extractBusinessDays = function (text) {
    var match = String(text || '').match(/(\d+)\s*dias?/i);

    if (!match) {
      return null;
    }

    return parseInt(match[1], 10);
  };

  ProductShippingCalculator.prototype.getCalendarIconSvg = function () {
    return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M8 3.75v3.5M16 3.75v3.5M4 9.25h16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>';
  };

  ProductShippingCalculator.prototype.getMethodTruckIconSvg = function () {
    return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M3.5 8h10v6.25H12a2.25 2.25 0 0 0-4.5 0H6A2.25 2.25 0 0 0 1.5 14V10a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M13.5 10h3.2l2.3 2.5v1.75h-1.1a2.25 2.25 0 0 0-4.4 0h-.1V10Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="8.75" cy="16.5" r="1.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle><circle cx="16.25" cy="16.5" r="1.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle></svg>';
  };

  ProductShippingCalculator.prototype.getFastTruckIconSvg = function () {
    return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M2.5 10h2.75M1.5 13h3.75M4 7h2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path><path d="M6.5 8h8.5v6H14a2.25 2.25 0 0 0-4.5 0H9A2.25 2.25 0 0 0 4.5 14v-4A2 2 0 0 1 6.5 8Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 10h2.8l2.2 2.35V14h-1.1a2.25 2.25 0 0 0-4.4 0H15v-4Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="10.75" cy="16.25" r="1.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle><circle cx="17.25" cy="16.25" r="1.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle></svg>';
  };

  document.addEventListener('DOMContentLoaded', function () {
    var blocks = document.querySelectorAll('.useup-me-product-shipping');

    blocks.forEach(function (block) {
      new ProductShippingCalculator(block);
    });
  });
})(window, document, window.jQuery);
