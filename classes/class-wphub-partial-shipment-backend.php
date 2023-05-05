<?php
defined('ABSPATH') || exit;

if(!class_exists('WpHub_Partial_Shipment_Backend')){
    class WpHub_Partial_Shipment_Backend{

        protected $wc_partial_shipment_settings = array();
        protected $wc_partial_labels = array();
        protected $wxp_api = 'no';
        protected $wxp_api_key = '';

        function __construct(){
            add_action('admin_enqueue_scripts',array($this,'pf_admin_scripts'),10);
            add_filter('woocommerce_screen_ids',array($this,'wphub_shipment_add_screen'),999,1);
            add_action('admin_enqueue_scripts',array($this,'admin_head'),999);
            add_action('wp_enqueue_scripts',array($this,'wxp_front_script'));
            add_filter('woocommerce_admin_order_actions',array($this,'partial_shipment_actions'),100,2);
            add_action('admin_menu',array($this,'partial_shipment_page'));
            add_action('admin_head',array($this,'partial_shipment_page_highlight'));
            add_action('wp_loaded',array($this,'add_shipment'),10);
            add_action('admin_notices',array($this,'display_pf_admin_notice'),999);
            add_filter('wc_order_statuses',array($this,'add_partial_complete_status'));
            add_action('init',array($this,'wxp_partial_complete_register_status'),999);
            add_action('woocommerce_order_item_add_action_buttons',array($this,'wxp_order_shipment_button'),10,1);

            add_filter('woocommerce_admin_order_preview_line_item_columns',array($this,'wxp_order_status_in_popup'),10,2);
            add_filter('woocommerce_admin_order_preview_line_item_column_wxp_status',array($this,'wxp_order_status_in_popup_value'),10,4);
            add_action('woocommerce_admin_order_item_headers',array($this,'wxp_order_item_headers'),10,1);
            add_action('woocommerce_admin_order_item_values',array($this,'wxp_order_item_values'),10,3);
            //add_action('woocommerce_order_item_meta_end',array($this,'wxp_order_item_icons'),999,4);

            add_action('woocommerce_order_details_after_order_table',array($this,'wxp_order_details'),9,1);
            add_action('wphub_partial_shipment_status',array($this,'wxp_partial_shipment_order_status'),9,1);
            add_action('rest_api_init',array($this,'wxp_data_route'));
            add_filter('woocommerce_email_classes',array($this,'wphub_partial_email_classes'),999,1);
            add_action('wphub_partial_shipment_new_email',array($this,'wphub_partial_email_new'),999,2);
            add_action('wphub_partial_shipment_update_email',array($this,'wphub_partial_email_update'),999,2);
            add_action('wphub_partially_shipped_order_details',array($this,'order_details'),10,5);
        }

        function wphub_shipment_add_screen($screen_ids){
            $screen_ids[] = 'admin_page_partial-shipment';
            return $screen_ids;
        }

        function pf_admin_scripts(){
            $Admin_Assets = new WC_Admin_Assets();
            $Admin_Assets->admin_scripts();
            $Admin_Assets->admin_styles();
        }

        function wxp_front_script(){
            wp_enqueue_style('wxp_front_style',wphub_partial_shipment()->plugin_url().'/assets/css/front.css');
        }

        function admin_head(){
	        $screen = get_current_screen();
			if(isset($screen->id) && ($screen->id=='admin_page_partial-shipment' || $screen->id=='edit-shop_order')){
				wp_enqueue_style('pf_admin-style',wphub_partial_shipment()->plugin_url().'/assets/css/admin.css',array(),null);
				wp_enqueue_script('pf_admin-script',wphub_partial_shipment()->plugin_url().'/assets/js/admin.js',array('jquery'),null,true);
			}
        }

        function partial_shipment_actions($actions,$order){
            $actions['partial-shipment'] = array(
                'url'       => wp_nonce_url(admin_url('admin.php?page=partial-shipment&id='.$order->get_id()),'partial_shipment'),
                'name'      => __('Shipment','wxp-partial-shipment'),
                'action'    => "partial-shipment",
            );
            return $actions;
        }

        function partial_shipment_page(){
            add_submenu_page(
                false,
                __('Order Details','wxp-partial-shipment'),
                __('Order Details','wxp-partial-shipment'),
                'edit_others_shop_orders',
                'partial-shipment',
                array($this,'partial_shipment_dashboard')
            );
        }

        function partial_shipment_page_highlight(){
            global $self, $parent_file, $submenu_file, $plugin_page, $typenow;
            if(isset($_REQUEST['page']) && $_REQUEST['page']=='partial-shipment'){
                $parent_file = 'woocommerce';
                $submenu_file = 'edit.php?post_type=shop_order';
                $plugin_page = '';
            }
        }

        function partial_shipment_dashboard(){
            $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
            if(!wp_verify_nonce($nonce,'partial_shipment')){
                die(__( 'Security check','wxp-partial-shipment'));
            }
            else
            {
                require_once(WPHUB_PARTIAL_SHIP.'/includes/dashboard.php');
            }
        }

        function check_posted_qty($posted){
            $qty = 0;
            if(is_array($posted) && !empty($posted)){
                foreach($posted as $post){
                    $qty = $qty+$post['qty'];
                    if($qty){
                        break;
                    }
                }
            }
            return $qty;
        }

        function add_shipment(){
            if(isset($_POST['shipment']['check']) && wp_verify_nonce($_POST['shipment']['check'],'add-pf-shipment')){
                global $wpdb;
                if(isset($_POST['shipment']['new'])){
                    $data = array(
                        'order_id' => isset($_POST['shipment']['order']) ? sanitize_text_field($_POST['shipment']['order']) : 0,
                        'shipment_id' => isset($_POST['shipment']['order']) ? $this->get_shipment_id(sanitize_text_field($_POST['shipment']['order'])) : 0,
                        'shipment_url'=>isset($_POST['shipment']['url']) ? sanitize_url($_POST['shipment']['url']) : '',
                        'shipment_num'=>isset($_POST['shipment']['num']) ? sanitize_text_field($_POST['shipment']['num']) : '',
                        'shipment_date'=>current_time('timestamp',0),
                    );

                    $posted_qty = $this->check_posted_qty($_POST['item']);
                    if(!$posted_qty){
                        $this->add_pf_notice(__('No item available in shipment.','wxp-partial-shipment'),"error");
                        wp_redirect(admin_url('admin.php?page=partial-shipment&id='.$_REQUEST['id'].'&_wpnonce='.$_REQUEST['_wpnonce']));
                        exit;
                    }

                    $wpdb->insert($wpdb->prefix."partial_shipment",$data,array('%d','%d','%s','%s','%s'));
                    $shipment_id = $wpdb->insert_id;
                    if($shipment_id){
                        $values = array();
                        if(isset($_POST['item']) && is_array($_POST['item']) && !empty($_POST['item'])){
                            foreach($_POST['item'] as $item_id=>$item){
                                if($item['qty']>0){
                                    $values[$item_id] = $wpdb->prepare("(%d,%d,%d)",$shipment_id,$item_id,$item['qty']);
                                }
                            }
                        }
                        if(!empty($values)){
                            $wpdb->query("INSERT INTO ".$wpdb->prefix."partial_shipment_items (shipment_id,item_id,item_qty) VALUES ".implode(',',$values)."");
                            $last_id = $wpdb->insert_id;
                            if($last_id){
                                do_action('wphub_partial_shipment_status',$_REQUEST['id']);
                                do_action('wphub_partial_shipment_new_email',$_REQUEST['id'],$shipment_id);
                                $this->add_pf_notice(__('Shipment #'.$data['shipment_id'].' added successfully.','wxp-partial-shipment'),"success");
                            }
                        }
                    }
                }
                elseif(isset($_POST['shipment']['update']) && $_POST['shipment']['update']){
                    $posted_qty = $this->check_posted_qty($_POST['item']);
                    if(!$posted_qty){
                        $this->add_pf_notice(__('No item available in shipment.','wxp-partial-shipment'),"error");
                        wp_redirect(admin_url('admin.php?page=partial-shipment&id='.$_REQUEST['id'].'&action=edit-shipment&shipment-id='.$_POST['shipment']['update'].'&_wpnonce='.$_REQUEST['_wpnonce']));
                        exit;
                    }
                    $shipment_edit = wphub_partial_shipment()->partial_shipment->get_shipment($_POST['shipment']['update'],$_POST['shipment']['order']);
                    if(isset($shipment_edit->id) && $shipment_edit->id==$_POST['shipment']['update']){

                        $update_main = $wpdb->update($wpdb->prefix."partial_shipment",
                                        array(
                                            'shipment_url'=>isset($_POST['shipment']['url']) ? $_POST['shipment']['url'] : '',
                                            'shipment_num'=>isset($_POST['shipment']['num']) ? $_POST['shipment']['num'] : '',
                                        ),
                                        array('id'=>$shipment_edit->id),
                                        array('%s','%s'),
                                        array('%d')
                                        );

                        $values = array();
                        if(isset($_POST['item']) && is_array($_POST['item']) && !empty($_POST['item'])){
                            foreach($_POST['item'] as $item_id=>$item){
                                if($item['qty']>0){
                                    $values[$item_id] = $wpdb->prepare("(%d,%d,%d,%d)",$item['row-id'],$shipment_edit->id,$item_id,$item['qty']);
                                }
                            }
                        }
                        if(!empty($values)){
                            $update = $wpdb->query("INSERT INTO ".$wpdb->prefix."partial_shipment_items (id,shipment_id,item_id,item_qty) VALUES ".implode(',',$values)." ON DUPLICATE KEY UPDATE item_qty=VALUES(item_qty)");
                            do_action('wphub_partial_shipment_status',$_REQUEST['id']);
                            do_action('wphub_partial_shipment_update_email',$_REQUEST['id'],$shipment_edit->id);
                            if($update || $update_main){
                                $this->add_pf_notice(__('Shipment #'.$shipment_edit->shipment_id.' updated successfully.','wxp-partial-shipment'),"success");
                            }
                            wp_redirect(admin_url('admin.php?page=partial-shipment&id='.$_REQUEST['id'].'&_wpnonce='.$_REQUEST['_wpnonce']));
                            exit;
                        }
                    }
                }
            }
            elseif(isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'],'partial_shipment')){
                if(isset($_REQUEST['action']) && $_REQUEST['action']=='trash-shipment'){
                    if(isset($_REQUEST['shipment-id']) && $_REQUEST['shipment-id']){
                        global $wpdb;
                        $row = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE id=".$_REQUEST['shipment-id']);
                        if(isset($row->order_id) && isset($_REQUEST['id']) && $row->order_id==$_REQUEST['id']){
                            $wpdb->delete($wpdb->prefix."partial_shipment",array('id'=>$row->id));
                            $wpdb->delete($wpdb->prefix."partial_shipment_items",array('shipment_id'=>$row->id));
                            $this->add_pf_notice(__('Shipment #'.$row->shipment_id.' deleted successfully.','wxp-partial-shipment'),"info");
                        }
                    }
                    wp_redirect(admin_url('admin.php?page=partial-shipment&id='.$_REQUEST['id'].'&_wpnonce='.$_REQUEST['_wpnonce']));
                    exit;
                }
            }
        }

        function get_shipment_id($order_id){
            global $wpdb;
            $qry = "SELECT MAX(shipment_id) as ship_id FROM ".$wpdb->prefix."partial_shipment WHERE order_id=".$order_id;
            $ship_id = $wpdb->get_var($qry);
            $ship_id = $ship_id+1;
            return $ship_id;
        }

        function get_qty($item_id,$order_id,$qty){
            global $wpdb;
            $qry = "SELECT SUM(i.item_qty) as tot_qty FROM ".$wpdb->prefix."partial_shipment_items AS i,".$wpdb->prefix."partial_shipment AS s
                    WHERE i.shipment_id=s.id AND i.item_id=".$item_id." AND s.order_id=".$order_id;
            $shipped = $wpdb->get_var($qry);
            $qty = $qty-$shipped;
            return $qty;
        }

        function get_shipped_qty($item_id,$order_id){
            global $wpdb;
            $qry = "SELECT SUM(i.item_qty) as tot_qty FROM ".$wpdb->prefix."partial_shipment_items AS i,".$wpdb->prefix."partial_shipment AS s
                    WHERE i.shipment_id=s.id AND i.item_id=".$item_id." AND s.order_id=".$order_id;
            $shipped = $wpdb->get_var($qry);
            return $shipped>0 ? $shipped : 0;
        }

        function get_edit_qty($item_id,$order_id,$shipment_id,$qty){
            global $wpdb;
            $qry = "SELECT SUM(i.item_qty) as tot_qty FROM ".$wpdb->prefix."partial_shipment_items AS i,".$wpdb->prefix."partial_shipment AS s
                    WHERE i.shipment_id=s.id AND i.item_id=".$item_id." AND i.shipment_id!=".$shipment_id." AND s.order_id=".$order_id;
            $shipped = $wpdb->get_var($qry);
            $qty = $qty-$shipped;
            return $qty;
        }

        function get_shipment($shipment_id,$order_id){
            global $wpdb;
            $qry = "SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE id=".$shipment_id." AND order_id=".$order_id;
            $shipment = $wpdb->get_row($qry);
            return $shipment;
        }

        function get_items($shipment_id){
            global $wpdb;
            $qry = "SELECT * FROM ".$wpdb->prefix."partial_shipment_items WHERE shipment_id=".$shipment_id;
            $shipment = $wpdb->get_results($qry);
            return $shipment;
        }

        function add_pf_notice($notice="",$type="warning",$dismissible=true){
            $notices = get_option("_wphub_pf_notices",array());
            $dismissible_text = ($dismissible) ? "is-dismissible" : "";
            array_push($notices,array(
                "notice" => $notice,
                "type" => $type,
                "dismissible" => $dismissible_text
            ));
            update_option("_wphub_pf_notices",$notices);
        }

        function display_pf_admin_notice(){
            $notices = get_option("_wphub_pf_notices",array());
            if(is_array($notices) && !empty($notices)){
                foreach($notices as $key=>$notice){
                    printf('<div class="pf-notice notice notice-%1$s %2$s"><p>%3$s</p></div>',
                        $notice['type'],
                        $notice['dismissible'],
                        $notice['notice']
                    );
                    if(isset($notices[$key])){
                        unset($notices[$key]);
                    }
                }
            }
            update_option("_wphub_pf_notices",$notices);
        }

        function get_label_title($shipped,$qty){
            if($shipped==0){
                return $this->wc_partial_labels['not-shipped'];
            }
            elseif($shipped==$qty){
                return $this->wc_partial_labels['shipped'];
            }
            elseif($shipped<$qty){
                return $this->wc_partial_labels['partially-shipped'];
            }
            else
            {
                return $this->wc_partial_labels['not-shipped'];
            }
        }

        function get_label_class($shipped,$qty){
            if($shipped==0){
                return 'wphub-not-shipped';
            }
            elseif($shipped==$qty){
                return 'wphub-shipped';
            }
            elseif($shipped<$qty){
                return 'wphub-partially-shipped';
            }
            else
            {
                return 'wphub-not-shipped';
            }
        }

        function settings(){
            $wcp = new Wphub_Partial_Shipment_Settings();
            $wcp->init();
            $this->load_settings();
        }

        function load_settings(){

            $this->wc_partial_labels['shipped'] = get_option('partially_shipped_custom')!='' ? get_option('partially_shipped_custom') : __('Shipped','wxp-partial-shipment');
            $this->wc_partial_labels['not-shipped'] = get_option('partially_not_shipped_custom')!='' ? get_option('partially_not_shipped_custom') : __('Not Shipped','wxp-partial-shipment');
            $this->wc_partial_labels['partially-shipped'] = get_option('partially_shipped_label_custom')!='' ? get_option('partially_shipped_label_custom') : __('Partially Shipped','wxp-partial-shipment');

            $this->wc_partial_labels = apply_filters('wc_partial_labels',$this->wc_partial_labels);

            $this->wxp_api = get_option('enable_wxp_api')!='' ? get_option('enable_wxp_api') : 'no';
            $this->wxp_api_key = get_option('wxp_api_key');

            $this->wc_partial_shipment_settings = array(
                'partially_shipped_status' => get_option('partially_shipped_status')!='' ? get_option('partially_shipped_status') : 'yes',
                'partially_auto_complete' => get_option('partially_auto_complete')!='' ? get_option('partially_auto_complete') : 'yes',
                'partially_enable_status_popup' => get_option('partially_enable_status_popup')!='' ? get_option('partially_enable_status_popup') : 'yes',
            );
        }

        function add_partial_complete_status($statuses){
            if(isset($this->wc_partial_shipment_settings['partially_shipped_status'])){
                if($this->wc_partial_shipment_settings['partially_shipped_status']=='yes'){
                    $statuses['wc-partial-shipped'] = __('Partially Shipped','wxp-partial-shipment');
                }
            }
            return $statuses;
        }

        function wxp_partial_complete_register_status(){
            if(isset($this->wc_partial_shipment_settings['partially_shipped_status'])){
                if($this->wc_partial_shipment_settings['partially_shipped_status']=='yes'){
                    register_post_status('wc-partial-shipped', array(
                        'label' => __('Partially Shipped','wxp-partial-shipment'),
                        'public' => true,
                        'exclude_from_search' => false,
                        'show_in_admin_all_list' => true,
                        'show_in_admin_status_list' => true,
                        'label_count' => _n_noop('Partially Shipped <span class="count">(%s)</span>', 'Partially Shipped <span class="count">(%s)</span>')
                    ));
                }
            }
        }

        function wxp_order_status_in_popup($columns,$order){
            if(isset($this->wc_partial_shipment_settings['partially_enable_status_popup'])){
                if($this->wc_partial_shipment_settings['partially_enable_status_popup']=='yes'){
                    $columns['wxp_status'] = __('Status','wxp-partial-shipment');
                }
            }
            return $columns;
        }

        function wxp_order_status_in_popup_value($val,$item,$item_id,$order){
            if(isset($this->wc_partial_shipment_settings['partially_enable_status_popup'])){
                if(is_a($item,'WC_Order_Item_Product') && $this->wc_partial_shipment_settings['partially_enable_status_popup']=='yes'){
                    $order_id = $item->get_order_id();
                    $product = $item->get_product();
                    $qty = $item->get_quantity();
                    $icon = '';
                    if(is_a($product,'WC_Product') && !$product->is_virtual()){
                        $qty_shipped = $this->get_shipped_qty($item_id,$order_id);
                        $label_title = $this->get_label_title($qty_shipped,$qty);
                        $label_class = $this->get_label_class($qty_shipped,$qty);
                        $icon = '<span data-tip="'.$label_title.'" class="tips wphub-status-label '.$label_class.'">'.$qty_shipped.'</span>';
                    }
                    $val = $icon;
                }
            }
            return $val;
        }

        function wxp_order_item_headers($order){
            echo '<th class="wxp-partital-item-head">'.__('Shipment','wxp-partial-shipment').'</th>';
        }

        function wxp_order_item_values($product,$item,$item_id){
            if(is_a($product,'WC_Product') && is_a($item,'WC_Order_Item_Product')){
                $order_id = $item->get_order_id();
                $qty = $item->get_quantity();
                $qty_shipped = $this->get_shipped_qty($item_id,$order_id);
                $icon = '';
                if(!$product->is_virtual()){
                    $shipped = $this->get_shipped_qty($item_id,$order_id);
                    $label_title = $this->get_label_title($qty_shipped,$qty);
                    $label_class = $this->get_label_class($qty_shipped,$qty);
                    $icon = '<span data-tip="'.$label_title.'" class="tips wphub-status-label '.$label_class.'">'.$shipped.'</span>';
                }
                echo '<td class="wxp-partital-item-icon" width="1%">'.$icon.'</td>';
            }
            else
            {
                echo '<td></td>';
            }
        }

        function wxp_order_shipment_button($order){
            echo '<a target="_blank" href="'.wp_nonce_url(admin_url('admin.php?page=partial-shipment&id='.$order->get_id()),'partial_shipment').'" class="button wxp-order-shipment">'.__('Shipment','wxp-partial-shipment').'</a>';
        }

        function wxp_order_item_icons($item_id, $item, $order, $bol = false){
			if(is_a($item,'WC_Order_Item_Product')){
				$product = $item->get_product();
				$order_id = $item->get_order_id();
				if(is_a($product,'WC_Product') && !$product->is_virtual()){
					if(is_page()){
						$qty = $item->get_quantity();
						$qty_shipped = $this->get_shipped_qty($item_id,$order_id);
						$label_title = $this->get_label_title($qty_shipped,$qty);
						$label_class = $this->get_label_class($qty_shipped,$qty);
						$icon = '<span data-tip="'.$label_title.'" class="tips wphub-status-label '.$label_class.'">'.$qty_shipped.'</span>';
						echo $icon;
					}
				}
			}
        }

        function wxp_order_details($order){
            wc_get_template(
                'order/shipment-details.php',
                array('order'=>$order),
                '',
                WPHUB_PARTIAL_SHIP.'/templates/'
            );
        }

        function wxp_partial_shipment_order_status($order_id){
            if($this->wc_partial_shipment_settings['partially_auto_complete']!='yes'){
                return null;
            }
            $order = wc_get_order($order_id);
            $mark = true;
            if(is_a($order,'WC_Order')){
                $items = $order->get_items();
                if(is_array($items) && !empty($items)){
                    foreach($items as $item_id=>$item){
                        $product = is_a($item,'WC_Order_Item_Product') ? $item->get_product() : new stdClass();
                        if(is_a($product,'WC_Product') && !$product->is_virtual()){
                            $qty = $item->get_quantity();
                            $shipped_qty = $this->get_shipped_qty($item_id,$order_id);
                            if($qty>0 && $shipped_qty<$qty){
                                $mark = false;
                                break;
                            }
                        }
                    }
                }
            }
            if($mark){
                if(is_a($order,'WC_Order')){
					$status = apply_filters('wphub_shipment_complete_status','completed');
                    $order->update_status($status,__('Order marked auto '.wc_get_order_status_name($status).' by advance partial shipment.','wxp-partial-shipment'));
                }
            }
			else
			{
				$order->update_status('partial-shipped',__('Order Partially Shipped by advance partial shipment.','wxp-partial-shipment'));
			}
        }

        function wxp_data_route(){
            register_rest_route('wxp-shipment-data','wxp-data', array(
                    'methods' => 'POST',
                    'callback' => array($this,'wxp_shipment_data'),
                    'permission_callback' => '__return_true',
                    'args' => array()
                )
            );
        }

        function get_item_id($sku,$items){
            $id = 0;
            if(is_array($items) && !empty($items)){
                foreach($items as $item_id=>$item){
                    $product = is_a($item,'WC_Order_Item_Product') ? $item->get_product() :  new stdClass();
                    if(is_a($product,'WC_Product') && !$product->is_virtual()){
                        $product_sku = $product->get_sku();
                        if($product_sku==trim($sku)){
                            $id = $item_id;
                            break;
                        }
                    }
                }
            }
            return $id;
        }

        function wxp_shipment_data($args){

            if($this->wxp_api=='no'){
                return new WP_REST_Response(__('API is Disabled.','wxp-partial-shipment'),200);
            }
            $params = $args->get_params();
            if($params['key']!=$this->wxp_api_key){
                return new WP_REST_Response(__('Invalid API Key.','wxp-partial-shipment'),200);
            }

            if(isset($params['order-id']) && $params['order-id']){
                global $wpdb;
                $order = wc_get_order($params['order-id']);
                if(is_a($order,'WC_Order')){

                    $order_id = $order->get_id();
                    if(isset($params['action']) && $params['action']=='update'){
                        $data = array(
                            'order_id' => isset($params['order-id']) ? sanitize_text_field($params['order-id']) : 0,
                            'shipment_id' => isset($params['order-id']) ? $this->get_shipment_id(sanitize_text_field($params['order-id'])) : 0,
                            'shipment_url'=>isset($params['tracking_url']) ? sanitize_url($params['tracking_url']) : '',
                            'shipment_num'=>isset($params['tracking_num']) ? sanitize_text_field($params['tracking_num']) : '',
                            'shipment_date'=>current_time('timestamp',0),
                        );
                        $wpdb->insert($wpdb->prefix."partial_shipment",$data,array('%d','%d','%s','%s','%s'));
                        $shipment_id = $wpdb->insert_id;
                        if($shipment_id){
                            $order_items = $order->get_items();
                            $values = array();
                            if(isset($params['items']) && is_array($params['items']) && !empty($params['items'])){
                                foreach($params['items'] as $item){
                                    if($item['qty']>0){
                                        $item_id = $this->get_item_id($item['sku'],$order_items);
                                        if($item_id){
                                            $values[$item_id] = $wpdb->prepare("(%d,%d,%d)",$shipment_id,$item_id,$item['qty']);
                                        }

                                    }
                                }
                            }
                            if(!empty($values)){
                                $wpdb->query("INSERT INTO ".$wpdb->prefix."partial_shipment_items (shipment_id,item_id,item_qty) VALUES ".implode(',',$values)."");
                                $last_id = $wpdb->insert_id;
                                if($last_id){
                                    do_action('wphub_partial_shipment_status',$order_id);
                                    do_action('wphub_partial_shipment_new_email',$order_id,$shipment_id);
                                    $response = rest_ensure_response(array('updated'=>true,'error'=>false));
                                    $response->header('Content-Type',"application/json");
                                    delete_transient($order_id.'_wxp_partial_shipment');
                                    return $response;
                                }
                            }
                        }

                        $response = rest_ensure_response(array('updated'=>false,'error'=>true));
                        $response->header('Content-Type',"application/json");
                        return $response;
                    }
                    else
                    {
                        $parcel = array();
                        if(false === ($parcel = get_transient($order_id.'_wxp_partial_shipment'))){
                            $shipments = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE order_id=".$order_id);
                            if(is_array($shipments) && !empty($shipments)){
                                foreach($shipments as $shipment){
                                    $shipped_items = array();
                                    $items = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment_items WHERE shipment_id=".$shipment->id);
                                    if(is_array($items) && !empty($items)){
                                        foreach($items as $item){
                                            $itm = $order->get_item($item->item_id);
                                            if(is_a($itm,'WC_Order_Item_Product')){
                                                $product = $itm->get_product();
                                                $shipped_items[$item->item_id] = array(
                                                    'name'=>$itm->get_name(),
                                                    'sku'=>$product->get_sku(),
                                                    'qty'=>$item->item_qty,
                                                );
                                            }
                                        }
                                    }
                                    if(is_array($shipped_items) && count($shipped_items)>0){
                                        $parcel[$shipment->shipment_id]=array(
                                            'tracking_url'=>$shipment->shipment_url,
                                            'tracking_num'=>$shipment->shipment_num,
                                            'items'=>$shipped_items
                                        );
                                    }
                                }
                            }
                            set_transient($order_id.'_wxp_partial_shipment',$parcel,120);
                        }
                        if(is_array($parcel) && !empty($parcel)){
                            $data = array(
                                'order-id'=>$order_id,
                                'shipment'=> $parcel
                            );
                            $response = rest_ensure_response($data);
                            $response->header('Content-Type',"application/json");
                            return $response;
                        }
                        $response = rest_ensure_response(array('updated'=>false,'error'=>true));
                        $response->header('Content-Type',"application/json");
                        return $response;
                    }

                }
            }
            exit;
        }

        function wphub_partial_email_classes($classes){
            $classes['Wphub_Partial_Shipment_Email'] = include('class-wphub-partial-shipment-email.php');
            $classes['Wphub_Partial_Shipment_Update_Email'] = include('class-wphub-partial-shipment-update-email.php');
            return $classes;
        }

        function wphub_partial_email_new($order_id,$shipment_id){
            $mails = WC()->mailer()->get_emails();
            $mails['Wphub_Partial_Shipment_Email']->trigger($order_id,false,$shipment_id);
        }

        function wphub_partial_email_update($order_id,$shipment_id){
            $mails = WC()->mailer()->get_emails();
            $mails['Wphub_Partial_Shipment_Update_Email']->trigger($order_id,false,$shipment_id);
        }

        function order_details($order,$shipment_id,$sent_to_admin=false,$plain_text=false,$email=''){

            if($plain_text){
                wc_get_template(
                    'emails/plain/order-details.php', array(
                    'order'         => $order,
                    'shipment_id'   => $shipment_id,
                    'sent_to_admin' => $sent_to_admin,
                    'plain_text'    => $plain_text,
                    'email'         => $email,
                ),
                    '',
                    WPHUB_PARTIAL_SHIP.'/templates/'
                );
            } else {
                wc_get_template(
                    'emails/order-details.php', array(
                    'order'         => $order,
                    'shipment_id'   => $shipment_id,
                    'sent_to_admin' => $sent_to_admin,
                    'plain_text'    => $plain_text,
                    'email'         => $email,
                ),
                    '',
                    WPHUB_PARTIAL_SHIP.'/templates/'
                );
            }
        }

        function get_shipment_items($shipment_id,$order){
            global $wpdb;
            $items = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment_items WHERE shipment_id=".$shipment_id);
            return $items;
        }

        function get_shipment_details($shipment_id){
            global $wpdb;
            $details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE id=".$shipment_id);
            return $details;
        }

	    function is_order_shipped($order_id){
		    global $wpdb;
		    $qry = "SELECT COUNT(id) FROM ".$wpdb->prefix."partial_shipment WHERE order_id=".$order_id;
		    $shipped = $wpdb->get_var($qry);
		    return $shipped;
	    }


	    function get_tracking_list(){

            return apply_filters(
                'wphub_shipment_tracking_providers',
                array(
                    'AU'      => array(
                        'Australia Post'   => 'https://auspost.com.au/mypost/track/#/details/%1$s',
                        'Fastway Couriers' => 'https://www.fastway.com.au/tools/track/?l=%1$s',
                    ),
                    'AT'        => array(
                        'post.at' => 'https://www.post.at/sv/sendungsdetails?snr=%1$s',
                        'dhl.at'  => 'https://www.dhl.at/content/at/de/express/sendungsverfolgung.html?brand=DHL&AWB=%1$s',
                        'DPD.at'  => 'https://tracking.dpd.de/parcelstatus?locale=de_AT&query=%1$s',
                    ),
                    'BR'         => array(
                        'Correios' => 'http://websro.correios.com.br/sro_bin/txect01$.QueryList?P_LINGUA=001&P_TIPO=001&P_COD_UNI=%1$s',
                    ),
                    'BE'        => array(
                        'bpost' => 'https://track.bpost.be/btr/web/#/search?itemCode=%1$s',
                    ),
                    'CA'         => array(
                        'Canada Post' => 'https://www.canadapost-postescanada.ca/track-reperage/en#/resultList?searchFor=%1$s',
                        'Purolator'   => 'https://www.purolator.com/purolator/ship-track/tracking-summary.page?pin=%1$s',
                    ),
                    'CZ' => array(
                        'PPL.cz'      => 'https://www.ppl.cz/main2.aspx?cls=Package&idSearch=%1$s',
                        'Česká pošta' => 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?parcelNumbers=%1$s',
                        'DHL.cz'      => 'https://www.dhl.cz/cs/express/sledovani_zasilek.html?AWB=%1$s',
                        'DPD.cz'      => 'https://tracking.dpd.de/parcelstatus?locale=cs_CZ&query=%1$s',
                    ),
                    'FI'        => array(
                        'Itella' => 'https://www.posti.fi/itemtracking/posti/search_by_shipment_id?lang=en&ShipmentId=%1$s',
                    ),
                    'FR'         => array(
                        'Colissimo' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%1$s',
                    ),
                    'DE'        => array(
                        'DHL Intraship (DE)' => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s&rfn=&extendedSearch=true',
                        'Hermes'             => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#%1$s',
                        'Deutsche Post DHL'  => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s',
                        'UPS Germany'        => 'https://wwwapps.ups.com/WebTracking?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=de_DE&InquiryNumber1=%1$s',
                        'DPD.de'             => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=en_DE',
                    ),
                    'IE'        => array(
                        'DPD.ie'  => 'https://dpd.ie/tracking?deviceType=5&consignmentNumber=%1$s',
                        'An Post' => 'https://track.anpost.ie/TrackingResults.aspx?rtt=1&items=%1$s',
                    ),
                    'IT'          => array(
                        'BRT (Bartolini)' => 'https://as777.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Nspediz=%1$s',
                        'DHL Express'     => 'https://www.dhl.it/it/express/ricerca.html?AWB=%1$s&brand=DHL',
                    ),
                    'IN'          => array(
                        'DTDC' => 'https://www.dtdc.in/tracking/tracking_results.asp?Ttype=awb_no&strCnno=%1$s&TrkType2=awb_no',
                    ),
                    'NL'    => array(
                        'DPD.NL'          => 'https://tracking.dpd.de/status/en_US/parcel/%1$s',
                        'UPS Netherlands' => 'https://wwwapps.ups.com/WebTracking?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=nl_NL&InquiryNumber1=%1$s',
                    ),
                    'NZ'    => array(
                        'Courier Post' => 'https://trackandtrace.courierpost.co.nz/Search/%1$s',
                        'NZ Post'      => 'https://www.nzpost.co.nz/tools/tracking?trackid=%1$s',
                        'Aramex'       => 'https://www.aramex.co.nz/tools/track?l=%1$s',
                        'PBT Couriers' => 'http://www.pbt.com/nick/results.cfm?ticketNo=%1$s',
                    ),
                    'PL'         => array(
                        'InPost'        => 'https://inpost.pl/sledzenie-przesylek?number=%1$s',
                        'DPD.PL'        => 'https://tracktrace.dpd.com.pl/parcelDetails?p1=%1$s',
                        'Poczta Polska' => 'https://emonitoring.poczta-polska.pl/?numer=%1$s',
                    ),
                    'RO'        => array(
                        'Fan Courier'   => 'https://www.fancourier.ro/awb-tracking/?xawb=%1$s',
                        'DPD Romania'   => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=ro_RO',
                        'Urgent Cargus' => 'https://app.urgentcargus.ro/Private/Tracking.aspx?CodBara=%1$s',
                    ),
                    'ZA' => array(
                        'SAPO'    => 'http://sms.postoffice.co.za/TrackingParcels/Parcel.aspx?id=%1$s',
                        'Fastway' => 'https://fastway.co.za/our-services/track-your-parcel?l=%1$s',
                    ),
                    'SE'         => array(
                        'PostNord Sverige AB' => 'https://portal.postnord.com/tracking/details/%1$s',
                        'DHL.se'              => 'https://www.dhl.com/se-sv/home/tracking.html?submit=1&tracking-id=%1$s',
                        'Bring.se'            => 'https://tracking.bring.se/tracking/%1$s',
                        'UPS.se'              => 'https://www.ups.com/track?loc=sv_SE&tracknum=%1$s&requester=WT/',
                        'DB Schenker'         => 'http://privpakportal.schenker.nu/TrackAndTrace/packagesearch.aspx?packageId=%1$s',
                    ),
                    'GB' => array(
                        'DHL'                       => 'https://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=%1$s',
                        'DPD.co.uk'                 => 'https://www.dpd.co.uk/apps/tracking/?reference=%1$s#results',
                        'InterLink'                 => 'https://www.dpdlocal.co.uk/apps/tracking/?reference=%1$s#results',
                        'ParcelForce'               => 'https://www.parcelforce.com/track-trace?trackNumber=%1$s',
                        'Royal Mail'                => 'https://www3.royalmail.com/track-your-item#/tracking-results/%1$s',
                        'TNT Express (consignment)' => 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=con&cons=%1$s',
                        'TNT Express (reference)'   => 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=ref&cons=%1$s',
                        'DHL Parcel UK'             => 'https://track.dhlparcel.co.uk/?con=%1$s',
                    ),
                    'US'  => array(
                        'Fedex'         => 'https://www.fedex.com/apps/fedextrack/?action=track&action=track&tracknumbers=%1$s',
                        'FedEx Sameday' => 'https://www.fedexsameday.com/fdx_dotracking_ua.aspx?tracknum=%1$s',
                        'OnTrac'        => 'http://www.ontrac.com/trackingdetail.asp?tracking=%1$s',
                        'UPS'           => 'https://www.ups.com/track?loc=en_US&tracknum=%1$s',
                        'USPS'          => 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=%1$s',
                        'DHL US'        => 'https://www.logistics.dhl/us-en/home/tracking/tracking-ecommerce.html?tracking-id=%1$s',
                    ),
                )
            );
        }

    }

}