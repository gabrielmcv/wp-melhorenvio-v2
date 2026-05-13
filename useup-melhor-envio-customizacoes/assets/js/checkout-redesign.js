(function($) {
    'use strict';

    var config = window.useupMeCheckoutRedesign || {};

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeShippingMethodName(text) {
        return text
            .replace(/\bJeT\b/i, 'J&T Express')
            .replace(/\bJ&T\b(?!\s*Express)/i, 'J&T Express')
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

    function ensureSection($container, key, title) {
        var selector = '.useup-checkout-section[data-section="' + key + '"]';
        var $section = $container.children(selector);

        if ($section.length) {
            $section.find('.useup-checkout-section-title').text(title);
            return $section;
        }

        $section = $('<section class="useup-checkout-section" data-section="' + key + '"><h3 class="useup-checkout-section-title"></h3><div class="useup-checkout-field-grid"></div></section>');
        $section.find('.useup-checkout-section-title').text(title);
        $container.append($section);

        return $section;
    }

    function detectBillingSection($row) {
        var id = ($row.attr('id') || '').toLowerCase();
        var $field = $row.find('input, select, textarea').first();
        var name = (($field.attr('name') || '') + ' ' + ($field.attr('id') || '')).toLowerCase();
        var token = id + ' ' + name;

        if (/phone|cell|celular|email|mail|whatsapp/.test(token)) {
            return 'contact';
        }

        if (/country|postcode|cep|address|numero|number|bairro|neigh|city|estado|state/.test(token)) {
            return 'address';
        }

        return 'personal';
    }

    function groupBillingFields() {
        $('.woocommerce-billing-fields').each(function() {
            var $billing = $(this);
            var $heading = $billing.children('h3').first();
            var $wrapper = $billing.find('> .woocommerce-billing-fields__field-wrapper').first();

            if (!$wrapper.length) {
                return;
            }

            var $groups = $billing.children('.useup-checkout-field-groups');

            if (!$groups.length) {
                $groups = $('<div class="useup-checkout-field-groups"></div>');

                if ($heading.length) {
                    $groups.insertAfter($heading);
                } else {
                    $billing.prepend($groups);
                }
            }

            var sectionTitles = config.sectionTitles || {};
            var $personalSection = ensureSection($groups, 'personal', sectionTitles.personal || 'Dados pessoais');
            var $addressSection = ensureSection($groups, 'address', sectionTitles.address || 'Endereço');
            var $contactSection = ensureSection($groups, 'contact', sectionTitles.contact || 'Contato');

            var $allRows = $billing.find('> .woocommerce-billing-fields__field-wrapper > .form-row, .useup-checkout-field-grid > .form-row');

            $allRows.each(function() {
                var $row = $(this);
                var section = detectBillingSection($row);
                var $target = $personalSection.find('.useup-checkout-field-grid');

                if (section === 'address') {
                    $target = $addressSection.find('.useup-checkout-field-grid');
                } else if (section === 'contact') {
                    $target = $contactSection.find('.useup-checkout-field-grid');
                }

                $target.append($row);
            });

        });
    }

    function moveMobileSummary() {
        var $form = $('form.checkout.woocommerce-checkout').first();
        var $toggle = $('.useup-checkout-mobile-summary').first();
        var $customerDetails = $('#customer_details').first();

        if (!$form.length || !$toggle.length || !$customerDetails.length) {
            return;
        }

        if ($toggle.prev()[0] !== $customerDetails[0]) {
            $toggle.insertBefore($customerDetails);
        }

        $toggle.prop('hidden', false);
    }

    function updateMobileSummaryAmount() {
        var $amount = $('.woocommerce-checkout-review-order-table .order-total .amount').first();
        var amountText = $.trim($amount.text());

        if (!amountText) {
            return;
        }

        $('.useup-checkout-mobile-summary__amount').text(amountText);
    }

    function toggleMobileSummary(open) {
        var $body = $('body');
        var shouldOpen = typeof open === 'boolean' ? open : !$body.hasClass('useup-checkout-review-open');

        $body.toggleClass('useup-checkout-review-open', shouldOpen);
        $('.useup-checkout-mobile-summary__button').attr('aria-expanded', shouldOpen ? 'true' : 'false');
        $('.useup-checkout-mobile-summary__label').text(
            shouldOpen ? (config.hideSummaryLabel || 'Ocultar resumo do pedido') : (config.mobileSummaryLabel || 'Ver resumo do pedido')
        );
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

            if ($label.find('.useup-shipping-label-main').length) {
                return;
            }

            var originalText = $.trim($label.text());
            var $amount = $label.find('.amount').first();
            var priceHtml = $amount.length ? $('<div>').append($amount.clone()).html() : '';
            var textWithoutPrice = originalText.replace(/R\$\s?\d+(?:[.,]\d{2})?/g, '').trim();

            textWithoutPrice = normalizeShippingMethodName(textWithoutPrice);

            var methodName = textWithoutPrice;
            var estimate = '';
            var match = textWithoutPrice.match(/^(.*?)\s*\(?\s*(Chega até\s*[^)]*)\)?/i);

            if (match) {
                methodName = $.trim(match[1]).replace(/[:\-–—]+$/, '').trim();
                estimate = $.trim(match[2]).replace(/:$/, '');
            }

            if (!priceHtml) {
                var priceMatch = originalText.match(/R\$\s?\d+(?:[.,]\d{2})?/);
                priceHtml = priceMatch ? escapeHtml(priceMatch[0]) : '';
            }

            var html = '<span class="useup-shipping-label-main">' +
                '<span class="useup-shipping-method-name">' + escapeHtml(methodName) + '</span>';

            if (estimate) {
                html += '<span class="useup-shipping-estimate">— ' + escapeHtml(estimate) + '</span>';
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

                if (!text) {
                    return;
                }

                if (text.indexOf(tipPrefix) === 0 || text.indexOf(tipContains) !== -1) {
                    $el.addClass('useup-shipping-tip-hidden').hide();
                }
            });
        });
    }

    function polishTagsOption() {
        var tagsText = (config.tagsText || 'Enviar tags lisas com o pedido').toLowerCase();

        $('.woocommerce-checkout label').each(function() {
            var $label = $(this);
            var text = $.trim($label.text()).toLowerCase();

            if (text.indexOf(tagsText) === -1) {
                return;
            }

            var $wrapper = $label.closest('.form-row, p, li, .woocommerce-additional-fields__field-wrapper > div, div');
            var $input = $label.find('input[type="checkbox"]').first();

            if (!$input.length && $wrapper.length) {
                $input = $wrapper.find('input[type="checkbox"]').first();
            }

            if (!$wrapper.length || !$input.length) {
                return;
            }

            $wrapper.addClass('useup-me-tags-option');
            $label.addClass('useup-me-tags-option__label');
            $input.addClass('useup-me-tags-option__input');

            if (text.indexOf('opcional') === -1 && !$label.find('.useup-me-tags-option__optional').length) {
                $label.append('<span class="useup-me-tags-option__optional">opcional</span>');
            }
        });
    }

    function polishPaymentMethods() {
        $('#payment .payment_methods > li').each(function() {
            var $li = $(this);
            var $input = $li.find('> input[type="radio"]').first();
            var $label = $li.find('> label').first();
            var $box = $li.find('> .payment_box').first();

            if (!$input.length || !$label.length) {
                return;
            }

            $li.toggleClass('is-selected', $input.is(':checked'));

            var labelText = $.trim($label.text());
            var normalizedName = normalizePaymentName(labelText);
            var lowerName = normalizedName.toLowerCase();
            var badgeText = '';

            if (lowerName.indexOf('pix') !== -1) {
                badgeText = config.pixBadge || '5% no PIX';
            } else if (lowerName.indexOf('cartão') !== -1 || lowerName.indexOf('credito') !== -1 || lowerName.indexOf('crédito') !== -1) {
                badgeText = config.cardBadge || 'até 12x';
            }

            if (!$label.find('.useup-payment-badge').length && badgeText) {
                $label.append('<span class="useup-payment-badge">' + escapeHtml(badgeText) + '</span>');
            }

            if ((lowerName.indexOf('cartão') !== -1 || lowerName.indexOf('credito') !== -1 || lowerName.indexOf('crédito') !== -1) && !$li.find('.useup-payment-note').length) {
                var $note = $('<div class="useup-payment-note"></div>').text(config.cardHelper || 'Você será redirecionado para concluir o pagamento com segurança.');

                if ($box.length) {
                    $box.before($note);
                } else {
                    $label.after($note);
                }
            }
        });
    }

    function initCheckoutRedesign() {
        groupBillingFields();
        moveMobileSummary();
        updateMobileSummaryAmount();
        polishShippingMethods();
        hideAdminTips();
        polishTagsOption();
        polishPaymentMethods();
    }

    $(document).ready(function() {
        initCheckoutRedesign();
        toggleMobileSummary(false);
    });

    $(document).on('click', '.useup-checkout-mobile-summary__button', function() {
        toggleMobileSummary();
    });

    $(document.body).on('updated_checkout update_checkout', function() {
        window.setTimeout(function() {
            initCheckoutRedesign();
        }, 80);
    });

    $(document).on('change', '.woocommerce-shipping-methods input[type="radio"], ul#shipping_method input[type="radio"], #payment .payment_methods input[type="radio"]', function() {
        initCheckoutRedesign();
    });
})(jQuery);
