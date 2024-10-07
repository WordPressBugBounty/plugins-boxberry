var firstScriptElement = document.getElementsByTagName("script")[0];
var scriptElement = document.createElement("script");
var placeScript = function () {
    firstScriptElement.parentNode.insertBefore(scriptElement, firstScriptElement);
};
scriptElement.type = "text/javascript";
scriptElement.src = (document.location.protocol == "https:" ? "https:" : "http:") + "//points.boxberry.de/js/boxberry.js";
if (window.opera == "[object Opera]") {
    document.addEventListener("DOMContentLoaded", placeScript, false);
} else {
    placeScript();
}
document.addEventListener('click', function(e) {
    if (e.target && (e.target instanceof HTMLElement) && e.target.getAttribute('data-boxberry-open') == 'true') {
        e.preventDefault();

        var selectPointLink = e.target;
        (function(selectedPointLink) {

            var city = selectPointLink.getAttribute('data-boxberry-city') || undefined;
            var token = '1$DCIlCpOeh0NkfiVjTUQNEQ8fPbjnIldR';
            var weight = '5';
            var data_id = selectPointLink.getAttribute('data-id');
            var boxberryPointSelectedHandler = function (result) {

                var selectedPointName = result.name + ' (' + result.address + ')';
                selectedPointLink.textContent = selectedPointName;
                var xhr = new XMLHttpRequest();

                xhr.open('POST', window.wp_data.ajax_url, true);
                var fd = new FormData;

                fd.append('action','boxberry_admin_update');
                fd.append('id', data_id);
                fd.append('code', result.id);
                fd.append('address', selectedPointName);
                xhr.onreadystatechange = function()
                {
                    if (xhr.readyState == 4 && xhr.status == 200)
                    {
                        location.reload();
                    }
                }
                xhr.send(fd);
            };

            boxberry.open(boxberryPointSelectedHandler, token, city, '', '', weight);
        })(selectPointLink);
    }
}, true);

jQuery(document).ready(function () {
    if (jQuery(location).attr('href').indexOf('shipping&instance_id') >= 0) {
        let getBxbId = jQuery("input:visible[id*='woocommerce_boxberry']");
        let getBxbSelect = jQuery("select:visible[id*='woocommerce_boxberry']");
        if (getBxbId.length) {
            getBxbId[3].id.indexOf('boxberry_courier') >= 0 ? jQuery(getBxbId[3]).closest('tr').hide() : '';
            getBxbSelect[4].id.indexOf('boxberry_courier') >= 0 ? jQuery(getBxbSelect[4]).closest('tr').hide() : '';
            getBxbSelect[5].id.indexOf('boxberry_self') >= 0 ? jQuery(getBxbSelect[5]).closest('tr').hide() : '';
            let setBxbId = '#' + getBxbId[1].id;
            jQuery(setBxbId).on('change', function () {
                jQuery(this).val().trim().length !== 32 ? alert('Токен указан с ошибкой') : '';
            });
        }
    }
});

