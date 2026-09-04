(function($) {
    'use strict';

    function calculateMortgage(price, downPercent, interestRate, years) {
        price = parseFloat(price) || 0;
        downPercent = parseFloat(downPercent) || 0;
        interestRate = parseFloat(interestRate) || 0;
        years = parseFloat(years) || 1;

        var downPayment = (price * downPercent) / 100;
        var loanAmount = price - downPayment;
        if (loanAmount < 0) loanAmount = 0;

        var monthlyInterest = (interestRate / 100) / 12;
        var totalMonths = years * 12;
        var monthlyPayment = 0;

        if (monthlyInterest > 0 && totalMonths > 0 && loanAmount > 0) {
            monthlyPayment = loanAmount * (monthlyInterest * Math.pow(1 + monthlyInterest, totalMonths)) / (Math.pow(1 + monthlyInterest, totalMonths) - 1);
        } else if (totalMonths > 0 && loanAmount > 0) {
            monthlyPayment = loanAmount / totalMonths;
        }

        return {
            price: price,
            downPercent: downPercent,
            downPayment: downPayment,
            loanAmount: loanAmount,
            interestRate: interestRate,
            years: years,
            monthlyPayment: monthlyPayment
        };
    }

    function formatInt(num) {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0
        }).format(num);
    }

    function formatDec(num) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }

    function updateSummaryUI(data) {
        window.tcaMortgageData = data;
        try {
            sessionStorage.setItem('tca_mortgage_data', JSON.stringify(data));
        } catch(e) {}

        // Update Summary Card text elements
        $('.tca-ms-monthly-val').text(formatDec(data.monthlyPayment) + ' AED');
        $('.tca-ms-loan-val').text(formatInt(data.loanAmount) + ' AED');
        $('.tca-ms-down-val').text(formatInt(data.downPayment) + ' AED (' + data.downPercent.toFixed(0) + '%)');
        $('.tca-ms-rate-val').text(data.interestRate + '%');
        $('.tca-ms-period-val').text(data.years + ' years');
    }

    function syncFormFieldsOnSubmit($form) {
        var data = window.tcaMortgageData;
        if (!data) {
            try {
                var stored = sessionStorage.getItem('tca_mortgage_data');
                if (stored) data = JSON.parse(stored);
            } catch(e) {}
        }
        if (!data) return;

        var fieldsToSync = {
            'monthly_payment': formatDec(data.monthlyPayment) + ' AED',
            'loan_amount': formatInt(data.loanAmount) + ' AED',
            'down_payment': formatInt(data.downPayment) + ' AED (' + data.downPercent.toFixed(0) + '%)',
            'interest_rate': data.interestRate + '%',
            'loan_period': data.years + ' years'
        };

        $.each(fieldsToSync, function(key, val) {
            var $existing = $form.find('input[name="' + key + '"], input[name="form_fields[' + key + ']"], input[id*="' + key + '"]');
            if ($existing.length) {
                $existing.val(val);
            } else {
                $form.append('<input type="hidden" name="form_fields[' + key + ']" id="form-field-' + key + '" value="' + val + '">');
            }
        });
    }

    function updateSliderFill($slider) {
        var min = parseFloat($slider.attr('min')) || 0;
        var max = parseFloat($slider.attr('max')) || 100;
        var val = parseFloat($slider.val()) || 0;
        var pct = ((val - min) / (max - min)) * 100;
        $slider.css('background', 'linear-gradient(to right, #0066ff 0%, #0066ff ' + pct + '%, #eaeff5 ' + pct + '%, #eaeff5 100%)');
    }

    function initCalculators() {
        $('.tca-mortgage-calc-box').each(function() {
            var $calc = $(this);
            if ($calc.hasClass('tca-calc-init')) return;
            $calc.addClass('tca-calc-init');

            var $sliderPrice = $calc.find('.tca-slider-price');
            var $sliderDown  = $calc.find('.tca-slider-down');
            var $sliderYears = $calc.find('.tca-slider-years');
            var $sliderRate  = $calc.find('.tca-slider-rate');

            var $valPrice   = $calc.find('.tca-val-price');
            var $valDown    = $calc.find('.tca-val-down');
            var $valDownPct = $calc.find('.tca-val-down-pct');
            var $valYears   = $calc.find('.tca-val-years');
            var $valRate    = $calc.find('.tca-val-rate');

            var $resLoan    = $calc.find('.tca-mc-res-loan');
            var $resRate    = $calc.find('.tca-mc-res-rate');
            var $resPeriod  = $calc.find('.tca-mc-res-period');
            var $resMonthly = $calc.find('.tca-mc-res-monthly');

            function recalculate() {
                var price   = parseFloat($sliderPrice.val()) || 0;
                var downPct = parseFloat($sliderDown.val()) || 0;
                var years   = parseFloat($sliderYears.val()) || 12;
                var rate    = parseFloat($sliderRate.val()) || 4.25;

                var data = calculateMortgage(price, downPct, rate, years);

                // Update slider fills
                updateSliderFill($sliderPrice);
                updateSliderFill($sliderDown);
                updateSliderFill($sliderYears);
                updateSliderFill($sliderRate);

                // Update Left Side Header Labels
                $valPrice.text(formatInt(data.price));
                $valDown.text(formatInt(data.downPayment));
                $valDownPct.text(data.downPercent.toFixed(0) + '%');
                $valYears.text(data.years);
                $valRate.text(data.interestRate);

                // Update Right Side Card Values
                $resLoan.text(formatInt(data.loanAmount) + ' AED');
                $resRate.text(data.interestRate + '%');
                $resPeriod.text(data.years + ' years');
                $resMonthly.text(formatDec(data.monthlyPayment) + ' AED');

                // Sync Popup UI
                updateSummaryUI(data);
            }

            $sliderPrice.on('input change', recalculate);
            $sliderDown.on('input change', recalculate);
            $sliderYears.on('input change', recalculate);
            $sliderRate.on('input change', recalculate);

            recalculate();
        });

        try {
            var stored = sessionStorage.getItem('tca_mortgage_data');
            if (stored) {
                var parsed = JSON.parse(stored);
                updateSummaryUI(parsed);
            }
        } catch(e) {}
    }

    // Dynamic Elementor Popup Open Fallback (Only if Elementor native trigger does not open it)
    $(document).on('click', '.tca-mc-apply-btn', function(e) {
        var $btn = $(this);
        if ($btn.closest('.elementor-popup-modal, .dialog-widget').length > 0) {
            return;
        }

        var popupId = $btn.data('popup-id') || '4985';

        // Check if Elementor frontend popup module exists
        if (typeof elementorFrontend !== 'undefined' && elementorFrontend.documentsManager) {
            try {
                elementorFrontend.documentsManager.showModal({ id: popupId });
                e.preventDefault();
            } catch(err) {}
        }
    });

    // Sync hidden fields ONLY on form submit so inputs are 100% untouched while typing
    $(document).on('submit', 'form.elementor-form', function() {
        syncFormFieldsOnSubmit($(this));
    });

    $(document).ready(function() {
        initCalculators();

        $(document).on('elementor/popup/show', function() {
            initCalculators();
            if (window.tcaMortgageData) {
                updateSummaryUI(window.tcaMortgageData);
            }
            // Ensure inputs inside popup are immediately editable and focusable
            setTimeout(function() {
                $('.elementor-popup-modal input, .elementor-popup-modal textarea, .elementor-popup-modal select').removeAttr('readonly disabled');
            }, 100);
        });
    });

})(jQuery);
