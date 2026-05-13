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

    function normalizePaymentName(text) {
        if (/pix/i.test(text)) {
            return 'PIX';
        }

        if (/cart[aã]o|cr[eé]dito|infinitepay/i.test(text)) {
            return 'Cartão de crédito';
        }

        return text;
    }

    function dedupeByText($elements) {
        var seen = {};

        $elements.each(function() {
            var $el = $(this);
            var key = $.trim($el.text()).replace(/\s+/g, ' ').toLowerCase();

            if (!key) {
                return;
            }

            if (seen[key]) {
                $el.remove();
                return;
            }

            seen[key] = true;
        });
    }

    function removeDuplicateCheckoutBits() {
        dedupeByText($('.useup-checkout-polish .useup-payment-note'));
        dedupeByText($('.useup-checkout-polish .useup-payment-badge'));
        dedupeByText($('.useup-checkout-polish .useup-me-tags-option__optional'));
        dedupeByText($('.useup-checkout-polish .woocommerce-shipping-totals .useup-shipping-tip-hidden'));

        $('.useup-checkout-polish .woocommerce-shipping-totals, .useup-checkout-polish tr.woocommerce-shipping-totals.shipping').each(function() {
            var $shippingBox = $(this);
            var $lists = $shippingBox.find('ul#shipping_method, .woocommerce-shipping-methods');

            if ($lists.length > 1) {
                $lists.not(':first').remove();
            }
        });

        $('.useup-checkout-polish .useup-me-tags-option').each(function() {
            var $wrapper = $(this);
            var $inputs = $wrapper.find('input[type="checkbox"]');

            if ($inputs.length > 1) {
                $inputs.not(':first').remove();
            }
        });
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

            if (text.toLowerCase().indexOf('opcional') === -1 && !$label.find('.useup-me-tags-option__optional').length) {
                $label.append('<span class="useup-me-tags-option__optional">opcional</span>');
            }
        });
    }

    function polishPaymentMethods() {
        $('#payment .payment_methods > li').each(function() {
            var $li = $(this);
            var $input = $li.find('input[type="radio"]').first();
            var $label = $li.find('label').first();
            var $box = $li.find('.payment_box').first();

            if (!$input.length || !$label.length) {
                return;
            }

            $li.toggleClass('is-selected', $input.is(':checked'));

            if (!$label.find('.useup-payment-badge').length) {
                var normalizedName = normalizePaymentName($.trim($label.text()));
                var badgeText = '';

                if (/pix/i.test(normalizedName)) {
                    badgeText = config.pixBadge || '5% no PIX';
                } else if (/cart[aã]o|cr[eé]dito/i.test(normalizedName)) {
                    badgeText = config.cardBadge || 'até 12x';
                }

                if (badgeText) {
                    $label.append('<span class="useup-payment-badge">' + badgeText + '</span>');
                }
            }

            if (!$li.find('.useup-payment-note').length && /cart[aã]o|cr[eé]dito|infinitepay/i.test($.trim($label.text()))) {
                $('<div class="useup-payment-note"></div>')
                    .text(config.cardHelper || 'Você será redirecionado para concluir o pagamento com segurança.')
                    .insertBefore($box);
            }
        });
    }

    function initUseupCheckoutPolish() {
        polishShippingMethods();
        hideAdminTips();
        polishTagsOption();
        polishPaymentMethods();
        removeDuplicateCheckoutBits();
    }

    $(document).ready(initUseupCheckoutPolish);

    $(document.body).on('updated_checkout update_checkout', function() {
        setTimeout(initUseupCheckoutPolish, 50);
    });

    $(document).on('change', '.woocommerce-shipping-methods input[type="radio"], ul#shipping_method input[type="radio"], #payment .payment_methods input[type="radio"]', function() {
        initUseupCheckoutPolish();
    });
})(jQuery);
