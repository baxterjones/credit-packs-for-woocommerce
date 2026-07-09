(function($){
    var presets = {
        gold:   { accent:'#b98b35', background:'#fff9ed', border:'#e2c88f', text:'#4a3314', radius:14 },
        silver: { accent:'#6f7885', background:'#f7f8fa', border:'#cfd4dc', text:'#303740', radius:14 },
        bronze: { accent:'#9a6433', background:'#fff6ef', border:'#d9ad84', text:'#4b2f1a', radius:14 },
        minimal:{ accent:'#50575e', background:'#ffffff', border:'#dcdcde', text:'#1d2327', radius:6 }
    };

    function replaceTokens(template, data){
        return String(template || '').replace(/\{([^}]+)\}/g, function(match, key){
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : match;
        });
    }

    function field(selector){ return $(selector).first(); }

    function buildClassIcon(iconClass){
        return iconClass ? '<span class="bxtr-cp-icon ' + $('<div>').text(iconClass).html() + '" aria-hidden="true"><span class="' + $('<div>').text(iconClass).html() + '" aria-hidden="true"></span></span>' : '';
    }

    function buildSvgIcon(icon){
        var paths = {
            ticket: '<path d="M5 7h14v3a2 2 0 0 0 0 4v3H5v-3a2 2 0 0 0 0-4V7z"></path><path d="M9 9v6"></path>',
            check: '<circle cx="12" cy="12" r="8"></circle><path d="M8.5 12.5l2.2 2.2 4.8-5"></path>',
            card: '<rect x="5" y="7" width="14" height="10" rx="2"></rect><path d="M5 10h14"></path><path d="M8 14h4"></path>',
            package: '<path d="M12 3l8 4-8 4-8-4 8-4z"></path><path d="M4 7v9l8 5 8-5V7"></path><path d="M12 11v10"></path>'
        };
        return '<span class="bxtr-cp-icon bxtr-cp-icon--svg" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' + (paths[icon] || paths.ticket) + '</svg></span>';
    }

    function applyPresetToInputs(){
        var preset = field('[data-bxtr-cp-style-preset]').val();
        if (!presets[preset]) return;
        var p = presets[preset];
        field('input[data-preview-style="accent"]').val(p.accent);
        field('input[data-preview-style="background"]').val(p.background);
        field('input[data-preview-style="border"]').val(p.border);
        field('input[data-preview-style="text"]').val(p.text);
        field('input[data-preview-style="radius"]').val(p.radius);
    }

    function updatePreview(){
        var $preview = $('[data-bxtr-cp-preview]');
        if (!$preview.length) return;

        var data = {
            credit_balance: 8,
            credits_required: 1,
            credits_granted: 10,
            product_name: 'Sample Product',
            credit_label: field('input[data-preview="credit_label"]').val() || 'Credit',
            credit_label_plural: field('input[data-preview="credit_label_plural"]').val() || 'Credits',
            pack_label: field('input[data-preview="pack_label"]').val() || 'Credit Pack'
        };
        data.pack_label_plural = data.pack_label + 's';

        var titleTemplate = field('input[data-preview="redeemable_title"]').val() || 'Available with {pack_label_plural}';
        var messageTemplate = field('input[data-preview="redeemable_message"]').val() || 'Use {credits_required} {credit_label} from your balance, or checkout normally.';

        $('[data-preview-output="redeemable_title"]').text(replaceTokens(titleTemplate, data));
        $('[data-preview-output="redeemable_message"]').text(replaceTokens(messageTemplate, data));

        var $iconModeFields = $('input[name="bxtr_cp_settings[icon_mode]"]');
        if ($iconModeFields.length) {
            var iconMode = $('input[name="bxtr_cp_settings[icon_mode]"]:checked').val() || 'builtin';
            var iconHtml = '';
            if (iconMode === 'builtin') iconHtml = buildSvgIcon($('select[name="bxtr_cp_settings[icon_builtin]"]').val() || 'ticket');
            if (iconMode === 'class') iconHtml = '';
            $('[data-bxtr-cp-preview-icon]').html(iconHtml);
        }

        var accent = field('input[data-preview-style="accent"]').val();
        var bg = field('input[data-preview-style="background"]').val();
        var border = field('input[data-preview-style="border"]').val();
        var text = field('input[data-preview-style="text"]').val();
        var radius = field('input[data-preview-style="radius"]').val();
        var fontSize = field('input[data-preview-style="font_size"]').val();
        var labelFontSize = field('input[data-preview-style="label_font_size"]').val();

        if (accent) $preview[0].style.setProperty('--bxtr-cp-accent', accent); else $preview[0].style.removeProperty('--bxtr-cp-accent');
        if (bg) $preview[0].style.setProperty('--bxtr-cp-bg', bg); else $preview[0].style.removeProperty('--bxtr-cp-bg');
        if (border) $preview[0].style.setProperty('--bxtr-cp-border', border); else $preview[0].style.removeProperty('--bxtr-cp-border');
        if (text) $preview[0].style.setProperty('--bxtr-cp-text', text); else $preview[0].style.removeProperty('--bxtr-cp-text');
        if (radius !== undefined && radius !== '') $preview[0].style.setProperty('--bxtr-cp-radius', parseInt(radius, 10) + 'px');
        if (fontSize !== undefined && fontSize !== '') $preview[0].style.setProperty('--bxtr-cp-font-size', parseInt(fontSize, 10) + 'px');
        if (labelFontSize !== undefined && labelFontSize !== '') $preview[0].style.setProperty('--bxtr-cp-label-font-size', parseInt(labelFontSize, 10) + 'px');
    }


    function toggleIconSettings(){
        var iconMode = $('input[name="bxtr_cp_settings[icon_mode]"]:checked').val() || 'builtin';
        $('.bxtr-cp-icon-option').addClass('is-hidden');
        if (iconMode === 'builtin') $('.bxtr-cp-icon-option--builtin').removeClass('is-hidden');
        if (iconMode === 'class') $('.bxtr-cp-icon-option--class').removeClass('is-hidden');
    }

    function toggleProductRows(){
        $('.bxtr-cp-products-table tbody tr').each(function(){
            var $row = $(this);
            var type = $row.find('.bxtr-cp-product-type').val();
            $row.find('[data-credit-field]').removeClass('bxtr-cp-field-disabled');
            if (type === 'pack') {
                $row.find('[data-credit-field="required"]').addClass('bxtr-cp-field-disabled');
            } else if (type === 'product') {
                $row.find('[data-credit-field="granted"], [data-credit-field="expiry"]').addClass('bxtr-cp-field-disabled');
            } else {
                $row.find('[data-credit-field]').addClass('bxtr-cp-field-disabled');
            }
        });
    }

    $(document).on('change', '[data-bxtr-cp-style-preset]', function(){
        applyPresetToInputs();
        updatePreview();
    });
    $(document).on('input change', '[data-bxtr-cp-preview-form] input, [data-bxtr-cp-preview-form] select', function(){ toggleIconSettings(); updatePreview(); });
    $(document).on('change', '.bxtr-cp-product-type', toggleProductRows);

    $(function(){
        toggleProductRows();
        toggleIconSettings();
        updatePreview();
    });
})(jQuery);
