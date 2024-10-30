<?php
/*
Plugin Name: CouriersX - Shipping
Plugin URI: https://www.couriersx.com
Description: Integration with CouriersX
Version: 1.0.1
Author: CouriersX
Author URI: https://couriersx.com/about/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    function couriersx_register_options_page()
    {
        add_options_page('CouriersX Settings', 'CouriersX', 'manage_options', 'couriersx', 'couriersx_options_page');
    }

    add_action('admin_init', 'couriersx_register_settings');
    function couriersx_register_settings()
    {
        add_option('couriersx_shipping_label', 'CouriersX');
        add_option('couriersx_api_key', 'API Key');
        add_option('couriersx_account_code', '');
        add_option('couriersx_service_level', '');
        add_option('couriersx_product_uom', 'M');
        register_setting('couriersx_options_group', 'couriersx_shipping_label', 'couriersx_callback');
        register_setting('couriersx_options_group', 'couriersx_api_key', 'couriersx_callback');
        register_setting('couriersx_options_group', 'couriersx_account_code', 'couriersx_callback');
        register_setting('couriersx_options_group', 'couriersx_service_level', 'couriersx_callback');
        register_setting('couriersx_options_group', 'couriersx_product_uom', 'couriersx_callback');
    }

    add_action('admin_menu', 'couriersx_register_options_page');
    function couriersx_options_page()
    {
        $productUom = get_option('couriersx_product_uom');
        ?>
        <div>
            <h2>CouriersX Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('couriersx_options_group'); ?>
                <table>
                    <tr valign="top">
                        <th scope="row"><label for="couriersx_shipping_label">Shipping Label</label></th>
                        <td><input type="text" id="couriersx_shipping_label" name="couriersx_shipping_label"
                                   value="<?php echo get_option('couriersx_shipping_label'); ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="couriersx_api_key">API Key</label></th>
                        <td><input type="text" id="couriersx_api_key" name="couriersx_api_key"
                                   value="<?php echo get_option('couriersx_api_key'); ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="couriersx_account_code">Account Code</label></th>
                        <td><input type="text" id="couriersx_account_code" name="couriersx_account_code"
                                   value="<?php echo get_option('couriersx_account_code'); ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="couriersx_service_level">Service Level</label></th>
                        <td><input type="text" id="couriersx_service_level" name="couriersx_service_level"
                                   value="<?php echo get_option('couriersx_service_level'); ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="couriersx_product_uom">Product UOM</label></th>
                        <td><select name="couriersx_product_uom" id="couriersx_product_uom">
                                <option <?php if ($productUom == 'MM') echo 'selected'; ?> value="MM">MM</option>
                                <option <?php if ($productUom == 'CM') echo 'selected'; ?> value="CM">CM</option>
                                <option <?php if ($productUom == 'M') echo 'selected'; ?> value="M">M</option>
                            </select></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    function couriersx_init()
    {
        if (!class_exists('WC_CouriersX')) {
            class WC_CouriersX extends WC_Shipping_Method
            {

                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct()
                {
                    $this->id = 'couriersx'; // Id for shipping method.
                    $this->method_title = __('CouriersX');  // Title shown in admin
                    $this->method_description = __('CouriersX Rates'); // Description shown in admin

                    $this->enabled = "yes"; // This can be added as an setting but for this example its forced enabled
                    $this->title = get_option('couriersx_shipping_label'); // This can be added as an setting but for this example its forced.

                    $this->init();
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API.
                    $this->init_settings(); // This is part of the settings API.

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = array())
                {
                    if ($package['destination']['country'] == 'AU') {
                        $rate = array();
                        //Generate the sender data which will be used for the API call
                        $store_address = get_option('woocommerce_store_address');
                        $store_address_2 = get_option('woocommerce_store_address_2');
                        $store_city = get_option('woocommerce_store_city');
                        $store_postcode = get_option('woocommerce_store_postcode');
                        $store_raw_country = get_option('woocommerce_default_country');
                        $split_country = explode(":", $store_raw_country);
                        $store_country = $split_country[0];
                        $store_state = $split_country[1];

                        $data = array();
                        $data['sender'] = array(
                            "address" => $store_address,
                            "address2" => $store_address_2,
                            "city" => $store_city,
                            "postcode" => $store_postcode,
                            "country" => $store_country,
                            "state" => $store_state,
                        );

                        //Add the receiver data to the array
                        $data['receiver'] = $package['destination'];

                        //Open the rating class and send the information over so can return a cost
                        $obj = new CouriersXShippingMethods();
                        $costs = $obj->getRates($data, $package);
                        foreach ($costs as $cost) {

                            if ($cost->total > 0) {
                                $rate = [
                                    'id' => $cost->carrier_id,
//                                    'label' => $cost->carrier_name . ' - ' . $cost->description,
                                    'label' => 'CX - ' . $cost->description,
                                    'cost' => $cost->total,
                                    'calc_tax' => 'per_item'
                                ];
                                $this->add_rate($rate);
                            }
                        }
                    }
                }
            }
        }
    }


    add_action('woocommerce_shipping_init', 'couriersx_init');

    function add_shipping_method($methods)
    {
        $methods['couriersx'] = 'WC_Couriersx';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_shipping_method');
}

class CouriersXShippingMethods
{

    //Define the defaults
    function __construct()
    {
        //Below URL will be used to contact CouriersX to get the shipping rates / methods
        $this->api_base_url = 'http://couriersx.com:8000/carriers/noorder';

        //Define one set of headers for API calls
        $this->headers = array('Content-Type: application/json; charset=utf-8');
    }

    public function getRates($data, $packages)
    {
        $data = $this->postRequest($this->api_base_url, $this->generateQuery($data, $packages));
        return $data;
    }

    public function generateQuery($data, $packages)
    {
        //Get the array of products that need to be sent to the API
        $products = $this->organiseProducts($packages);
        //Generate the payload to be sent
        $payload = array(
            "sender" => [
                "city" => $data['sender']['city'],
                "post_code" => $data['sender']['postcode']
            ],
            "receiver" => [
                "city" => $data['receiver']['city'],
                "post_code" => $data['receiver']['postcode']
            ],
            'items' => $products
        );

        //Convert the array to json and return
        $payload = json_encode($payload);
        return $payload;
    }

    public function organiseProducts($packages)
    {
        $products = array();

        //Make sure there are products
        if (isset($packages['contents'])) {
            //Organise an array that will be sent to Mainfreight
            foreach ($packages['contents'] as $key => $value) {

                $thisData = $value['data'];
                $productConversion = 0;
                $productUom = get_option('couriersx_product_uom');
                if ($productUom == 'CM') {
                    $productConversion = 100;
                } elseif ($productUom == 'MM') {
                    $productConversion = 1000;
                }

                $productConversion = 1;
                //Volume is worked out by (L * W * H)
                $weight = 0;
                $length = 0;
                $width = 0;
                $height = 0;

                if (is_numeric($thisData->get_weight())) {
                    $weight = $thisData->get_weight();
                }
                if (is_numeric($thisData->get_length())) {
                    $length = ($thisData->get_length() / $productConversion);
                }

                if (is_numeric($thisData->get_width())) {
                    $width = ($thisData->get_width() / $productConversion);
                }
                if (is_numeric($thisData->get_height())) {
                    $height = ($thisData->get_height() / $productConversion);
                }

                $products[] = array(
                    "quantity" => floatval($value['quantity']),
                    'packTypeCode' => 'CTN',
                    "weight" => floatval($weight),
                    'height' => floatval($height),
                    'length' => floatval($length),
                    'width' => floatval($width),
                );
            }
        }
        return $products;
    }


    function postRequest($url, $postdata)
    {

        //Contact the CouriersX API to get the Shipping Rates / Methods
        $result = wp_remote_post( $url, array(
                'timeout' => 60,
                'redirection' => 5,
                'blocking' => true,
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body' =>
                            $postdata
                    ,
                'cookies' => array()
            )
        );

        //Return the result
        return json_decode($result['body']);
    }

}