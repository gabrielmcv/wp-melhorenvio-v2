(function($) {
    'use strict';

    var config = window.useupMeCheckoutPolish || {};

    function normalizeComparableText(text) {
        var normalized = String(text || '').toLowerCase();

        if (normalized.normalize) {
            normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        return normalized.replace(/\s+/g, ' ').trim();
    }

    function normalizeMethodName(text) {
        return String(text || '')
            .replace(/^JeT/i, 'J&T Express')
            .replace(/^J&T(?!\s*Express)/i, 'J&T Express')
            .replace(/Jadlog\s*\.?\s*Com/i, 'Jadlog')
            .replace(/\bSedex\b/i, 'SEDEX');
    }

    function normalizePaymentName(text) {
        var comparable = normalizeComparableText(text);

        if (comparable.indexOf('pix') !== -1) {
            return 'PIX';
        }

        if (/(cartao|credito|infinitepay)/i.test(comparable)) {
            return 'Cart\u00e3o de cr\u00e9dito';
        }

        return $.trim(String(text || ''));
    }

    function stripCardHelperText(text) {
        return $.trim(String(text || '').replace(/\s+/g, ' ').replace(/\s*voc(?:e|\u00ea)\s+ser(?:a|\u00e1)\s+redirecionado\s+para\s+concluir\s+o\s+pagamento\s+com\s+seguran(?:ca|\u00e7a)\.?\s*/i, ' '));
    }

    function dedupeByText($elements) {
        var seen = {};

        $elements.each(function() {
            var $el = $(this);
            var key = normalizeComparableText($el.text());

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

    function escapeAttribute(value) {
        return String(value || '').replace(/"/g, '&quot;');
    }

    function extractQuantityText($scope) {
        var quantityMatch;
        var quantityText = '';
        var $quantity = $scope.find('.product-quantity').first();

        if ($quantity.length) {
            quantityMatch = $quantity.text().match(/(\d+)/);
            if (quantityMatch) {
                quantityText = quantityMatch[1];
            }
        }

        if (!quantityText) {
            quantityMatch = $scope.text().match(/[\u00d7x]\s*(\d+)/i);
            if (quantityMatch) {
                quantityText = quantityMatch[1];
            }
        }

        return 'x' + (quantityText || '1');
    }

    function extractProductNameHtml($cell) {
        var $clone = $cell.clone();

        $clone.find('.et-product-thumbnail, .product-thumbnail, .product-quantity, .variation, dl.variation, small').remove();

        return $.trim($clone.html()).replace(/\s*[\u00d7x]\s*\d+\s*$/i, '').trim();
    }

    function extractProductImage($cell) {
        var $image = $cell.find('.et-product-thumbnail img, .product-thumbnail img, img').first();
        var src = '';

        if ($image.length) {
            src = $image.attr('src') || $image.attr('data-src') || $image.attr('srcset') || '';
        }

        return {
            imageHtml: $image.length ? $('<div>').append($image.clone()).html() : '',
            imageSrc: src
        };
    }

    function getShippingHeadingMarkup() {
        return '' +
            '<div class="useup-shipping-heading">' +
                '<span class="useup-shipping-heading__icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' +
                        '<path d="M3 7h11v8H3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>' +
                        '<path d="M14 10h3l3 3v2h-6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>' +
                        '<circle cx="7.5" cy="17.5" r="1.5" fill="none" stroke="currentColor" stroke-width="1.6"></circle>' +
                        '<circle cx="17.5" cy="17.5" r="1.5" fill="none" stroke="currentColor" stroke-width="1.6"></circle>' +
                    '</svg>' +
                '</span>' +
                '<span class="useup-shipping-heading__title">Entrega e prazo</span>' +
            '</div>' +
            '<p class="useup-shipping-heading__description">Escolha como deseja receber sua joia.</p>';
    }

    function ensureShippingHeading() {
        $('.useup-checkout-polish .woocommerce-shipping-totals, .useup-checkout-polish tr.woocommerce-shipping-totals.shipping').each(function() {
            var $shippingBox = $(this);
            var $headingHost = $shippingBox.find('th').first();

            if (!$headingHost.length) {
                return;
            }

            if (!$headingHost.find('.useup-shipping-heading').length) {
                $headingHost.append(getShippingHeadingMarkup());
            }
        });
    }

    function hideFeaturedPix() {
        $('.useup-checkout-polish .pix-por-piggly--featured').hide();
    }

    function cleanupVisualCheckout() {
        $('.useup-checkout-polish .useup-checkout-product-card, .useup-checkout-polish .useup-checkout-total-card, .useup-checkout-polish .useup-checkout-pix-discount-card, .useup-checkout-polish .useup-checkout-security-note').remove();
        $('.useup-checkout-polish .useup-checkout-visual-source').removeClass('useup-checkout-visual-source');
    }

    function removeDuplicateCheckoutBits() {
        $('.useup-checkout-polish .useup-payment-note').remove();

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
            var match = textWithoutPrice.match(/^(.*?)\s*\(?\s*(Chega at(?:e|\u00e9)\s*[^)]*)\)?/i);

            if (match) {
                methodName = $.trim(match[1]).replace(/[:\-\u2013\u2014]+$/, '').trim();
                estimate = $.trim(match[2]).replace(/:$/, '');
            }

            if (!priceHtml) {
                var priceMatch = originalText.match(/R\$\s?\d+(?:[.,]\d{2})?/);
                priceHtml = priceMatch ? priceMatch[0] : '';
            }

            var html = '<span class="useup-shipping-label-main">' +
                '<span class="useup-shipping-method-name">' + methodName + '</span>';

            if (estimate) {
                html += '<span class="useup-shipping-estimate">&mdash; ' + estimate + '</span>';
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
        var tipContains = config.tipContains || 'Adicione pelo menos 5 dias \u00fateis';

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

            var $optional = $label.find('.optional, .useup-me-tags-option__optional').first();

            if (!$optional.length && text.toLowerCase().indexOf('opcional') === -1) {
                $optional = $('<span class="optional useup-me-tags-option__optional">opcional</span>');
                $label.append($optional);
            }

            if ($optional.length) {
                $optional
                    .addClass('useup-me-tags-option__optional')
                    .text($.trim($optional.text()).replace(/[()]/g, '').toUpperCase());
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

            var $labelClone = $label.clone();
            var $images = $label.find('img').detach();
            var rawLabelText;
            var methodName;
            var badgeText = '';

            $labelClone.find('.useup-payment-badge, .useup-payment-label-text').remove();

            rawLabelText = stripCardHelperText($.trim($labelClone.text()));
            methodName = normalizePaymentName(rawLabelText);

            if (/pix/i.test(methodName)) {
                badgeText = config.pixBadge || '5% NO PIX';
            } else if (/(cartao|credito)/i.test(normalizeComparableText(methodName))) {
                badgeText = config.cardBadge || 'AT\u00c9 12X';
            }

            $label.empty();
            $label.append('<span class="useup-payment-label-text"></span>');
            $label.find('.useup-payment-label-text').text(methodName || rawLabelText);

            if ($images.length) {
                $images.addClass('useup-payment-method-icon');
                $label.append($images);
            }

            if (badgeText) {
                $label.append('<span class="useup-payment-badge">' + badgeText + '</span>');
            }

            if ($box.length && /(cartao|credito|infinitepay)/i.test(normalizeComparableText(methodName))) {
                if (!$.trim($box.text()) && config.cardHelper) {
                    $box.text(config.cardHelper);
                }
            }
        });
    }

    function buildProductCard($review, $table) {
        var $items = $table.find('tbody tr.cart_item');
        var $subtotalRow = $table.find('tfoot tr.cart-subtotal').first();
        var $card;

        if (!$items.length) {
            return null;
        }

        $card = $('<div class="useup-checkout-product-card"></div>');

        $items.each(function(index) {
            var $row = $(this);
            var $nameCell = $row.find('.product-name').first();
            var $totalCell = $row.find('.product-total').first();
            var productNameHtml = extractProductNameHtml($nameCell) || 'Produto';
            var productPriceHtml = $.trim($totalCell.html()) || '';
            var quantityText = extractQuantityText($nameCell);
            var imageData = extractProductImage($nameCell);
            var thumbStyle = imageData.imageSrc ? ' style="background-image:url(&quot;' + escapeAttribute(imageData.imageSrc) + '&quot;);"' : '';
            var thumbInner = imageData.imageHtml || '<span class="useup-checkout-product-thumb-fallback">JOIA</span>';
            var rowClass = index === $items.length - 1 ? 'useup-checkout-product-row' : 'useup-checkout-product-row useup-checkout-product-row--with-divider';

            $card.append(
                '<div class="' + rowClass + '">' +
                    '<div class="useup-checkout-product-thumb"' + thumbStyle + '>' +
                        thumbInner +
                        '<span class="useup-checkout-product-qty-badge">' + quantityText + '</span>' +
                    '</div>' +
                    '<div class="useup-checkout-product-info">' +
                        '<div class="useup-checkout-product-name">' + productNameHtml + '</div>' +
                    '</div>' +
                    '<div class="useup-checkout-product-price">' + productPriceHtml + '</div>' +
                '</div>'
            );

            $row.addClass('useup-checkout-visual-source');
        });

        if ($subtotalRow.length) {
            $card.append(
                '<div class="useup-checkout-subtotal-row">' +
                    '<span class="useup-checkout-subtotal-label">Subtotal</span>' +
                    '<span class="useup-checkout-subtotal-value">' + $.trim($subtotalRow.find('td').first().html()) + '</span>' +
                '</div>'
            );

            $subtotalRow.addClass('useup-checkout-visual-source');
        }

        $table.before($card);

        return $card;
    }

    function findPixDiscountRow($table) {
        return $table.find('tfoot tr').filter(function() {
            var $row = $(this);
            var comparable = normalizeComparableText($row.find('th').first().text() || $row.text());

            if ($row.hasClass('cart-subtotal') || $row.hasClass('order-total') || $row.hasClass('shipping') || $row.hasClass('woocommerce-shipping-totals')) {
                return false;
            }

            return comparable.indexOf('pix') !== -1 || (comparable.indexOf('desconto') !== -1 && comparable.indexOf('pagamento') !== -1);
        }).first();
    }

    function buildPixDiscountCard($review, $table) {
        var $pixRow = findPixDiscountRow($table);
        var $card;
        var valueHtml;

        if (!$pixRow.length) {
            return null;
        }

        valueHtml = $.trim($pixRow.find('td').last().html() || '');

        $card = $(
            '<div class="useup-checkout-pix-discount-card">' +
                '<div class="useup-checkout-pix-discount-row">' +
                    '<span class="useup-checkout-pix-discount-label">Desconto PIX aplicado</span>' +
                    '<span class="useup-checkout-pix-discount-value">' + valueHtml + '</span>' +
                '</div>' +
                '<div class="useup-checkout-pix-discount-note">Economia no pagamento via PIX</div>' +
            '</div>'
        );

        $pixRow.addClass('useup-checkout-visual-source');
        $table.after($card);

        return $card;
    }

    function buildTotalCard($review, $table, $afterNode) {
        var $orderTotal = $table.find('tfoot tr.order-total').first();
        var $valueCell;
        var $amountClone;
        var amountHtml;
        var taxText;
        var $card;

        if (!$orderTotal.length) {
            return null;
        }

        $valueCell = $orderTotal.find('td').first();
        $amountClone = $valueCell.clone();
        $amountClone.find('small, .includes_tax').remove();
        amountHtml = $.trim($amountClone.html() || '');
        taxText = $.trim($valueCell.find('small, .includes_tax').first().text() || '');

        $card = $(
            '<div class="useup-checkout-total-card">' +
                '<div class="useup-checkout-total-row">' +
                    '<div class="useup-checkout-total-label">TOTAL</div>' +
                    '<div class="useup-checkout-total-summary">' +
                        '<div class="useup-checkout-total-amount">' + amountHtml + '</div>' +
                        (taxText ? '<div class="useup-checkout-total-tax">' + taxText + '</div>' : '') +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $orderTotal.addClass('useup-checkout-visual-source');

        if ($afterNode && $afterNode.length) {
            $afterNode.after($card);
        } else {
            $table.after($card);
        }

        return $card;
    }

    function ensureSecurityNote() {
        var $button = $('.useup-checkout-polish #place_order').first();

        if (!$button.length) {
            return;
        }

        $button.after(
            '<div class="useup-checkout-security-note">' +
                '<span class="useup-checkout-security-icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' +
                        '<path d="M12 3.5 18 6v5.1c0 4.05-2.4 7.3-6 8.9-3.6-1.6-6-4.85-6-8.9V6l6-2.5Z" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"></path>' +
                        '<path d="m9.75 11.75 1.5 1.5 3-3.25" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"></path>' +
                    '</svg>' +
                '</span>' +
                '<span class="useup-checkout-security-text">Pagamento processado em ambiente seguro</span>' +
            '</div>'
        );
    }

    function buildReviewOrderCards() {
        $('.useup-checkout-polish .woocommerce-checkout-review-order').each(function() {
            var $review = $(this);
            var $table = $review.find('.woocommerce-checkout-review-order-table').first();
            var $pixCard;

            if (!$table.length) {
                return;
            }

            buildProductCard($review, $table);
            $pixCard = buildPixDiscountCard($review, $table);
            buildTotalCard($review, $table, $pixCard);
        });
    }

    function initUseupCheckoutPolish() {
        cleanupVisualCheckout();
        ensureShippingHeading();
        polishShippingMethods();
        hideAdminTips();
        polishTagsOption();
        polishPaymentMethods();
        hideFeaturedPix();
        buildReviewOrderCards();
        ensureSecurityNote();
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
