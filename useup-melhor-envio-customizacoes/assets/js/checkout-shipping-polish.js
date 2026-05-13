(function($) {
    'use strict';

    var config = window.useupMeCheckoutPolish || {};

    function normalizeMethodName(text) {
        return text
            .replace(/^JeT/i, 'J&T Express')
            .replace(/^J&T(?!\s*Express)/i, 'J&T Express')
            .replace(/Jadlog\s*\.?\s*Com/i, 'Jadlog')
            .replace(/\bSedex\b/i, 'SEDEX');
    }

    function polishShippingMethods() {
        var $methods = $('.woocommerce-shipping-methods li, ul#shipping_method li');

        $methods.each(function() {
            var $li = $(this);
            var $input = $li.find('input[type="radio"]').first();
            var $label = $li.find('label').first();

            if (!$input.length || !$label.length) {
                return;
            }

            $li.toggleClass('is-selected', $input.is(':checked'));

            var originalText = $.trim($label.text());
            var $amount = $label.find('.amount').first();
            var priceHtml = $amount.length ? $('<div>').append($amount.clone()).html() : '';
            var textWithoutPrice = originalText.replace(/R\$\s?\d+(?:[.,]\d{2})?/g, '').trim();

            textWithoutPrice = normalizeMethodName(textWithoutPrice);

            var methodName = textWithoutPrice;
            var estimate = '';
            var match = textWithoutPrice.match(/^(.*?)\s*\(?\s*(Chega até\s*[^)]*)\)?/i);

            if (match) {
                methodName = $.trim(match[1]).replace(/[:\-–—]+$/, '').trim();
                estimate = $.trim(match[2]).replace(/:$/, '');
            }

            if (!priceHtml) {
                var priceMatch = originalText.match(/R\$\s?\d+(?:[.,]\d{2})?/);
                priceHtml = priceMatch ? priceMatch[0] : '';
            }

            var html = '<span class="useup-shipping-label-main">' +
                '<span class="useup-shipping-method-name">' + methodName + '</span>';

            if (estimate) {
                html += '<span class="useup-shipping-estimate">— ' + estimate + '</span>';
            }

            html += '</span>';

            if (priceHtml) {
                html += '<span class="useup-shipping-price">' + priceHtml + '</span>';
            }

            $label.html(html);
        });
    }

    function hideAdminTips() {
        var tipPrefix = config.tipPrefix || 'Dica:';
        var tipContains = config.tipContains || 'Adicione pelo menos 5 dias úteis';

        $('.woocommerce-shipping-methods, ul#shipping_method').each(function() {
            var $container = $(this).parent();

            $container.find('p, small, div').each(function() {
                var $el = $(this);
                var text = $.trim($el.text());

                if (text.indexOf(tipPrefix) === 0 || text.indexOf(tipContains) !== -1) {
                    $el.addClass('useup-shipping-tip-hidden').hide();
                }
            });
        });
    }

    function polishTagsOption() {
        var tagsText = config.tagsText || 'Enviar tags lisas com o pedido';

        $('.woocommerce-checkout label').each(function() {
            var $label = $(this);
            var text = $.trim($label.text());

            if (text.indexOf(tagsText) === -1) {
                return;
            }

            var $wrapper = $label.closest('.form-row, p, li, .woocommerce-additional-fields__field-wrapper > div, div');
            var $input = $label.find('input[type="checkbox"]').first();

            if (!$input.length && $wrapper.length) {
                $input = $wrapper.find('input[type="checkbox"]').first();
            }

            if ($wrapper.length) {
                $wrapper.addClass('useup-me-tags-option');
            }

            $label.addClass('useup-me-tags-option__label');

            if ($input.length) {
                $input.addClass('useup-me-tags-option__input');
            }
        });
    }

    function initUseupCheckoutPolish() {
        polishShippingMethods();
        hideAdminTips();
        polishTagsOption();
    }

    $(document).ready(initUseupCheckoutPolish);

    $(document.body).on('updated_checkout update_checkout', function() {
        setTimeout(initUseupCheckoutPolish, 50);
    });

    $(document).on('change', '.woocommerce-shipping-methods input[type="radio"], ul#shipping_method input[type="radio"]', function() {
        polishShippingMethods();
    });
})(jQuery);
