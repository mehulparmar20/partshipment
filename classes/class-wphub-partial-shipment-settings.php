<?php
if (!defined('ABSPATH')) {
	exit;
	// Exit if accessed directly
}

class Wphub_Partial_Shipment_Settings{

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
		add_action( 'woocommerce_settings_tabs_wxp_partial_shipping_settings', __CLASS__ . '::settings_tab' );
		add_action( 'woocommerce_update_options_wxp_partial_shipping_settings', __CLASS__ . '::update_settings' );
	}

	public static function add_settings_tab( $settings_tabs ) {
		$settings_tabs['wxp_partial_shipping_settings'] = __('Partial Shipment', 'wxp-partial-shipment' );
		return $settings_tabs;
	}

	public static function settings_tab(){
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function update_settings(){
		woocommerce_update_options( self::get_settings() );
	}

	public static function get_settings(){

		$key = get_option('wxp_api_key')!='' ? get_option('wxp_api_key') : md5(wp_generate_password(12,false));
		if(get_option('wxp_api_key')==''){
			update_option('wxp_api_key',$key,'no');
		}

		$settings = array(
			'section_title' => array(
				'name'     => __('Partial Shipment Settings','wxp-partial-shipment'),
				'type'     => 'title',
				'id'       => 'wxp_partial_shipping_settings_section_title'
			),
			'partially_shipped' => array(
				'title'   => __('Add Status "Partially Shipped"','wxp-partial-shipment'),
				'desc'    => __('Add new order status called "Partially Shipped".','wxp-partial-shipment'),
				'id'      => 'partially_shipped_status',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'auto_complete' => array(
				'title'   => __('Mark auto "completed"','wxp-partial-shipment'),
				'desc'    => __('Auto Switch order status to "completed", if all products are shipped.','wxp-partial-shipment'),
				'id'      => 'partially_auto_complete',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'partially_enable_status_popup' => array(
				'title'   => __('Display status in order popup','wxp-partial-shipment'),
				'desc'    => __('Display status in order popup at order list page.','wxp-partial-shipment'),
				'id'      => 'partially_enable_status_popup',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'enable_wxp_api' => array(
				'title'   => __('Enable API','wxp-partial-shipment'),
				'desc'    => __('Enable API to retrieve and set shipment data.','wxp-partial-shipment'),
				'id'      => 'enable_wxp_api',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'wxp_api_url' => array(
				'title'   => __('API Webhook URL','wxp-partial-shipment'),
				'desc'    => __('API Webhook URL.','wxp-partial-shipment'),
				'id'      => 'wxp_api_url',
				'type'    => 'text',
				'value'   => site_url("/wp-json/wxp-shipment-data/wxp-data/?key=".$key),
				'default' => site_url("/wp-json/wxp-shipment-data/wxp-data/?key=".$key),
				'css'     => 'width:80%;',
				'custom_attributes' => array(
					'readonly'=>true,
					'onClick'=>'this.setSelectionRange(0, this.value.length)',
				),
			),
			'partially_shipped_custom' => array(
				'title'   => __('Label "Shipped"','wxp-partial-shipment'),
				'desc'    => __('Custom label for status "Shipped"','wxp-partial-shipment'),
				'id'      => 'partially_shipped_custom',
				'type'    => 'text',
				'default' => 'Shipped',
			),
			'partially_not_shipped_custom' => array(
				'title'   => __('Label "Not Shipped"','wxp-partial-shipment'),
				'desc'    => __('Custom label for status "Not Shipped"','wxp-partial-shipment'),
				'id'      => 'partially_not_shipped_custom',
				'type'    => 'text',
				'default' => 'Not Shipped',
			),
			'partially_shipped_label_custom' => array(
				'title'   => __('Label "Partially Shipped"','wxp-partial-shipment'),
				'desc'    => __('Custom label for status "Partially Shipped"','wxp-partial-shipment'),
				'id'      => 'partially_shipped_label_custom',
				'type'    => 'text',
				'default' => 'Partially Shipped',
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => 'wxp_partial_shipping_settings_section_end'
			)
		);

		return apply_filters('wxp_partial_shipping_settings',$settings);
	}

}
?>