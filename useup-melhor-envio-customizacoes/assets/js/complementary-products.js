(function (window, document) {
  'use strict';

  var localized = window.useupMeComplementaryProducts || {};

  function setMessage(product, message) {
    var node = product.querySelector('.useup-complementary-product__message');

    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.hidden = !message;
  }

  function updatePrice(product, option) {
    var wholesaleNode = product.querySelector('.useup-complementary-product__wholesale-amount');
    var retailNode = product.querySelector('.useup-complementary-product__retail-amount');
    var wholesaleText = option ? option.getAttribute('data-wholesale-text') : product.getAttribute('data-default-wholesale-text');
    var retailText = option ? option.getAttribute('data-retail-text') : product.getAttribute('data-default-retail-text');

    if (wholesaleNode && wholesaleText) {
      wholesaleNode.textContent = wholesaleText;
    }

    if (retailNode && retailText) {
      retailNode.textContent = retailText;
    }
  }

  function syncVariation(product) {
    var input = product.querySelector('.useup-complementary-product__variation-id');
    var selectedOption = product.querySelector('.useup-complementary-product__variation-option.is-selected');

    if (!input) {
      return;
    }

    if (selectedOption) {
      input.value = selectedOption.getAttribute('data-variation-id') || '';
      updatePrice(product, selectedOption);
      return;
    }

    input.value = '';
    updatePrice(product, null);
  }

  function selectVariation(product, option) {
    product.querySelectorAll('.useup-complementary-product__variation-option').forEach(function (button) {
      var isSelected = button === option;
      button.classList.toggle('is-selected', isSelected);
      button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });

    setMessage(product, '');
    syncVariation(product);
  }

  function validateProduct(product) {
    var checkbox = product.querySelector('.useup-complementary-product__checkbox input[type="checkbox"]');
    var variationInput = product.querySelector('.useup-complementary-product__variation-id');
    var isVariable = product.getAttribute('data-is-variable') === '1';

    if (!checkbox || !checkbox.checked) {
      setMessage(product, '');
      return true;
    }

    if (!isVariable) {
      setMessage(product, '');
      return true;
    }

    if (variationInput && variationInput.value) {
      setMessage(product, '');
      return true;
    }

    setMessage(product, localized.chooseVariationText || 'Escolha o tamanho da corrente para continuar.');
    return false;
  }

  function bindProduct(product) {
    var options = product.querySelectorAll('.useup-complementary-product__variation-option');
    var checkbox = product.querySelector('.useup-complementary-product__checkbox input[type="checkbox"]');

    options.forEach(function (option) {
      option.addEventListener('click', function () {
        selectVariation(product, option);
      });
    });

    if (checkbox) {
      checkbox.addEventListener('change', function () {
        if (!checkbox.checked) {
          setMessage(product, '');
        }
      });
    }

    if (options.length === 1 && !product.querySelector('.useup-complementary-product__variation-option.is-selected')) {
      selectVariation(product, options[0]);
      return;
    }

    syncVariation(product);
  }

  function bindBlock(block) {
    var form = block.closest('form.cart');

    block.querySelectorAll('.useup-complementary-product').forEach(bindProduct);

    if (!form || form.getAttribute('data-useup-complementary-bound') === '1') {
      return;
    }

    form.setAttribute('data-useup-complementary-bound', '1');
    form.addEventListener('submit', function (event) {
      var isValid = true;

      block.querySelectorAll('.useup-complementary-product').forEach(function (product) {
        if (!validateProduct(product)) {
          isValid = false;
        }
      });

      if (!isValid) {
        event.preventDefault();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-useup-complementary-products="1"]').forEach(bindBlock);
  });
})(window, document);
