/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
           
            {
                type: 'fondy',
                component: 'Fondy_Fondy/js/view/payment/method-renderer/fondy-method'
            }
            
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);