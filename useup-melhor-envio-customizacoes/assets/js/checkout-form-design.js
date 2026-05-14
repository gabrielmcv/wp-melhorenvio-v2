(function($) {
    'use strict';

    var config = window.useupMeCheckoutFormDesign || {};

    function getSectionConfig(key, fallbackNumber, fallbackTitle) {
        var sections = config.sections || {};
        var section = sections[key] || {};

        return {
            number: section.number || fallbackNumber,
            title: section.title || fallbackTitle
        };
    }

    function getSelectorGroup(group, key, fallback) {
        var selectors = config.selectors || {};
        var groupSelectors = selectors[group] || {};
        var value = groupSelectors[key];

        if (Array.isArray(value) && value.length) {
            return value;
        }

        return fallback;
    }

    function findFirstMatch($scope, selectors) {
        var index;
        var $match;

        for (index = 0; index < selectors.length; index += 1) {
            $match = $scope.find(selectors[index]).first();

            if ($match.length) {
                return $match;
            }
        }

        return $();
    }

    function findFieldRow($scope, selectors) {
        var $match = findFirstMatch($scope, selectors);

        if (!$match.length) {
            return $();
        }

        return $match.closest('.form-row');
    }

    function createSection(key, fallbackNumber, fallbackTitle) {
        var sectionConfig = getSectionConfig(key, fallbackNumber, fallbackTitle);

        return $(
            '<section class="useup-checkout-form-section" data-useup-form-section="' + key + '">' +
                '<div class="useup-checkout-form-section__title">' +
                    '<span class="useup-checkout-form-section__number">' + sectionConfig.number + '</span>' +
                    '<span class="useup-checkout-form-section__text">' + sectionConfig.title + '</span>' +
                '</div>' +
            '</section>'
        );
    }

    function createGrid(className) {
        return $('<div class="useup-checkout-form-grid ' + className + '"></div>');
    }

    function appendIfPresent($target, $node) {
        if ($node && $node.length) {
            $target.append($node);
        }
    }

    function appendGridIfNotEmpty($section, $grid) {
        if ($grid.children().length) {
            $section.append($grid);
        }
    }

    function hideSourceIfEmpty($source) {
        if (!$source.length) {
            return;
        }

        if (!$source.find('.form-row, #ship-to-different-address, .shipping_address').length) {
            $source.addClass('useup-checkout-form-source-empty').attr('hidden', 'hidden');
        }
    }

    function designCheckoutForm() {
        var $checkout = $('form.checkout').first();
        var $billingFields = $checkout.find('.woocommerce-billing-fields').first();
        var $billingTitle = $billingFields.find('> h3, > .woocommerce-billing-fields__title').first();
        var $billingWrapper = $billingFields.find('.woocommerce-billing-fields__field-wrapper').first();
        var $additionalFields = $checkout.find('.woocommerce-additional-fields').first();
        var $shippingFields = $checkout.find('.woocommerce-shipping-fields').first();
        var $personalSection;
        var $addressSection;
        var $contactSection;
        var $gridThree;
        var $gridTwo;
        var $addressTopGrid;
        var $addressMainGrid;
        var $addressBottomGrid;
        var $contactTopGrid;
        var $contactBottomGrid;
        var $orderNotesRow;
        var $remainingBillingRows;

        if (!$checkout.length || !$billingFields.length) {
            return;
        }

        if (!$billingWrapper.length) {
            $billingWrapper = $billingFields;
        }

        if ($billingWrapper.attr('data-useup-form-designed') === '1' && $billingWrapper.find('.useup-checkout-form-section').length) {
            return;
        }

        $billingWrapper.attr('data-useup-form-designed', '1');
        $billingTitle.addClass('checkout-form-title');

        $personalSection = createSection('personal', '1.', 'Dados pessoais');
        $gridThree = createGrid('useup-grid-3');
        $gridTwo = createGrid('useup-grid-2');

        appendIfPresent($gridThree, findFieldRow($checkout, getSelectorGroup('personal', 'firstName', [ '#billing_first_name_field', '#billing_first_name', '[name="billing_first_name"]' ])));
        appendIfPresent($gridThree, findFieldRow($checkout, getSelectorGroup('personal', 'lastName', [ '#billing_last_name_field', '#billing_last_name', '[name="billing_last_name"]' ])));
        appendIfPresent($gridThree, findFieldRow($checkout, getSelectorGroup('personal', 'personType', [ '#billing_persontype_field', '#billing_person_type_field', '#billing_persontype', '#billing_person_type', '[name="billing_persontype"]', '[name="billing_person_type"]' ])));
        appendIfPresent($gridTwo, findFieldRow($checkout, getSelectorGroup('personal', 'cpf', [ '#billing_cpf_field', '#billing_cpfcnpj_field', '#billing_cpf_cnpj_field', '#billing_cpf', '#billing_cpfcnpj', '#billing_cpf_cnpj', '[name="billing_cpf"]', '[name="billing_cpfcnpj"]', '[name="billing_cpf_cnpj"]' ])));
        appendIfPresent($gridTwo, findFieldRow($checkout, getSelectorGroup('personal', 'birthdate', [ '#billing_birthdate_field', '#billing_birth_date_field', '#billing_date_of_birth_field', '#billing_birthdate', '#billing_birth_date', '#billing_date_of_birth', '[name="billing_birthdate"]', '[name="billing_birth_date"]', '[name="billing_date_of_birth"]' ])));

        appendGridIfNotEmpty($personalSection, $gridThree);
        appendGridIfNotEmpty($personalSection, $gridTwo);

        $addressSection = createSection('address', '2.', 'Endereço');
        $addressTopGrid = createGrid('useup-grid-2');
        $addressMainGrid = createGrid('useup-grid-address-number');
        $addressBottomGrid = createGrid('useup-grid-3');

        appendIfPresent($addressTopGrid, findFieldRow($checkout, getSelectorGroup('address', 'country', [ '#billing_country_field', '#billing_country', '[name="billing_country"]' ])));
        appendIfPresent($addressTopGrid, findFieldRow($checkout, getSelectorGroup('address', 'postcode', [ '#billing_postcode_field', '#billing_postcode', '[name="billing_postcode"]' ])));
        appendIfPresent($addressMainGrid, findFieldRow($checkout, getSelectorGroup('address', 'address', [ '#billing_address_1_field', '#billing_address_1', '[name="billing_address_1"]' ])));
        appendIfPresent($addressMainGrid, findFieldRow($checkout, getSelectorGroup('address', 'number', [ '#billing_number_field', '#billing_number', '[name="billing_number"]' ])));
        appendIfPresent($addressSection, findFieldRow($checkout, getSelectorGroup('address', 'complement', [ '#billing_address_2_field', '#billing_address_2', '[name="billing_address_2"]' ])));
        appendIfPresent($addressBottomGrid, findFieldRow($checkout, getSelectorGroup('address', 'neighborhood', [ '#billing_neighborhood_field', '#billing_neighborhood', '[name="billing_neighborhood"]' ])));
        appendIfPresent($addressBottomGrid, findFieldRow($checkout, getSelectorGroup('address', 'city', [ '#billing_city_field', '#billing_city', '[name="billing_city"]' ])));
        appendIfPresent($addressBottomGrid, findFieldRow($checkout, getSelectorGroup('address', 'state', [ '#billing_state_field', '#billing_state', '[name="billing_state"]' ])));

        appendGridIfNotEmpty($addressSection, $addressTopGrid);
        appendGridIfNotEmpty($addressSection, $addressMainGrid);
        appendGridIfNotEmpty($addressSection, $addressBottomGrid);

        $contactSection = createSection('contact', '3.', 'Contato');
        $contactTopGrid = createGrid('useup-grid-2');
        $contactBottomGrid = createGrid('useup-grid-1');

        appendIfPresent($contactTopGrid, findFieldRow($checkout, getSelectorGroup('contact', 'phone', [ '#billing_phone_field', '#billing_phone', '[name="billing_phone"]' ])));
        appendIfPresent($contactTopGrid, findFieldRow($checkout, getSelectorGroup('contact', 'email', [ '#billing_email_field', '#billing_email', '[name="billing_email"]' ])));

        appendGridIfNotEmpty($contactSection, $contactTopGrid);

        if ($shippingFields.length) {
            $shippingFields.addClass('useup-checkout-form-shipping').find('#ship-to-different-address').addClass('useup-field-ship-different');
            $contactSection.append($shippingFields);
        }

        $orderNotesRow = findFieldRow($checkout, getSelectorGroup('contact', 'orderNotes', [ '#order_comments_field', '#order_comments', '[name="order_comments"]' ]));
        appendIfPresent($contactBottomGrid, $orderNotesRow);

        $remainingBillingRows = $billingWrapper.children('.form-row');

        if ($remainingBillingRows.length) {
            $contactBottomGrid.append($remainingBillingRows);
        }

        appendGridIfNotEmpty($contactSection, $contactBottomGrid);

        $billingWrapper.append($personalSection, $addressSection, $contactSection);

        hideSourceIfEmpty($additionalFields);
    }

    $(document).ready(designCheckoutForm);

    $(document.body).on('updated_checkout update_checkout', function() {
        setTimeout(designCheckoutForm, 50);
    });
})(jQuery);
