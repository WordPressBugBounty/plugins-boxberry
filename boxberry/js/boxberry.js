let boxberrySelectedPointAddress = false;
let boxberryPointCode = false;
let boxberryPointName = false;

jQuery(document).on('click', function (e) {
    if (e.target && (e.target instanceof HTMLElement) && e.target.getAttribute('data-boxberry-open') === 'true') {
        e.preventDefault();

        let selectPointLink = e.target;

        (function (selectedPointLink) {
            let city = selectPointLink.getAttribute('data-boxberry-city') || undefined;
            let method = selectPointLink.getAttribute('data-method');
            let token = selectPointLink.getAttribute('data-boxberry-token');
            let targetStart = selectedPointLink.getAttribute('data-boxberry-target-start');
            let weight = selectPointLink.getAttribute('data-boxberry-weight');

            let surch = selectedPointLink.getAttribute('data-surch');

            let paymentSum = selectPointLink.getAttribute('data-paymentsum');
            let orderSum = selectPointLink.getAttribute('data-ordersum');
            let height = selectPointLink.getAttribute('data-height');
            let width = selectPointLink.getAttribute('data-width');
            let depth = selectPointLink.getAttribute('data-depth');
            let api = selectPointLink.getAttribute('data-api-url');
            let boxberryPointSelectedHandler = function (result) {
                if (typeof result !== undefined) {
                    boxberryPointCode = result.id;
                    boxberryPointName = result.name.replace('Алма-Ата', 'Алматы');
                    boxberrySelectedPointAddress = boxberryPointName + ' (' + result.address + ')';

                    let addresSplit = result.address.split(',');
                    let insertAddres = 'ПВЗ: ' + addresSplit[2].trim() + (addresSplit[3] !== undefined ? addresSplit[3] : '');

                    if (document.getElementById('shipping_address_1')) {
                        document.getElementById('shipping_address_1').value = insertAddres;
                        if (document.getElementById('billing_address_1')) {
                            document.getElementById('billing_address_1').value = insertAddres;
                        }
                    } else {
                        if (document.getElementById('billing_address_1')) {
                            document.getElementById('billing_address_1').value = insertAddres;
                        }
                    }

                    let formData = new FormData();
                    formData.append('action', 'boxberry_update');
                    formData.append('method', method);
                    formData.append('code', boxberryPointCode);
                    formData.append('address', boxberrySelectedPointAddress);
                    bxbAjaxPost(formData).then(function (){
                        jQuery(document.body).trigger('update_checkout');
                    })
                }
            };
            boxberry.versionAPI(api);
            boxberry.checkLocation(1);
            boxberry.sucrh(surch);
            boxberry.open(boxberryPointSelectedHandler, token, city, targetStart, orderSum, weight, paymentSum, height, width, depth);
        })
        (selectPointLink)
    }
});

async function bxbAjaxPost(data) {
    await fetch(window.wp_data.ajax_url,
        {
            method: 'POST',
            body: data
        });
}

function getCityField(){
    if (jQuery('#billing_city').length && !jQuery('#ship-to-different-address-checkbox').prop('checked')){
        return jQuery('#billing_city');
    }

    if (jQuery('#shipping_city').length){
        return jQuery('#shipping_city');
    }

    return false;
}

jQuery(document).ajaxComplete(function () {
    let elements = jQuery('a[data-boxberry-open="true"]');

    if (elements.length) {
        for (let i = 0; i < elements.length; i++) {
            if (boxberrySelectedPointAddress) {
                jQuery(elements[i]).text(boxberrySelectedPointAddress);
            }
        }
    }

    if (boxberryPointName && getCityField()) {
        if (getCityField().val().trim().toLowerCase().replace('ё','е').indexOf(boxberryPointName.toLowerCase().replace('ё','е')) === -1) {
            bxbDeleteCookie('bxb_code');
            boxberryPointCode = false;
            boxberrySelectedPointAddress = false;
            boxberryPointName = false;
            jQuery(document.body).trigger('update_checkout');
        }
    }
});

jQuery(document).on('change', 'input[name="payment_method"]', function () {
    jQuery(document.body).trigger('update_checkout');
});

function bxbDeleteCookie(name) {
    let d = new Date();
    d.setDate(d.getDate() - 1);
    let expires = ";expires=" + d;
    let value = "";
    document.cookie = name + "=" + value + expires + "; path=/";
}

jQuery(document).ready(function () {
    bxbDeleteCookie('bxb_code');
    let upd = 0;
    if (location.pathname === '/checkout/' && upd === 0) {
        upd = 1;
        if (getCityField() && getCityField().val().length) {
            jQuery(document.body).trigger('update_checkout');
        }
    } else {
        upd = 0;
    }

    jQuery('#billing_postcode').on('blur',function(){
        if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
            jQuery( document.body ).trigger( 'update_checkout' );
        }
    });
    jQuery('#billing_state').on('blur',function(){
        if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
            jQuery( document.body ).trigger( 'update_checkout' );
        }
    });
    jQuery('#billing_city').on('blur',function(){
        if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
            jQuery( document.body ).trigger( 'update_checkout' );
        }
    });
    jQuery('#shipping_city').on('focusout',function(){
        jQuery( document.body ).trigger( 'update_checkout' );
    });
    jQuery('#shipping_state').on('focusout',function(){
        jQuery( document.body ).trigger( 'update_checkout' );
    });
    jQuery('#shipping_postcode').on('focusout',function(){
        jQuery( document.body ).trigger( 'update_checkout' );
    });
});
