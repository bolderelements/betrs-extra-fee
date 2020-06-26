<?php
/*
Plugin Name: Extra Fee for BETRS
Plugin URI: http://www.bolderelements.net/extra-fee-betrs/
Description: Add an additional flat rate or percentage fee based on the calculated value from one of your tables
Author: Bolder Elements
Author URI: http://www.bolderelements.net/
Version: 1.0

	Copyright: © 2020 Bolder Elements (email : info@bolderelements.net)
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

add_action('plugins_loaded', 'betrs_extra_fee_init', 123);

function betrs_extra_fee_init() {

	// setup the dashboard upgrader
	require_once( 'class.upgrader.php' );
	if ( is_admin() ) {
	    new BETRS_Extra_Fee_Upgrader( __FILE__, 'bolderelements', "betrs-extra-fee" );
	}

	//Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) return;

	// Check if BETRS is active
	if( ! class_exists( 'BE_Table_Rate_WC' ) ) {
		add_action( 'admin_notices', 'betrs_ef_admin_notice' );
		return;
	}

	// Ensure there are not duplicate classes
	if ( class_exists( 'BETRS_Extra_Fee_WC' ) ) return;

	class BETRS_Extra_Fee_WC {

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
			$per_fee = ( isset( $item['per_fee'] ) ) ? wc_format_localized_price( $item['per_fee'] ) : '';
?>
			<div class="clear">
				<h5><?php _e( 'Additional Flat Fee', 'betrs-ef' ); ?></h5>
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

			<div class="clear">
				<h5><?php _e( 'Percentage Fee', 'betrs-ef' ); ?>
					<?php echo wc_help_tip( __( 'Percentage is taken from final shipping cost', 'betrs-ef' ) ); ?>
				</h5>
				<div class="regular-input">
<?php
			printf(
				get_woocommerce_price_format(),
	            /*$1%s*/ '%',
	            /*$2%s*/ '<input type="text" name="per_fee[' . $rowID . ']" id="per_fee[' . $rowID . ']" class="wc_input_decimal" value="' . $per_fee . '">'
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
				if( isset( $_POST['per_fee'][ $key ] ) ) {
					$table_rates_saving[ $key ]['per_fee'] = wc_format_decimal( sanitize_text_field( $_POST['per_fee'][ $key ] ) );
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
			if( isset( $option['per_fee'] ) ) {
				$per_fee = wc_format_decimal( $option['per_fee'] );
				$processed_rate['per_fee'] = $per_fee;
			}

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

				// apply percentage fee where needed
				if( ! empty( $first_row[ $key ]['per_fee'] ) ) {
					$per_fee = (float) wc_format_decimal( $first_row[ $key ]['per_fee'] );
					$shipping_options[ $key ]['cost'] += $shipping_options[ $key ]['cost'] * ( $per_fee / 100 );
				}

				// apply flat fee where needed
				if( ! empty( $first_row[ $key ]['flat_fee'] ) ) {
					$flat_fee = (float) wc_format_decimal( $first_row[ $key ]['flat_fee'] );
					$shipping_options[ $key ]['cost'] += $flat_fee;
				}
			}

			return $shipping_options;
		}

	} // end class BETRS_Extra_Fee_WC

	return new BETRS_Extra_Fee_WC();

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'be_shipping_flat_fee_plugin_action_links' );
	function be_shipping_flat_fee_plugin_action_links( $links ) {
		return array_merge(
			array(
				'settings' => '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=shipping">' . __( 'Settings', 'betrs-ef' ) . '</a>',
				'support' => '<a href="http://bolderelements.net/" target="_blank">' . __( 'Bolder Elements', 'betrs-ef' ) . '</a>'
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
function betrs_ef_admin_notice() {
?>
<div class="notice notice-error">
    <p style="font-weight: bold;"><?php _e( 'The Extra Fee for Shipping extension requires the Table Rate Shipping plugin by Bolder Elements', 'betrs-ef' ); ?></p>
    <p><a href="https://1.envato.market/bK01g" target="_blank" class="button">
    	<?php _e( 'Purchase Table Rate Shipping', 'betrs-ef' ); ?></a></p>
</div>
<?php
}