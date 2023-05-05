<?php
/**
*Plugin Name: Advance Partial Shipment for woocommerce
*Plugin URI: https://wpexpertshub.com
*Description: Partially ship orders in woocommerce.
*Author: WpExperts Hub
*Version: 2.0
*Author URI: https://wpexpertshub.com
*Text Domain: wxp-partial-shipment
*WC requires at least: 5.4
*WC tested up to: 7.2
*Requires at least: 5.4
*Tested up to: 6.1
*Requires PHP: 7.2 
**/

if(!defined('ABSPATH')){
    exit;
}

class Wphub_Partial_Shipment{

    protected static $_instance = null;

    public static function instance(){

        if(is_null(self::$_instance)){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    function __construct(){

        if(!defined('WPHUB_PARTIAL_SHIP')){
            define('WPHUB_PARTIAL_SHIP',__DIR__);
        }

	    add_action('init',array($this,'plugin_updates'));
        register_activation_hook(__FILE__,array($this,'partial_shipment_active'));
        add_action('init',array($this,'on_loaded'));
        spl_autoload_register(array($this,'init_autoload'));
        add_filter('plugin_action_links_'.plugin_basename(__FILE__),array($this,'wphub_partial_action_links'),10,1);
        add_action('init',array($this,'wphub_shipment_domain'),999);
	    add_action('woocommerce_order_item_meta_end',array($this,'order_item_status'),999,4);

    }

	function plugin_updates(){
		$plugin_file = basename(__DIR__).'/'.basename(__FILE__);
		$plugin_slug = strtolower(basename(__DIR__));
		$this->licence = new WpExpertshub_Licence($plugin_slug,$plugin_file);
	}

    function plugin_url(){
        return untrailingslashit(plugins_url('/', __FILE__ ));
    }

    function init_autoload($class){
        $dir = dirname(__FILE__).'/classes/';
        $class = 'class-'.str_replace('_','-',strtolower($class)).'.php';
        if(file_exists("{$dir}{$class}")){
            include("{$dir}{$class}");
        }
    }

    function on_loaded(){
        $this->partial_shipment = new WpHub_Partial_Shipment_Backend();
        $this->partial_shipment->settings();
    }

    function partial_shipment_active(){
        $sql = new WpHub_Partial_Shipment_Sql();
        $sql->create();
    }

    function wphub_partial_action_links($links){
	    $plug_link = array(
            '<a href="'.admin_url('admin.php?page=wc-settings&tab=wxp_partial_shipping_settings').'">'.__('Settings','wxp-partial-shipment').'</a>'
        );
	    $slug = strtolower(basename(__DIR__));
	    $key_status = get_option('_'.$slug.'_key_status');
	    $status_text = $key_status=='active' ? __('Deactivate License','wc-cancel-order') : __('Activate License','wc-cancel-order');
	    $plug_link[] = '<a href="'.admin_url('plugins.php?page=wpexperts-hub-license').'">'.$status_text.'</a>';
        return array_merge($links,$plug_link);
    }

    function wphub_shipment_domain(){
        if(function_exists('determine_locale')){
            $locale = determine_locale();
        } else {
            $locale = is_admin() ? get_user_locale() : get_locale();
        }

        load_textdomain('wxp-partial-shipment',WPHUB_PARTIAL_SHIP . '/lang/wxp-partial-shipment-'.$locale.'.mo');
        load_plugin_textdomain('wxp-partial-shipment',false,basename(dirname(__FILE__)).'/lang');
    }

	function order_item_status($item_id,$item,$order,$bol=false){
		$product = is_callable(array($item,'get_product')) ? $item->get_product() :  null;
		if(is_a($product,'WC_Product') && is_page() && is_a($item,'WC_Order_Item_Product')){
			$order_id = $item->get_order_id();
			$qty = $item->get_quantity();
			$qty_shipped = $this->partial_shipment->get_shipped_qty($item_id,$order_id);
			$icon = '';
			if(!$product->is_virtual() && $this->partial_shipment->is_order_shipped($order_id)){
				$shipped = $this->partial_shipment->get_shipped_qty($item_id,$order_id);
				$label_title = $this->partial_shipment->get_label_title($qty_shipped,$qty);
				$label_class = $this->partial_shipment->get_label_class($qty_shipped,$qty);
				$icon = '<span class="wphub-status-label-top"><span data-tip="'.$label_title.'" class="tips wphub-status-label '.$label_class.'">'.$label_title.' - '.$shipped.'</span></span>';
			}
			echo $icon;
		}

	}


}

function wphub_partial_shipment(){
    return Wphub_Partial_Shipment::instance();
}

if(function_exists('is_multisite') && is_multisite()){
    if(!function_exists( 'is_plugin_active_for_network')){
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    }
    if(is_plugin_active_for_network('woocommerce/woocommerce.php')){
        wphub_partial_shipment();
    }
}
elseif(in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))){
    wphub_partial_shipment();
}