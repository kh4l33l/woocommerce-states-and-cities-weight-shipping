<?php
/**
 * Plugin Name: Woocommerce Weight Based shipping for States/Regions/Cities
 * Plugin URI: https://github.com/tunbola/woocommerce-states-and-cities-weight-shipping
 * Description: Woocommerce plugin for enabling weight based shipping for regions, states and cities.
 * Version: 1.0
 * Author: Ogunwande Tunbola
 * Author URI: https://github.com/tunbola
 * Developer: Ogunwande Tunbola
 * Developer URI: https://www.linkedin.com/in/tunbolawande/
 * Text Domain: woocommerce-extension
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('WPINC')) {
  die;
}

//Check that Woocommerce is active.
if (in_array( 'woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

  function weight_based_shipping_by_states_cities() {
    if (!class_exists( 'Weight_Based_Shipping_By_States_Cities_Method')) {

      class Weight_Based_Shipping_By_States_Cities_Method extends WC_Shipping_Method {

        /**
         * {@inheritdoc}
         */
        public function __construct($instance_id = 0) {
          $this->id = 'weight_based_for_states_and_cities';
          $this->instance_id        = absint( $instance_id );
          $this->method_title       = __( 'Weight Based shipping for States/Regions/Cities' );
          $this->method_description = __( 'Custom Weight Based shipping method for States/Regions/Cities' );
          $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
          );

          $this->init();
        }

        /**
         * Initialization.
         *
         *
         * @return void
         */
        function init() {
          $this->init_settings();

          // Load the settings API
          $this->init_form_fields();

          $form_fields = array(
            'enabled',
            'title',
            'weight',
          );

          foreach ( $form_fields as $field ) {
            $this->$field = $this->get_option( $field );
          }

          $this->weight = (array) explode( "\n", $this->weight );

          // Save settings in admin if you have any defined
          add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Define settings field for this shipping method.
         *
         * @return void
         */
        function init_form_fields() {

          $this->instance_form_fields = array(

            'enabled' => array(
              'title' => __( 'Enable' ),
              'type' => 'checkbox',
              'description' => __( 'Enable this shipping method' ),
              'default' => 'yes'
            ),

            'title' => array(
              'title' => __( 'Title' ),
              'type' => 'text',
              'description' => __( 'Title to be displayed on site' ),
              'default' => __( 'Weight Based Shipping' )
            ),

            'weight' => array(
              'title' => __('Weight (kg)' ),
              'type' => 'textarea',
              'desc_tip' => __( 'Separate cities sharing the same weight/fee with a backward slash' ),
              'description' => '<code>Weight(Kg)|Price|City(optional)</code><br />
                                <code>1|1000</code><br />
                                <code>2|1500</code><br />
                                <code>1|1000|Ikeja\Epe\Ojota</code><br />
                                <code>2|1500|Ikeja\Epe\Ojota</code><br />
                                <code>1|800|Mumbai\Kolkata\Surat</code><br />
                                <code>2|1800|Mumbai\Kolkata\Surat</code><br />
                                <div>Price for highest weight will be used if package weight is
                                greater than all the weight set here.</div>',
            ),
          );
        }

        /**
         * This function is used to calculate the shipping cost.
         * The shipping cost is calculated by first getting the package weight
         * and then using that to get the shipping fee set for that weight set in the shipping
         * method.
         * The price for the nearest weight to the package weight will be used for the
         * shipping fee. i.e max weight where the weight <= the package weight.
         *
         * @param mixed $package
         *
         * @return void
         */
        public function calculate_shipping( $package ) {
          $cart_weight = WC()->cart->get_cart_contents_weight();
          $destination_city = $package['destination']['city'];
          if ($destination_city) {
            WC()->session->set( 'destination_city', $destination_city );
          }

          $destination_city = WC()->session->get( 'destination_city');
          $cost_general = [0];

          foreach ($this->weight as $tariff){
            $tariff = explode('|', $tariff);
            $weight = isset($tariff[0]) ? $tariff[0] : '';
            $price = isset($tariff[1]) ? $tariff[1] : '';
            $city = trim(isset($tariff[2]) ? $tariff[2] : '');

            //This is to prevent unnecessary duplication in setting weight because some cities
            //might share similar shipping fees e.g 5.0|1000|Mumbai-Kolkata-Surat
            $city = $city ? explode('\\', $city) : [];
            if ($cart_weight >= $weight && in_array($destination_city, $city)) {
              $cost_for_city[] = $price;
            }

            if ($cart_weight >= $weight && !$city) {
              $cost_general[] = $price;
            }
          }

          $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => !empty($cost_for_city) ? max($cost_for_city) : max($cost_general)
          );

          $this->add_rate($rate);
        }
      }
    }
  }

  add_action('woocommerce_shipping_init', 'weight_based_shipping_by_states_cities');

  function add_weightbased_shipping_method( $methods ) {
    $methods['weight_based_for_states_and_cities'] = 'Weight_Based_Shipping_By_States_Cities_Method';

    return $methods;
  }

  add_filter( 'woocommerce_shipping_methods', 'add_weightbased_shipping_method', 0 );
}
