<?php
/*
Plugin Name: Boxberry for WooCommerce
Description: The plugin allows you to automatically calculate the shipping cost and create Parsel for Boxberry
Version: 2.20
Author: Boxberry
Author URI: Boxberry.ru
Text Domain: boxberry
Domain Path: /lang
*/

use Boxberry\Client\LocationFinder;
use Boxberry\Client\ParselCreateResponse;
use Boxberry\Collections\ListStatusesCollection;
use Boxberry\Models\City;
use Boxberry\Models\DeliveryCalculation;
use Boxberry\Models\DeliveryCosts;
use Boxberry\Models\Zip;
use Boxberry\Models\Status;
use Boxberry\Requests\ListStatusesRequest;
use Boxberry\Requests\ParselCreateRequest;

error_reporting(~E_NOTICE && ~E_STRICT);
add_action( 'plugins_loaded', 'boxberry_load_textdomain' );
function boxberry_load_textdomain() {
    load_plugin_textdomain( 'boxberry', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' );
}
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    require __DIR__ . '/Boxberry/src/autoload.php';
    function boxberry_shipping_method_init() {
        class WC_Boxberry_Parent_Method extends WC_Shipping_Method {

            public function __construct( $instance_id = 0 ) {

                parent::__construct();
                $this->instance_id           = absint( $instance_id );
                $this->supports              = array(
                    'shipping-zones',
                    'instance-settings'
                );

                $params = array (
                    'title'          => array (
                        'title'   => __('Title', 'boxberry'),
                        'type'    => 'text',
                        'default' => $this -> method_title,
                    ),
                    'key'            => array (
                        'title'             => __('Boxberry API Key', 'boxberry'),
                        'type'              => 'text',
                        'custom_attributes' => array (
                            'required' => true
                        )
                    ),
                    'api_url'        => array (
                        'title'             => __('Boxberry API Url', 'boxberry'),
                        'description'       => '',
                        'type'              => 'text',
                        'default'           => 'https://api.boxberry.ru/json.php',
                        'custom_attributes' => array (
                            'readonly' => true,
                            'required' => true
                        )
                    ),
                    'wiidget_url'    => array (
                        'title'             => __('Boxberry Widget Url', 'boxberry'),
                        'description'       => '',
                        'type'              => 'text',
                        'default'           => '//points.boxberry.de/js/boxberry.js',
                        'custom_attributes' => array (
                            'required' => true
                        )
                    ),
                    'default_weight' => array (
                        'title'             => __('Default Weight', 'boxberry'),
                        'type'              => 'text',
                        'default'           => '500',
                        'custom_attributes' => array (
                            'required' => true
                        )
                    ),
                    'min_weight'     => array (
                        'title'             => __('Min Weight', 'boxberry'),
                        'type'              => 'text',
                        'default'           => '0',
                        'custom_attributes' => array (
                            'required' => true
                        )
                    ),
                    'max_weight'     => array (
                        'title'             => __('Max Weight', 'boxberry'),
                        'type'              => 'text',
                        'default'           => '31000',
                        'custom_attributes' => array (
                            'required' => true
                        )
                    ),
                    'height'         => array (
                        'title'   => __('Height', 'boxberry'),
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'depth'          => array (
                        'title'   => __('Depth', 'boxberry'),
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'width'          => array (
                        'title'   => __('Width', 'boxberry'),
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'parselcreate_on_status' => array (
                        'title'    => __('ps_on_status_title', 'boxberry'),
                        'desc_tip' => __('ps_on_status_desc', 'boxberry'),
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'default'  => 'none',
                        'options'  => ['none' => __('ps_on_status_none', 'boxberry')] + wc_get_order_statuses()
                    ),
                    'order_status_send' => array(
                        'title'    => __('order_status_send_title', 'boxberry'),
                        'desc_tip' => __('order_status_send_desc', 'boxberry'),
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'default'  => 'none',
                        'options' => ['none' => __('order_status_send_none', 'boxberry')] + wc_get_order_statuses()
                    ),
                    'surch'          => array (
                        'title'   => __('surch', 'boxberry'),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'options' => [
                            1 => 'Нет',
                            0 => 'Да',
                        ]
                    ),
                    'autoact'        => array (
                        'title'   => __('autoact', 'boxberry'),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'options' => [
                            0 => 'Нет',
                            1 => 'Да',
                        ]
                    ),
                    'bxbbutton' => array (
                        'title'   => __('bxbbutton', 'boxberry'),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'options' => [
                            0 => 'Нет',
                            1 => 'Да',
                        ]
                    ),
                    'order_prefix'      => array(
                        'title'    => __('order_prefix_title', 'boxberry'),
                        'desc_tip' => __('order_prefix_desc', 'boxberry'),
                        'type'     => 'text',
                        'default'  => 'wp'
                    ),
                    'check_zip'      => array(
                        'title'    => __('check_zip', 'boxberry'),
                        'desc_tip' => __('check_zip_desc', 'boxberry'),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'options' => [
                            0 => 'Нет',
                            1 => 'Да'
                        ]
                    ),
                    'enable_for_selected_payment_methods' => array(
                        'title'             => __( 'enable_for_selected_payment_methods', 'boxberry' ),
                        'type'              => 'multiselect',
                        'class'             => 'wc-enhanced-select',
                        'css'               => 'width: 400px;',
                        'default'           => '',
                        'description'       => __( 'enable_for_selected_payment_methods_desc', 'boxberry' ),
                        'options'           => $this->get_available_payment_methods(),
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'data-placeholder' => __( 'enable_for_selected_payment_methods_data_placeholder', 'boxberry' )
                        )
                    )
                );

                if (is_array($this->instance_form_fields)) {
                    $this->instance_form_fields = array_merge($this->instance_form_fields, $params);
                } else {
                    $this->instance_form_fields  = $params;
                }

                $this -> key          = $this -> get_option('key');
                $this -> title        = $this -> get_option('title');
                $this -> from         = $this -> get_option('from');
                $this -> addcost      = $this -> get_option('addcost');
                $this -> api_url      = $this -> get_option('api_url');
                $this -> widget_url   = $this -> get_option('widget_url');
                $this -> ps_on_status = $this -> get_option('parselcreate_on_status');

                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            private function is_accessing_settings() {
                if ( is_admin() ) {
                    if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
                        return false;
                    }
                    if ( ! isset( $_REQUEST['tab'] ) || 'shipping' !== $_REQUEST['tab'] ) {
                        return false;
                    }
                    if ( ! isset( $_REQUEST['instance_id'] ) ) {
                        return false;
                    }

                    return true;
                }

                return false;
            }

            private function get_available_payment_methods() {
                if ( ! $this->is_accessing_settings() ) {
                    return [];
                }

                $methods          = [];
                $wc_gateways      = new WC_Payment_Gateways();
                $payment_gateways = $wc_gateways->get_available_payment_gateways();

                foreach ( $payment_gateways as $gateway_id => $gateway ) {
                    $methods[ $gateway_id ] = $gateway->get_title();
                }

                return $methods;
            }

            private function check_payment_method_for_calc() {
                $chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
                $option                = $this->get_option( 'enable_for_selected_payment_methods' );

                if ( $chosen_payment_method === '' || $option === '' || $chosen_payment_method === $option ) {
                    return true;
                }

                if ( is_array( $option ) && in_array( $chosen_payment_method, $option, true ) ) {
                    return true;
                }

                return false;
            }

            final public function calculate_shipping( $package = array() ) {
                if ( ! $this->check_payment_method_for_calc() ) {
                    return false;
                }

                if ( ( isset( $package['destination']['city'] ) && empty( trim( $package['destination']['city'] ) ) ) || current_action() === 'woocommerce_add_to_cart' ) {
                    $this->add_rate(
                        [
                            'label'   => $this->title,
                            'cost'    => 0,
                            'taxes'   => false,
                            'package' => $package,
                        ]
                    );

                    return false;
                }

                $weight     = 0;
                $dimensions = true;

                $default_height = (int) $this->get_option( 'height' );
                $default_depth  = (int) $this->get_option( 'depth' );
                $default_width  = (int) $this->get_option( 'width' );

                $currentUnit = strtolower( get_option( 'woocommerce_weight_unit' ) );
                $weightC     = 1;
                if ( $currentUnit === 'kg' ) {
                    $weightC = 1000;
                }
                $dimensionC    = 1;
                $dimensionUnit = strtolower( get_option( 'woocommerce_dimension_unit' ) );

                switch ( $dimensionUnit ) {
                    case 'm':
                        $dimensionC = 100;
                        break;
                    case 'mm':
                        $dimensionC = 0.1;
                        break;
                }

                $countProduct   = count( $package['contents'] );
                $currentProduct = null;

                foreach ( $package['contents'] as $cartProduct ) {
                    $product = wc_get_product( $cartProduct['product_id'] );

                    $itemWeight = bxbGetWeight( $product, $cartProduct['variation_id'] );
                    $itemWeight = (float) $itemWeight * $weightC;

                    $height = (float) $product->get_height() * $dimensionC;
                    $depth  = (float) $product->get_length() * $dimensionC;
                    $width  = (float) $product->get_width() * $dimensionC;

                    if ( $countProduct === 1 && ( $cartProduct['quantity'] === 1 ) ) {
                        $currentProduct = $product;
                    }

                    $weight += ( ! empty( $itemWeight ) ? (float) $itemWeight
                            : (float) $this->get_option( 'default_weight' ) ) * $cartProduct['quantity'];

                    $sum_dimensions = $height + $depth + $width;

                    if ( $sum_dimensions > 250 ) {
                        return false;
                    }

                    if ( ( $default_height > 0 && $height > $default_height )
                        || ( $default_depth > 0 && $depth > $default_depth )
                        || ( $default_width && $width > $default_width ) ) {
                        $dimensions = false;
                    }
                }

                if ( (float) $this->get_option( 'min_weight' ) <= $weight
                    && (float) $this->get_option( 'max_weight' ) >= $weight && $dimensions ) {
                    $height = $depth = $width = 0;

                    if ( ! is_null( $currentProduct ) ) {
                        $height = (float) $currentProduct->get_height() * $dimensionC;
                        $depth  = (float) $currentProduct->get_length() * $dimensionC;
                        $width  = (float) $currentProduct->get_width() * $dimensionC;
                    }

                    $totalval = WC()->cart->get_cart_contents_total() + WC()->cart->get_total_tax();

                    $surch = $this->get_option( 'surch' ) !== '' ? (int) $this->get_option( 'surch' ) : 1;

                    $client = new Boxberry\Client\Client();
                    $client->setApiUrl( $this->api_url );
                    $client->setKey( $this->key );

                    $location = new LocationFinder();
                    $location->setClient( $client );
                    $location->find( $package['destination']['city'], $package['destination']['state'] );

                    if ( $location->getError() ) {
                        return false;
                    }

                    if ( ! isCodAvailableForCountry( $location->getCountryCode(), $this->payment_after ) ) {
                        return false;
                    }

                    $widgetSettingsRequest = $client->getWidgetSettings();

                    try {
                        $widgetSettings = $client->execute( $widgetSettingsRequest );
                    } catch ( Exception $e ) {
                        return false;
                    }

                    if ( in_array( $location->getCityCode(), $widgetSettings->getCityCode() ) ) {
                        return false;
                    }

                    $deliveryCalculation = $client->getDeliveryCalculation();
                    $deliveryCalculation->setWeight( $weight );
                    $deliveryCalculation->setHeight( $height );
                    $deliveryCalculation->setWidth( $width );
                    $deliveryCalculation->setDepth( $depth );
                    $deliveryCalculation->setBoxSizes();
                    $deliveryCalculation->setRecipientCityId( $location->getCityCode() );
                    $deliveryCalculation->setDeliveryType( $this->self_type ? DeliveryCalculation::PICKUP_DELIVERY_TYPE_ID : DeliveryCalculation::COURIER_DELIVERY_TYPE_ID );
                    $deliveryCalculation->setPaysum( $this->payment_after ? $totalval : 0 );
                    $deliveryCalculation->setOrderSum( $totalval );
                    $deliveryCalculation->setUseShopSettings( $surch );
                    $deliveryCalculation->setCmsName( 'wordpress' );
                    $deliveryCalculation->setVersion( '2.20' );
                    $deliveryCalculation->setUrl( bxbGetUrl() );

                    try {
                        $costObject = $client->execute( $deliveryCalculation );
                    } catch ( \Exception $e ) {
                        return false;
                    }

                    if ( $this->self_type && $costObject->getPriceBasePickup() ) {
                        $costReceived   = $costObject->getTotalPricePickup();
                        $deliveryPeriod = ! $widgetSettings->getHide_delivery_day() ? $costObject->getDeliveryPeriodPickup() : '';
                    } elseif ( ! $this->self_type && $costObject->getPriceBaseCourier() ) {
                        $costReceived   = $costObject->getTotalPriceCourier();
                        $deliveryPeriod = ! $widgetSettings->getHide_delivery_day() ? $costObject->getDeliveryPeriodCourier() : '';
                    } else {
                        return false;
                    }

                    if ( $deliveryPeriod ) {
                        if ( get_bloginfo( 'language' ) === 'ru-RU' ) {
                            $deliveryPeriod = ' (' . (int) $deliveryPeriod . ' ' . trim(
                                    $client->setDayForPeriod(
                                        $deliveryPeriod,
                                        'рабочий день',
                                        'рабочих дня',
                                        'рабочих дней'
                                    )
                                ) . ') ';
                        } else {
                            $deliveryPeriod = ' (' . (int) $deliveryPeriod . ' ' . trim(
                                    $client->setDayForPeriod( $deliveryPeriod, 'day', 'days', 'days' )
                                ) . ')';
                        }
                    }

                    $this->add_rate( [
                        'id'    => $this->get_rate_id(),
                        'label' => ( $this->title . $deliveryPeriod ),
                        'cost'  => ( ( (float) $this->addcost + (float) $costReceived ) ),
                    ] );
                }

                return false;
            }
        }

        class WC_Boxberry_Self_Method extends WC_Boxberry_Parent_Method {

            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_self';
                $this->method_title          = __( 'Boxberry Self', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = true;
                $this->payment_after = false;
                parent::__construct( $instance_id );
                $this->default_weight = $this->get_option( 'default_weight' );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_SelfAfter_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_self_after';
                $this->method_title          = __( 'Boxberry Self Payment After', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = true;
                $this->payment_after = true;
                parent::__construct( $instance_id );
                $this->default_weight   		  = $this->get_option( 'default_weight' );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_Courier_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_courier';
                $this->method_title          = __( 'Boxberry Courier', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = false;
                $this->payment_after = false;
                parent::__construct( $instance_id );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_CourierAfter_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_courier_after';
                $this->method_title          = __( 'Boxberry Courier Payment After', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = false;
                $this->payment_after = true;
                parent::__construct( $instance_id );
                $this->key            = $this->get_option( 'key' );
            }
        }
    }

    function bxbGetWeight($product, $id = 0)
    {
        if ($product->is_type('simple')) {
            return (float)$product->get_weight();
        }

        if ($product->is_type('variable')) {
            foreach ($product->get_visible_children() as $variationId) {
                $variation = wc_get_product($variationId);
                if ($id === $variation->get_id()) {
                    return (float)$variation->get_weight();
                }
            }
        }
    }

    function bxbGetUrl()
    {
        return str_replace(['http://', 'https://'], '', get_site_url());
    }

    add_action( 'woocommerce_shipping_init', 'boxberry_shipping_method_init' );

    function boxberry_shipping_method( $methods ) {
        $methods['boxberry_self'] = 'WC_Boxberry_Self_Method';
        $methods['boxberry_courier'] = 'WC_Boxberry_Courier_Method';
        $methods['boxberry_self_after'] = 'WC_Boxberry_SelfAfter_Method';
        $methods['boxberry_courier_after'] = 'WC_Boxberry_CourierAfter_Method';
        return $methods;
    }

    add_filter( 'woocommerce_shipping_methods', 'boxberry_shipping_method' );

    function boxberry_add_meta_tracking_code_box($post_type, $post)
    {
        if (strpos(get_post_status($post), 'wc') !== false) {
            $order = wc_get_order($post);
            $shippingData = bxbGetShippingData($order);

            if (@strpos($shippingData['method_id'], 'boxberry') === false) {
                return;
            }

            add_meta_box('boxberry_meta_tracking_code', __($shippingData['title'], 'boxberry'), 'boxberry_tracking_code', 'shop_order', 'side', 'default');
        }
    }

    add_action('woocommerce_checkout_update_order_review', 'action_woocommerce_checkout_update_order_review', 10, 1);

    function action_woocommerce_checkout_update_order_review($postedData)
    {
        $packages = WC() -> cart -> get_shipping_packages();
        foreach ($packages as $packageKey => $package) {
            $sessionKey = 'shipping_for_package_' . $packageKey;
            WC() -> session -> __unset($sessionKey);
        }
    }

    function isCodAvailableForCountry($countryCode, $paymentAfter)
    {
        if ($countryCode === '643' || $countryCode === '398'){
            return true;
        }

        if (!$paymentAfter) {
            return true;
        }

        return false;
    }

    function bxbGetLastStatusInOrder($data)
    {
        $listStatuses = $data['client']->getListStatuses();
        $listStatuses->setImId($data['track']);
        try {
            $answer = $data['client']->execute($listStatuses);
            if ($answer->valid()) {
                $offset = $answer->count() - 1;
                if ($answer->offsetGet($offset) !== null) {
                    return '<div>
                                <ul class="order_notes">
                                    <li class="note system-note">
                                        <div class="note_content">
                                            <p>' . esc_html($answer->offsetGet($offset)->getName()) . '</p>
                                        </div>
                                            <p class="meta"><abbr class="exact-date">' . esc_html($answer->offsetGet($offset)->getDate()) . '</abbr></p>
                                    </li>
                                </ul>
                            </div>';
                }
            }
        } catch (Exception $e) {
            return '<div>
                        <ul class="order_notes">
                            <li class="note">
                                <div class="note_content">
                                    <p>На данный момент статусы по заказу еще не доступны.</p>
                                </div>
                            </li>
                        </ul>
                   </div>';
        }
    }

    add_action('add_meta_boxes', 'boxberry_add_meta_tracking_code_box', 10, 2);

    function boxberry_tracking_code($post) {

        $order = wc_get_order($post);

        $shippingData = bxbGetShippingData($order);

        if (isset($shippingData['object'])) {
            $trackingNumber = get_post_meta($post->ID, 'boxberry_tracking_number', true);
            $trackingSiteLink = get_post_meta($post->ID, 'boxberry_tracking_site_link', true);
            $labelLink = get_post_meta($post->ID, 'boxberry_link', true);
            $actLink = get_post_meta($post->ID, 'boxberry_act_link', true);
            $errorText = get_post_meta($post->ID, 'boxberry_error', true);

            $pvzCode = get_post_meta($post->ID, 'boxberry_code', true);
            $boxberryAddress = get_post_meta($post->ID, 'boxberry_address', true);
            $key = $shippingData['object']->get_option('key');
            $apiUrl = $shippingData['object']->get_option('api_url');

            $client = new \Boxberry\Client\Client();
            $client->setApiUrl($apiUrl);
            $client->setKey($key);

            $orderData = [
                'track' => $trackingNumber,
                'act' => $actLink,
                'client' => $client
            ];

            if (isset($errorText) && empty($trackingNumber) && $errorText !== '') {
                echo '<p><b><u>Возникла ошибка</u></b>: ' . $errorText . '</p>';

                echo '<p><input type="submit" class="add_note button" name="boxberry_create_parsel" value="Попробовать снова"></p>';

                if ($shippingData['object'] -> self_type) {
                    echo '<p>Код пункта выдачи: <a href="#" data-id="' . esc_attr(
                            $post -> ID
                        ) . '" data-boxberry-open="true" data-boxberry-city="' . esc_attr(
                            $order -> shipping_city
                        ) . '">' . esc_attr(
                            $pvzCode
                        ) . '</a></p>';
                    echo '<p>Адрес пункта выдачи: ' . esc_html($boxberryAddress) . '</p>';
                }
            } elseif (isset($trackingNumber) && $trackingNumber !== '') {
                echo '<p><span style="display: inline-block;">Номер отправления:</span>';
                echo '<span style="margin-left: 10px"><b>' . esc_html($trackingNumber) . '</b></span>';
                if (isset($trackingSiteLink) && $trackingSiteLink !== '') {
                    echo '<p><a class="button" href="' . esc_url(
                            $trackingSiteLink
                        ) . '" target="_blank">Посмотреть на сайте Boxberry</a></p>';
                }
                echo '<p><a class="button" href="' . esc_url($labelLink) . '" target="_blank">Скачать этикетку</a></p>';
                if (isset($actLink) && $actLink !== '') {
                    echo '<p><a class="button" href="' . esc_url($actLink) . '" target="_blank">Скачать акт</a></p>';
                }
                if (empty($actLink)){
                    echo '<p><input type="submit" class="add_note button" name="boxberry_create_act" value="Сформировать акт"></p>';
                }
                echo '<p>Текущий статус заказа в Boxberry:</p>';
                echo bxbGetLastStatusInOrder($orderData);
            } else {
                if ($shippingData['object'] -> self_type) {
                    if ($pvzCode === '') {
                        echo '<p><a href="#" data-id="' . esc_attr(
                                $post -> ID
                            ) . '" data-boxberry-open="true" data-boxberry-city="' . esc_attr(
                                $order -> shipping_state
                            ) . ' ' . esc_attr($order -> shipping_city) . '">Выберите ПВЗ</a></p>';
                        return;
                    }

                    echo '<p>Код пункта выдачи: <a href="#" data-id="' . esc_attr(
                            $post -> ID
                        ) . '" data-boxberry-open="true" data-boxberry-city="' . esc_attr(
                            $order -> shipping_city
                        ) . '">' . esc_html(
                            $pvzCode
                        ) . '</a></p>';
                    echo '<p>Адрес пункта выдачи: ' . esc_html($boxberryAddress) . '</p>';
                }
                echo '<p>После нажатия кнопки заказ будет создан в системе Boxberry.</p>';
                echo '<p><input type="submit" class="add_note button" name="boxberry_create_parsel" value="Отправить заказ в систему"></p>';
            }

        }

    }
    add_action( 'woocommerce_process_shop_order_meta', 'boxberry_meta_tracking_code', 0, 2 );

    function boxberry_meta_tracking_code($postId)
    {
        if (isset($_POST['boxberry_create_parsel'])) {
            boxberry_get_tracking_code($postId);
        }

        if (isset($_POST['boxberry_create_act'])) {
            bxbCreateAct($postId);
        }
    }

    function bxbCreateAct($postId)
    {
        $order = wc_get_order($postId);
        $orderId = $order->get_id();

        $shippingData = bxbGetShippingData($order);

        if (isset($shippingData['object'])) {
            $trackingNumber = get_post_meta($orderId, 'boxberry_tracking_number', true);

            $key = $shippingData['object']->get_option('key');
            $apiUrl = $shippingData['object']->get_option('api_url');

            $parselSendRequest = wp_remote_get($apiUrl . '?token=' . $key . '&method=ParselSend&ImIds=' . $trackingNumber);
            $parselSend = json_decode(wp_remote_retrieve_body($parselSendRequest), true);

            if (isset($parselSend['label'])) {
                update_post_meta($orderId, 'boxberry_act_link', $parselSend['label']);
                update_post_meta($orderId, 'boxberry_tracking_site_link', 'https://boxberry.ru/tracking-page?id=' . $trackingNumber);
            }

            if (isset($parselSend['err'])) {
                update_post_meta($orderId, 'boxberry_error', $parselSend['err']);
            }
        }
    }

    function boxberry_get_tracking_code($postId)
    {
        $order = wc_get_order($postId);
        $orderId = $order->get_id();

        $shippingData = bxbGetShippingData($order);

        if (isset($shippingData['object'])) {
            $client = new \Boxberry\Client\Client();
            $client->setApiUrl($shippingData['object']->get_option('api_url'));
            $client->setKey($shippingData['object']->get_option('key'));
            $parselCreate = $client::getParselCreate();

            $parsel = new \Boxberry\Models\Parsel();
            $parsel->setSourcePlatform('wordpress');
            $parsel->setOrderId(($shippingData['object']->get_option('order_prefix') ?
                    $shippingData['object']->get_option('order_prefix') . '_' : '')
                . $order->get_order_number());

            $parsel->setPrice($order->get_total() - $shippingData['cost']);
            $parsel->setDeliverySum($shippingData['cost']);

            if (strpos($shippingData['method_id'], '_after') === false) {
                $parsel->setPaymentSum(0);
            } else {
                $parsel->setPaymentSum($order->get_total());
            }

            $customer_name = $order->get_formatted_shipping_full_name();
            $address = $order->get_shipping_state() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_address_2();
            $customer_phone = get_post_meta($order->get_order_number(), '_shipping_phone', true);
            $customer_email = get_post_meta($order->get_order_number(), '_shipping_email', true);

            if (trim($customer_name) === '') {
                $customer_name = $order->get_formatted_billing_full_name();
            }
            if (trim(str_replace(',', '', $address)) === '') {
                $address = $order->get_billing_state() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_address_1() . ', ' . $order->get_billing_address_2();
            }
            if (trim($customer_phone) === '') {
                $customer_phone = $order->get_billing_phone();
            }
            if (trim($customer_email) === '') {
                $customer_email = $order->get_billing_email();
            }

            $customer = new \Boxberry\Models\Customer();
            $customer->setFio($customer_name);
            $customer->setEmail($customer_email);
            $customer->setPhone($customer_phone);
            $parsel->setCustomer($customer);

            $items = new \Boxberry\Collections\Items();
            $order_items = $order->get_items();
            foreach ($order_items as $key => $orderItem) {
                $current_unit = strtolower(get_option('woocommerce_weight_unit'));
                $weight_c = 1;

                if ($current_unit === 'kg') {
                    $weight_c = 1000;
                }

                $product = wc_get_product($orderItem['product_id']);

                $itemWeight = bxbGetWeight($product, $orderItem['variation_id']);
                $itemWeight = (int)($itemWeight * $weight_c * $orderItem["qty"]);

                if ($itemWeight === 0) {
                    $itemWeight = $shippingData['object']->get_option('default_weight') * $orderItem["qty"];
                }

                $item = new \Boxberry\Models\Item();
                $id = (string)((isset($product->sku) && !empty($product->sku)) ? $product->sku : $orderItem['product_id']);

                $item->setId($id);

                $item->setName($orderItem['name']);
                // $item->setPrice($product->get_price());
                $item->setPrice((float)$orderItem['total'] / $orderItem['qty']);
                $item->setQuantity($orderItem['qty']);
                $item->setWeight($itemWeight);
                $items[] = $item;
                unset($product);
            }


            $parsel->setItems($items);
            $shop = array(
                'name' => '',
                'name1' => ''
            );

            if (strpos($shippingData['method_id'], 'boxberry_self') !== false) {
                $parsel->setVid(1);
                $boxberry_code = get_post_meta($orderId, 'boxberry_code', true);

                if ($boxberry_code === '') {
                    update_post_meta($orderId, 'boxberry_error', 'Для доставки до пункта ПВЗ нужно указать его код');
                    return;
                }

                $shop['name'] = $boxberry_code;
                $shop['name1'] = $shippingData['object']->get_option('from');
            } else {
                $postCode = $order->get_shipping_postcode();
                if (is_null($postCode) || trim((string)$postCode) === '') {
                    $postCode = $order->get_billing_postcode();
                }
                $shippingCity = $order->get_shipping_city();
                if (is_null($shippingCity) || trim((string)$shippingCity) === '') {
                    $shippingCity = $order->get_billing_city();
                }
                $shippingState = $order->get_shipping_state();
                if (is_null($shippingState) || trim((string)$shippingState) === '') {
                    $shippingState = $order->get_billing_state();
                }
                $shippingAddress = $order->get_shipping_address_1() . ', ' . $order->get_shipping_address_2();
                if (trim(str_replace(',', '', $shippingAddress)) === '') {
                    $shippingAddress = $order->get_billing_address_1() . ', ' . $order->get_billing_address_2();
                }

                $location = new LocationFinder();
                $location->setClient( $client );
                $location->find( $shippingCity, $shippingState );
                if ( $location->getError() ) {
                    update_post_meta( $orderId, 'boxberry_error', $location->getError() );

                    return;
                }

                if ( $location->getCountryCode() !== '643' ) {
                    try {
                        $dadataSuggestions = $client->getDadataSuggestions();
                        $dadataSuggestions->setQuery( $shippingCity . ' ' . $shippingAddress );
                        $dadataSuggestions->setLocations();
                        $dadataSuggestions->fixCityName();
                        $dadataRequestResult = $client->execute( $dadataSuggestions );
                    } catch ( Exception $e ) {
                        $error = 'Не удалось определить город, попробуйте отредактировать адрес и выгрузить заказ повторно, либо создать заказ вручную в ЛК.';
                        update_post_meta( $orderId, 'boxberry_error', $error );

                        return;
                    }

                    try {
                        $export = new \Boxberry\Models\CourierDeliveryExport();
                        $export->setIndex( $export::EAEU_COURIER_DEFAULT_INDEX );
                        $export->setCountryCode( $location->getCountryCode() );
                        $export->setCityCode( $location->getCityCode() );
                        $export->setArea( $dadataRequestResult->getArea() );
                        $export->setStreet( $dadataRequestResult->getStreet() );
                        $export->setHouse( $dadataRequestResult->getHouse() );
                        $export->setFlat( $dadataRequestResult->getFlat() );
                        $export->setTransporterGuid( $export::TRANSPORTER_GUID );
                        $parsel->setExport( $export );
                    } catch ( Exception $e ) {
                        $error = $e->getMessage();
                        update_post_meta( $orderId, 'boxberry_error', $error );

                        return;
                    }

                    $client->disableDebugMode();
                }

                $parsel->setVid(2);
                $courierDost = new \Boxberry\Models\CourierDelivery();
                $courierDost->setIndex($postCode);
                $courierDost->setCity($shippingCity);
                $courierDost->setAddressp($shippingAddress);
                $parsel->setCourierDelivery($courierDost);
            }
            $parsel->setShop($shop);
            $parselCreate->setParsel($parsel);
            $autoact = (int)$shippingData['object']->get_option('autoact');
            $autoStatus = $shippingData['object']->get_option('order_status_send');
            try {
                /** @var ParselCreateResponse $answer */
                $answer = $client->execute($parselCreate);
                if ($answer->getTrack() !== '') {
                    update_post_meta($orderId, 'boxberry_tracking_number', $answer->getTrack());
                    update_post_meta($orderId, 'boxberry_link', $answer->getLabel());
                    if ($autoact === 1) {
                        bxbCreateAct($postId);
                    }
                    if ($autoStatus && wc_is_order_status($autoStatus) && $order = wc_get_order($orderId)) {
                        $order->update_status($autoStatus, sprintf(__('Успешная регистрация в Boxberry: %s ', 'boxberry'), $answer->getTrack()));
                        do_action('woocommerce_boxberry_tracking_code', 'send', $order, $answer->getTrack());
                    }
                }
            } catch (Exception $e) {
                if ($e->getMessage() === 'Ваша учетная запись заблокирована') {
                    update_post_meta($orderId, 'boxberry_error', 'В профиле доставки <b>"' . $shippingData['object']->get_option('title') . '"</b> указан не верный API-token, либо данный профиль доставки удален. Проверить ваш API-token вы можете <a href="https://account.boxberry.ru/client/infoblock/index?tab=api&api=methods" target="_blank">здесь</a>. Если API-token указан корректно и ошибка повторяется обратитесь в <a href="https://sd.boxberry.ru" target="_blank">техподдержку</a>');
                } else {
                    update_post_meta($orderId, 'boxberry_error', $e->getMessage());
                }
            }
        }
    }

    function bxbGetShippingData($order)
    {
        foreach ($order->get_items('shipping') as $item) {
            $methodId = $item->get_method_id();
            $exactInstanceId = $item->get_instance_id();
            $total = $item->get_total();
        }

        if ($exactShippingObject = WC_Shipping_Zones::get_shipping_method($exactInstanceId)) {
            return [
                'method_id' => $methodId,
                'object' => $exactShippingObject,
                'cost' => $total,
                'title' => $exactShippingObject->get_option('title')
            ];
        }

        global $wpdb;
        $raw_methods_sql = "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s";
        $result = $wpdb->get_results($wpdb->prepare($raw_methods_sql, $methodId));
        $instanceId = $result[0]->instance_id;

        if ($shippingObject = WC_Shipping_Zones::get_shipping_method($instanceId)) {
            return [
                'method_id' => $methodId,
                'object' => $shippingObject,
                'cost' => $total,
                'title' => $shippingObject->get_option('title')
            ];
        }

        return [];

    }

    function boxberry_woocommerce_after_shipping_rate( $method ) {
        if ( is_checkout() ) {

            if ( strpos( $method->get_method_id(), 'boxberry_self' ) !== false ) {
                $shipping = WC_Shipping_Zones::get_shipping_method( $method->get_instance_id() );
            }

            if ( isset( $shipping ) ) {
                $key     = $shipping->get_option( 'key' );
                $api_url = $shipping->get_option( 'api_url' );

                $client = new \Boxberry\Client\Client();
                $client->setApiUrl( $api_url );
                $client->setKey( $key );
                $widgetKeyMethod = $client::getKeyIntegration();
                $widgetKeyMethod->setToken( $key );

                try {
                    $widgetResponse = $client->execute( $widgetKeyMethod );
                    if ( empty( $widgetResponse ) ) {
                        return false;
                    }
                } catch ( Exception $ex ) {
                    return false;
                }

                $widget_key    = $widgetResponse->getWidgetKey();
                $billing_city  = WC()->customer->get_billing_city();
                $shipping_city = WC()->customer->get_shipping_city();

                if ( ! empty( $shipping_city ) ) {
                    $city = $shipping_city;
                } elseif ( ! empty( $billing_city ) ) {
                    $city = $billing_city;
                }

                $city = str_replace( [ 'Ё', 'Г ', 'АЛМАТЫ' ], [ 'Е', '', 'АЛМА-АТА' ], mb_strtoupper( $city ) );

                $link_title = 'Выберите пункт выдачи';

                $state = WC()->customer->get_shipping_state();

                $weight       = 0;
                $current_unit = strtolower( get_option( 'woocommerce_weight_unit' ) );
                $weight_c     = 1;

                if ( $current_unit === 'kg' ) {
                    $weight_c = 1000;
                }

                $dimension_c    = 1;
                $dimension_unit = strtolower( get_option( 'woocommerce_dimension_unit' ) );

                switch ( $dimension_unit ) {
                    case 'm':
                        $dimension_c = 100;
                        break;
                    case 'mm':
                        $dimension_c = 0.1;
                        break;
                }

                $cartProducts = WC()->cart->get_cart();
                $countProduct = count( $cartProducts );

                $height = 0;
                $depth  = 0;
                $width  = 0;

                foreach ( $cartProducts as $cartProduct ) {
                    $product = wc_get_product( $cartProduct['product_id'] );

                    $itemWeight = bxbGetWeight( $product, $cartProduct['variation_id'] );
                    $itemWeight = (float) $itemWeight * $weight_c;

                    if ( $countProduct == 1 && ( $cartProduct['quantity'] == 1 ) ) {
                        $height = (float) $product->get_height() * $dimension_c;
                        $depth  = (float) $product->get_length() * $dimension_c;
                        $width  = (float) $product->get_width() * $dimension_c;
                    }

                    $weight += ( ! empty( $itemWeight ) ? $itemWeight : (float) $shipping->get_option( 'default_weight' ) ) * $cartProduct['quantity'];
                }

                $totalval = WC()->cart->get_cart_contents_total() + WC()->cart->get_total_tax();

                $surch = $shipping->get_option( 'surch' ) !== '' ? (int) $shipping->get_option( 'surch' ) : 1;

                if ( $method->get_method_id() === 'boxberry_self_after' ) {
                    $payment = $totalval;
                } else {
                    $payment = 0;
                }

                $pvzimg                 = '<img src=\'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAYCAYAAAD6S912AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAE+SURBVHgBnVSBccMgDNR5Am9QRsgIjMIGZYN4g2QDpxN0BEagGzgbpBtQqSdiBQuC/Xc6G0m8XogDQEFKaUTzaAHtkVZEtBnNQi8w+bMgof+FTYKIzTuyS7HBKsqdIKfvqUZ2fpv0mj+JDkwZdILMQCcEaSwDuQULO8GDI7hS3VzZYFmJ09RzfFWJP981deJcU+tIhMoPWtDdSo3KJYKSe81tD7imid63zYIFHZr/h79mgDp+K/47NDBwgkG5YxG7VTZ/KT7zLIZEt8ZQjDhwusBeIZNDOcnDD3AAXPT/BkjnUlPZQTjnCUunO6KSWtyoE8HAQb+DcNmoU6ptXw+dLD91cyvJc1JUrpHM63+dROuXStyk9UW30NHKKM7mrJDl2AS9KFR4USiy7wp7kV5fm4coEOEomDQI0qk1LMIfknqE+j7lxtgAAAAASUVORK5CYII=\'>';
                $bxbbutton              = $shipping->get_option( 'bxbbutton' ) ? 'class="bxbbutton"' : '';
                $link_with_img          = $shipping->get_option( 'bxbbutton' ) ? $pvzimg : '';
                $nbsp                   = $shipping->get_option( 'bxbbutton' ) ? '&nbsp;' : '';
                $display                = $shipping->get_option( 'bxbbutton' ) ? '' : 'color:inherit;';
                $package                = WC()->shipping()->get_packages();
                $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );

                if ( isset( $package[0]['destination']['city'], $chosen_shipping_method[0] ) && $package[0]['destination']['city'] !== '' && $chosen_shipping_method[0] === $method->get_id() ) {
                    echo '                
                <p style="margin: 4px 0 8px 15px;"><a ' . $bxbbutton . ' id="' . esc_attr( $method->get_id() ) . '" href="#"
				   style="' . esc_attr( $display ) . '"
                   data-surch =" ' . esc_attr( $surch ) . '"
                   data-boxberry-open="true"
                   data-method="' . esc_attr( $method->get_method_id() ) . '"
                   data-boxberry-token="' . esc_attr( $widget_key ) . '"
                   data-boxberry-city="' . esc_attr( $state . ' ' . $city ) . '"
                   data-boxberry-weight="' . esc_attr( $weight ) . '"
                   data-paymentsum="' . esc_attr( $payment ) . '"
                   data-ordersum="' . esc_attr( $totalval ) . '"
                   data-height="' . esc_attr( $height ) . '"
                   data-width="' . esc_attr( $width ) . '"
                   data-depth="' . esc_attr( $depth ) . '"
                   data-api-url="' . esc_attr( $api_url ) . '"
                   
                >' . $link_with_img . $nbsp . $link_title . '</a></p>';
                }
            }
        }
    }

    add_action( 'woocommerce_after_shipping_rate', 'boxberry_woocommerce_after_shipping_rate' );

    function boxberry_script_handle()
    {
        return;
    }

    function my_admin_enqueue($hook)
    {
        $widget_url = get_option('wiidget_url');
        if (strpos($widget_url,'http://') !== false){
            $protocol = 'http://';
            $widget_url = str_replace('http://','',$widget_url);
            wp_register_script( 'boxberry_points', 'http://'.$widget_url);
        }else if (strpos($widget_url,'https://') !== false){
            $protocol = 'https://';
            $widget_url = str_replace('https://','',$widget_url);
            wp_register_script( 'boxberry_points', 'https://'.$widget_url);
        }else {
            wp_register_script( 'boxberry_points', 'https://points.boxberry.de/js/boxberry.js');
        }
        wp_enqueue_script( 'boxberry_points' );

        wp_enqueue_script( 'boxberry_script_handle', plugin_dir_url( __FILE__ ) . ( 'js/boxberry_admin.js' ), array("jquery"), '2.17' );
    }

    function my_enqueue($hook)
    {
        if (is_cart() || is_checkout()) {
            $widget_url = get_option('wiidget_url');
            if (strpos($widget_url, 'http://') !== false) {
                $protocol = 'http://';
                $widget_url = str_replace('http://', '', $widget_url);
                wp_register_script('boxberry_points', 'http://' . $widget_url);
            } else {
                if (strpos($widget_url, 'https://') !== false) {
                    $protocol = 'https://';
                    $widget_url = str_replace('https://', '', $widget_url);
                    wp_register_script('boxberry_points', 'https://' . $widget_url);
                } else {
                    wp_register_script('boxberry_points', 'https://points.boxberry.de/js/boxberry.js');
                }
            }
            wp_enqueue_script('boxberry_points');

            wp_enqueue_script('boxberry_script_handle', plugin_dir_url(__FILE__) . ('js/boxberry.js'), array("jquery"), '2.20');

            wp_register_style('boxberry_button', plugin_dir_url(__FILE__) . ('css/bxbbutton.css'));

            wp_enqueue_style('boxberry_button');
        }
    }
    add_action( 'wp_enqueue_scripts', 'my_enqueue' );
    add_action( 'admin_enqueue_scripts', 'my_admin_enqueue' );

    function boxberry_put_choice_code($order_id)
    {
        $shipping_method      = array_shift($_POST[ 'shipping_method' ]);
        $shipping_method_name = preg_replace('/\d+/', '', $shipping_method);
        $array                = get_user_meta(get_current_user_id(), '_boxberry_array', true);

        if (isset($_COOKIE['bxb_code'], $_COOKIE['bxb_address'])) {
            update_post_meta($order_id, 'boxberry_code', sanitize_text_field($_COOKIE[ 'bxb_code' ]));
            update_post_meta($order_id, 'boxberry_address', sanitize_text_field($_COOKIE[ 'bxb_address' ]));
        }
        update_user_meta(get_current_user_id(), '_boxberry_array', array ());
    }

    add_action('woocommerce_new_order', 'boxberry_put_choice_code');
    add_action('wp_ajax_boxberry_update', 'boxberry_update_callback');
    add_action('wp_ajax_nopriv_boxberry_update', 'boxberry_update_callback');
    add_action('wp_ajax_boxberry_admin_update', 'boxberry_admin_update_callback');

    function boxberry_update_callback()
    {
        setcookie("bxb_code", sanitize_text_field($_POST[ 'code' ]), 0, '/');
        setcookie("bxb_address", sanitize_text_field($_POST[ 'address' ]), 0, '/');
    }

    function boxberry_admin_update_callback()
    {
        update_post_meta(sanitize_key($_POST[ 'id' ]), 'boxberry_code', sanitize_text_field($_POST[ 'code' ]));
        update_post_meta(sanitize_key($_POST[ 'id' ]), 'boxberry_address', sanitize_text_field($_POST[ 'address' ]));
    }

    function js_variables()
    {
        $variables = array (
            'ajax_url' => admin_url('admin-ajax.php')
        );
        echo '<script type="text/javascript">';
        echo 'window.wp_data = ';
        echo json_encode($variables);
        echo ';</script>';
    }
    add_action('wp_head', 'js_variables');

    function admin_js_variables()
    {
        $variables = array (
            'ajax_url' => admin_url('admin-ajax.php')
        );
        echo '<script type="text/javascript">';
        echo 'window.wp_data = ';
        echo json_encode($variables);
        echo ';</script>';
    }
    add_action('admin_head', 'js_variables');

    add_action('woocommerce_order_status_changed', 'boxberry_register_on_status', 10 , 3);

    function boxberry_register_on_status($orderId, $previous_status, $next_status)
    {
        $order = wc_get_order($orderId);
        $shippingData = bxbGetShippingData($order);
        if (isset($shippingData['method_id'], $shippingData['object']) && strpos($shippingData['method_id'], 'boxberry') !== false) {
            $parselCreateStatus = $shippingData['object']->get_option('parselcreate_on_status');
            if ($next_status === substr($parselCreateStatus, 3) && !get_post_meta(
                    $orderId,
                    'boxberry_tracking_number',
                    true
                )) {
                boxberry_get_tracking_code($orderId);
            }
        }
    }

    add_action('woocommerce_after_checkout_validation', 'boxberry_validate_checkout', 10, 2);

    function boxberry_validate_checkout($data, $errors)
    {
        if (!empty($errors->get_error_message('shipping'))) {
            return;
        }

        $shippingMethod = array_map(static function ($i) {
            $i = explode(':', $i);

            return $i[0];
        }, (array)$data['shipping_method']);

        $chosenDeliveryPoint = isset($_POST['boxberry_code']) ? $_POST['boxberry_code'] : $_COOKIE['bxb_code'];

        if (((!$data['ship_to_different_address'] && !$data['billing_city']) || ($data['ship_to_different_address'] && !$data['shipping_city']))
            && (strpos($shippingMethod[0], 'boxberry') !== false)) {
            $errors->add('shipping', '<strong>Необходимо указать город для расчета доставки Boxberry</strong>');
        } elseif (empty($chosenDeliveryPoint) && strpos($shippingMethod[0], 'boxberry_self') !== false) {
            $errors->add('shipping', '<strong>Необходимо выбрать пункт выдачи Boxberry</strong>');
        }
    }

}