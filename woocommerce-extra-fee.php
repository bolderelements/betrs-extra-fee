<?php
/*
Plugin Name: Flat Fee Option for BETRS
Plugin URI: http://www.bolderelements.net/flat-fee-option-betrs/
Description: Add an additional flat fee to whatever price is calculated in the table of rates
Author: Bolder Elements
Author URI: http://www.bolderelements.net/
Version: 1.0

	Copyright: © 2019 Bolder Elements (email : info@bolderelements.net)
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

add_action('plugins_loaded', 'betrs_flat_fee_option_init', 123);

function betrs_flat_fee_option_init() {

	//Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) return;

	// Check if BETRS is active
	if( ! class_exists( 'BE_Table_Rate_WC' ) ) {
		add_action( 'admin_notices', 'betrs_ff_admin_notice' );
		return;
	}

	// Ensure there are not duplicate classes
	if ( class_exists( 'BE_Shipping_Flat_Fee_WC' ) ) return;

	class BE_Shipping_Flat_Fee_WC {

		/**
		 * Constructor.
		 */
		public function __construct() {

			add_action( 'betrs_additional_rate_settings', array( $this, 'add_settings_fields' ), 23, 2 );
			add_filter( 'betrs_processed_table_rates_settings', array( $this, 'save_settings_fields' ), 23, 1 );
			add_filter( 'betrs_processed_rate', array( $this, 'add_settings_to_processed_ar' ), 23, 3 );
			add_filter( 'betrs_calculated_shipping_options', array( $this, 'apply_min_max_costs' ), 23, 2 );
		}


		/**
		 * Add the Min/Max fields to the settings table.
		 *
		 * @access public
		 * @return void
		 */
		function add_settings_fields( $rowID, $item = array() ) {
			$flat_fee = ( isset( $item['flat_fee'] ) ) ? wc_format_localized_price( $item['flat_fee'] ) : '';
?>
			<div class="clear">
				<h5><?php _e( 'Additional Fee', 'be-table-ship' ); ?></h5>
				<div class="regular-input">
<?php
			printf(
				get_woocommerce_price_format(),
	            /*$1%s*/ get_woocommerce_currency_symbol(),
	            /*$2%s*/ '<input type="text" name="flat_fee[' . $rowID . ']" id="flat_fee[' . $rowID . ']" class="wc_input_price" value="' . $flat_fee . '">'
				);
?>
				</div>
			</div>
<?php
		}


		/**
		 * Save the Min/Max fields to the database.
		 *
		 * @access public
		 * @return array
		 */
		function save_settings_fields( $table_rates_saving ) {
			if( ! is_array( $table_rates_saving ) )
				return $table_rates_saving;

			foreach( $table_rates_saving as $key => $rate ) {
				if( isset( $_POST['flat_fee'][ $key ] ) ) {
					$table_rates_saving[ $key ]['flat_fee'] = wc_format_decimal( sanitize_text_field( $_POST['flat_fee'][ $key ] ) );
				}
			}

			return $table_rates_saving;
		}


		/**
		 * Adjust an option's cost accordingly.
		 *
		 * @access public
		 * @return void
		 */
		function add_settings_to_processed_ar( $processed_rate, $option, $op_id ) {
			// apply min cost where needed
			if( isset( $option['flat_fee'] ) ) {
				$flat_fee = wc_format_decimal( $option['flat_fee'] );
				$processed_rate['flat_fee'] = $flat_fee;
			}

			return $processed_rate;
		}


		/**
		 * Adjust an option's cost accordingly.
		 *
		 * @access public
		 * @return void
		 */
		function apply_min_max_costs( $shipping_options, $original_settings ) {

			$first_row = reset( $original_settings );

			foreach( $shipping_options as $key => $value ) {

				// apply min cost where needed
				if( ! empty( $first_row[ $key ]['flat_fee'] ) ) {
					$flat_fee = (float) wc_format_decimal( $first_row[ $key ]['flat_fee'] );
					$shipping_options[ $key ]['cost'] += $flat_fee;
				}
			}

			return $shipping_options;
		}

	} // end class BE_Shipping_Flat_Fee_WC

	return new BE_Shipping_Flat_Fee_WC();

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'be_shipping_flat_fee_plugin_action_links' );
	function be_shipping_flat_fee_plugin_action_links( $links ) {
		return array_merge(
			array(
				'settings' => '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=shipping">' . __( 'Settings', 'betrs-sc' ) . '</a>',
				'support' => '<a href="http://bolderelements.net/" target="_blank">' . __( 'Bolder Elements', 'be-table-ship' ) . '</a>'
			),
			$links
		);
	}
} // end function: woocommerce_shipping_cost_limits_init


/**
 * Generate dashboard notification when not properly installed
 *
 * @access public
 * @return void
 */
function betrs_ff_admin_notice() {
?>
<div class="notice notice-error">
    <p style="font-weight: bold;"><?php _e( 'The Flat Fee for Shipping Options extension requires the Table Rate Shipping plugin by Bolder Elements', 'betrs-sc' ); ?></p>
    <p><a href="https://codecanyon.net/item/table-rate-shipping-for-woocommerce/3796656" target="_blank" class="button">
    	<?php _e( 'Purchase Table Rate Shipping', 'betrs-sc' ); ?></a></p>
</div>
<?php
}
	