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

  function fixMojibakeText(text) {
    var value = String(text || '');

    if (!/[\u00c3\u00e2]/.test(value)) {
      return value;
    }

    try {
      return decodeURIComponent(escape(value));
    } catch (error) {
      return value;
    }
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
    this.normalizeVisibleCopy();
    this.initialButtonText = this.button ? this.button.textContent : (strings.seeOptions || 'Ver op\u00e7\u00f5es');
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

  ProductShippingCalculator.prototype.normalizeVisibleCopy = function () {
    var savedMessage = this.element.querySelector('.useup-me-product-shipping__current-cep > span');
    var description = this.element.querySelector('.useup-me-product-shipping__description');

    if (savedMessage) {
      savedMessage.innerHTML = 'Sua entrega ser\u00e1 calculada para o CEP <strong class="useup-me-product-shipping__postcode-value">' +
        fixMojibakeText(this.postcodeValue ? this.postcodeValue.textContent : '') +
        '</strong>.';
      this.postcodeValue = savedMessage.querySelector('.useup-me-product-shipping__postcode-value');
    }

    if (this.changeButton) {
      this.changeButton.textContent = 'Trocar CEP';
    }

    if (description) {
      description.textContent = 'Informe seu CEP para ver quando sua joia chega at\u00e9 voc\u00ea.';
    }

    if (this.button) {
      this.button.textContent = 'Ver op\u00e7\u00f5es';
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
      this.postcodeValue.textContent = fixMojibakeText(formattedPostcode);
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

    this.error.textContent = fixMojibakeText(message);
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
        this.showError(strings.invalidPostcode || 'Informe um CEP v\u00e1lido com 8 n\u00fameros.');
      }
      return;
    }

    this.clearError();
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
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : (strings.genericError || 'N\u00e3o foi poss\u00edvel calcular o frete agora. Tente novamente em instantes.'));
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
        this.showError(error && error.message ? error.message : (strings.genericError || 'N\u00e3o foi poss\u00edvel calcular o frete agora. Tente novamente em instantes.'));
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

    var safeData = data || {};
    var rates = Array.isArray(safeData.rates) ? safeData.rates.slice() : [];

    safeData.estimate_label = fixMojibakeText(safeData.estimate_label);
    safeData.free_shipping_note = fixMojibakeText(safeData.free_shipping_note);

    rates = rates.map(function (rate) {
      return {
        label: fixMojibakeText(rate && rate.label),
        cost: fixMojibakeText(rate && rate.cost),
        delivery_time: fixMojibakeText(rate && rate.delivery_time),
        raw_cost: rate && rate.raw_cost,
        id: rate && rate.id
      };
    });

    var hasFreeShippingRate = rates.some(function (rate) {
      return parseFloat(rate && rate.raw_cost) === 0;
    });

    if (safeData.estimate_label) {
      this.results.appendChild(this.buildEstimateNode(safeData.estimate_label));
    }

    var fastestIndex = this.findFastestRateIndex(rates);

    rates.forEach(function (rate, index) {
      this.results.appendChild(this.buildRateNode(rate, index === fastestIndex));
    }.bind(this));

    if (safeData.free_shipping_note && !hasFreeShippingRate) {
      var notice = document.createElement('div');
      notice.className = 'useup-me-product-shipping__free-shipping';
      notice.textContent = safeData.free_shipping_note;
      this.results.appendChild(notice);
    }

    this.results.hidden = false;
  };

  ProductShippingCalculator.prototype.buildEstimateNode = function (text) {
    var node = document.createElement('div');
    var icon = document.createElement('span');
    var content = document.createElement('span');
    var safeText = fixMojibakeText(text);
    var betweenMatch = safeText.match(/^Receba entre (.+) e (.+)\.$/);
    var untilMatch = safeText.match(/^Chega at\u00e9 (.+)\.$/);

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
      content.appendChild(document.createTextNode('Chega at\u00e9 '));
      content.appendChild(this.buildStrongNode(untilMatch[1]));
      content.appendChild(document.createTextNode('.'));
    } else {
      content.textContent = safeText;
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

  ProductShippingCalculator.prototype.formatRateCost = function (value) {
    return fixMojibakeText(value).replace(/R\$\s*(?=\d)/g, 'R$ ');
  };

  ProductShippingCalculator.prototype.buildRateNode = function (rate, isFastest) {
    var node = document.createElement('div');
    var main = document.createElement('span');
    var methodWrap = document.createElement('span');
    var methodLabel = document.createElement('span');
    var cost = document.createElement('strong');
    var rawCost = parseFloat(rate && rate.raw_cost);
    var isFreeShipping = !isNaN(rawCost) && rawCost === 0;

    node.className = 'useup-me-product-shipping__rate';
    main.className = 'useup-me-product-shipping__rate-main';
    methodWrap.className = 'useup-me-product-shipping__rate-label';

    methodLabel.textContent = rate && rate.label ? fixMojibakeText(rate.label) : 'Entrega';
    cost.className = isFreeShipping ? 'useup-me-free-shipping-label useup-me-product-shipping__free-label' : '';
    cost.textContent = isFreeShipping ? 'FRETE GRÁTIS' : this.formatRateCost(rate && rate.cost ? rate.cost : '');

    methodWrap.appendChild(methodLabel);

    if (isFastest) {
      methodWrap.appendChild(this.buildFastestLabel());
    }

    main.appendChild(methodWrap);
    node.appendChild(main);
    node.appendChild(cost);

    return node;
  };

  ProductShippingCalculator.prototype.buildFastestLabel = function () {
    var label = document.createElement('span');

    label.className = 'useup-me-product-shipping__fastest-label';
    label.textContent = '\u2014 mais r\u00e1pido';
    label.setAttribute('title', 'Entrega mais r\u00e1pida');
    label.setAttribute('aria-label', 'Entrega mais r\u00e1pida');

    return label;
  };

  ProductShippingCalculator.prototype.findFastestRateIndex = function (rates) {
    var bestIndex = -1;
    var bestDate = null;
    var bestDays = null;

    rates.forEach(function (rate, index) {
      var text = rate && rate.delivery_time ? fixMojibakeText(rate.delivery_time) : '';
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

  document.addEventListener('DOMContentLoaded', function () {
    var blocks = document.querySelectorAll('.useup-me-product-shipping');

    blocks.forEach(function (block) {
      new ProductShippingCalculator(block);
    });
  });
})(window, document, window.jQuery);
