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

    if (this.state.currentPostcode) {
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

    if (Array.isArray(data.rates)) {
      data.rates.forEach(function (rate) {
        this.results.appendChild(this.buildRateNode(rate));
      }.bind(this));
    }

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
    node.className = 'useup-me-product-shipping__estimate';

    var betweenMatch = text.match(/^Receba entre (.+) e (.+)\.$/);
    var untilMatch = text.match(/^Chega até (.+)\.$/);

    if (betweenMatch) {
      node.appendChild(document.createTextNode('Receba entre '));
      node.appendChild(this.buildStrongNode(betweenMatch[1]));
      node.appendChild(document.createTextNode(' e '));
      node.appendChild(this.buildStrongNode(betweenMatch[2]));
      node.appendChild(document.createTextNode('.'));
      return node;
    }

    if (untilMatch) {
      node.appendChild(document.createTextNode('Chega até '));
      node.appendChild(this.buildStrongNode(untilMatch[1]));
      node.appendChild(document.createTextNode('.'));
      return node;
    }

    node.textContent = text;

    return node;
  };

  ProductShippingCalculator.prototype.buildStrongNode = function (text) {
    var strong = document.createElement('strong');
    strong.textContent = text;
    return strong;
  };

  ProductShippingCalculator.prototype.buildRateNode = function (rate) {
    var node = document.createElement('div');
    var label = document.createElement('span');
    var cost = document.createElement('strong');

    node.className = 'useup-me-product-shipping__rate';
    label.textContent = rate && rate.label ? rate.label : 'Entrega';
    cost.textContent = rate && rate.cost ? rate.cost : '';

    node.appendChild(label);
    node.appendChild(cost);

    return node;
  };

  document.addEventListener('DOMContentLoaded', function () {
    var blocks = document.querySelectorAll('.useup-me-product-shipping');

    blocks.forEach(function (block) {
      new ProductShippingCalculator(block);
    });
  });
})(window, document, window.jQuery);
