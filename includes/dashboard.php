<?php
defined( 'ABSPATH' ) || exit;
$order_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$order = wc_get_order($order_id);
$order_time = $order->get_date_created()->getTimestamp();
$tracking_list = wphub_partial_shipment()->partial_shipment->get_tracking_list();

$shipping_country = $order->get_shipping_country();
$tlist = isset($tracking_list[$shipping_country]) ? $tracking_list[$shipping_country] : array();

//echo $order->get_date_created(); die;
?>
<div class="wrap pf-order-info">
    <div class="pf-order-in">

        <div class="pf-order-row">
            <div class="col-1">
                <div class="pf-heading-inline"><?php echo __('Order','wxp-partial-shipment').' #'.$order->get_order_number(); ?></div>
                <div class="pf-status">(<?php echo wc_get_order_status_name($order->get_status()); ?>)</div>
                <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="page-title-action"><?php echo __('Back','wxp-partial-shipment'); ?></a>
            </div>
            <div class="col-1"><h4><?php echo esc_attr(date_i18n(wc_date_format(),$order_time)); ?></h4></div>
        </div>
        <div class="pf-order-row pf-col-flex">
            <div class="pf-col-1-2 pf-flex-in pf-mr-1">
                <div class="pf-col-head"><?php echo __('Billing Address','wxp-partial-shipment'); ?></div>
                <div class="pf-col-txt">
                    <div class="col-rw"><?php echo $order->get_billing_first_name().' '.$order->get_billing_last_name(); ?></div>
                    <div class="col-rw"><?php echo $order->get_billing_company(); ?></div>
                    <div class="col-rw"><?php echo $order->get_billing_address_1(); ?></div>
                    <div class="col-rw"><?php echo $order->get_billing_address_2(); ?></div>
                    <div class="col-rw"><?php echo $order->get_billing_city(); ?>, <?php echo $order->get_billing_state(); ?> <?php echo $order->get_billing_postcode(); ?></div>
                    <div class="col-rw"><?php echo WC()->countries->countries[$order->get_billing_country()]; ?></div>
                    <div class="col-rw pf-bill-line"><strong><?php echo __('Phone','wxp-partial-shipment'); ?>:</strong><?php echo $order->get_billing_phone(); ?></div>
                    <div class="col-rw pf-bill-line"><strong><?php echo __('Email','wxp-partial-shipment'); ?>:</strong><?php echo $order->get_billing_email(); ?></div>
                </div>
            </div>
            <div class="pf-col-1-2 flex-in pf-mr-1">
                <div class="pf-col-head"><?php echo __('Shipping Address','wxp-partial-shipment'); ?></div>
                <div class="pf-col-txt">
                    <?php if($order->needs_shipping_address()): ?>
                    <div class="col-rw"><?php echo $order->get_shipping_first_name().' '.$order->get_shipping_last_name(); ?></div>
                    <div class="col-rw"><?php echo $order->get_shipping_company(); ?></div>
                    <div class="col-rw"><?php echo $order->get_shipping_address_1(); ?></div>
                    <div class="col-rw"><?php echo $order->get_shipping_address_2(); ?></div>
                    <div class="col-rw"><?php echo $order->get_shipping_city(); ?>, <?php echo $order->get_shipping_state(); ?> <?php echo $order->get_shipping_postcode(); ?></div>
                    <div class="col-rw"><?php echo WC()->countries->countries[$order->get_shipping_country()]; ?></div>
                    <?php
                    else:
                        echo '<div class="col-rw">';
                        echo __('No shipping address set.','wxp-partial-shipment');
                        echo '</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>
        <div class="pf-order-row">
            <div class="pf-mr-1">
                <table class="pf-table pf-table-items">
                    <thead>
                    <tr>
                        <th class="pro-img"><?php echo __('Description','wxp-partial-shipment'); ?></th>
                        <th class="pro-price"><?php echo __('Unit Price','wxp-partial-shipment'); ?></th>
                        <th class="pro-qty"><?php echo __('Quantity','wxp-partial-shipment'); ?></th>
                        <th class="pro-shipped"><?php echo __('Shipped','wxp-partial-shipment'); ?></th>
                        <th class="pro-subtotal"><?php echo __('Subtotal','wxp-partial-shipment'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types','line_item'));
                    foreach($order_items as $item_id => $item){
                        if(!is_a($item,'WC_Order_Item_Product')){
                            continue;
                        }
                        $data = $item->get_data();
	                    $product = is_a($item,'WC_Order_Item_Product') ? $item->get_product() :  new stdClass();
                        $thumbnail    = $product ? apply_filters('woocommerce_admin_order_item_thumbnail',$product->get_image('thumbnail',array('title'=>''),false),$item_id,$item) : '';
                        ?>
                        <tr class="product-row">
                            <td class="pro-img">
                                <span class="pro-title"><?php echo $item->get_name(); ?></span>
                                <span class="pro-sku"><strong><?php echo __('SKU','wxp-partial-shipment'); ?>:</strong> <?php echo $product->get_sku(); ?></span>
                                <span class="pro-img">
                                <span class="pro-im-l"><?php echo wp_kses_post($thumbnail); ?></span>
                                <span class="pro-im-r">
                                    <span class="pro-meta"><?php wc_display_item_meta($item); ?></span>
                                </span>
                                <span class="pfcls"></span>
                            </span>
                            </td>
                            <td class="pro-price">
                                <?php
                                $unit_price = $item->get_subtotal()/$item->get_quantity();
                                echo wc_price($unit_price); ?>
                            </td>
                            <td class="pro-qty"><?php echo $item->get_quantity(); ?></td>
                            <td class="pro-shipped">
                                <?php
                                if(is_a($product,'WC_Product') && !$product->is_virtual()){
                                    $shipped = wphub_partial_shipment()->partial_shipment->get_shipped_qty($item_id,$order_id);
                                    $label_title = wphub_partial_shipment()->partial_shipment->get_label_title($shipped,$item->get_quantity());
                                    $label_class = wphub_partial_shipment()->partial_shipment->get_label_class($shipped,$item->get_quantity());
                                    echo '<span data-tip="'.$label_title.'" class="tips wphub-status-label '.$label_class.'">'.$shipped.'</span>';
                                }
                                ?>
                            </td>
                            <td class="pro-subtotal"><?php echo $order->get_formatted_line_subtotal($item); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    <?php
                    foreach($order->get_order_item_totals() as $key => $total){
                        ?>
                        <tr class="tab-footr footr-top">
                            <td class="f-mid" colspan="4"><?php echo esc_html( $total['label'] ); ?></td>
                            <td class="f-mid-l"><?php echo ( 'payment_method' === $key ) ? esc_html($total['value']) : wp_kses_post($total['value']); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pf-order-row">
            <div class="pf-mr-1">
                <div class="shipment-rows">
                    <?php
                    $i=1;
                    global $wpdb;
                    $shipments = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE order_id=".$order_id);
                    if(is_array($shipments) && !empty($shipments)){
                        foreach($shipments as $shipment){

                            if(isset($_REQUEST['shipment-id']) && isset($_REQUEST['action']) && $_REQUEST['action']=='edit-shipment' && $shipment->id==$_REQUEST['shipment-id']){
                                continue;
                            }

                            //Dispatched Box Start
                            echo '<div class="pf-dispatched-shipment">';

                            //Shipment Table Start
                            echo '<div class="shiptable">';

                            echo '<div class="shiptable-head">';
                            echo '<div class="shiptable-row">';
                            echo '<div class="shiptable-cell">'.__('Shipment','wxp-partial-shipment').' #'.$shipment->shipment_id.'</div>';
                            echo '<div class="shiptable-cell pf-shipment-action">';
                            echo '<a class="button button-small" href="'.wp_nonce_url(admin_url('admin.php?page=partial-shipment&id='.$order->get_id().'&action=edit-shipment&shipment-id='.$shipment->id),'partial_shipment').'">'.__('Edit','wxp-partial-shipment').'</a>';
                            echo '<a class="button button-small" href="'.wp_nonce_url(admin_url('admin.php?page=partial-shipment&id='.$order->get_id().'&action=trash-shipment&shipment-id='.$shipment->id),'partial_shipment').'">'.__('Delete','wxp-partial-shipment').'</a>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';

                            echo '<div class="shiptable-body">';

                            echo '<div class="shiptable-row">';

                            echo '<div class="shiptable-cell">';
                            echo '<label>'.__('ID','wxp-partial-shipment').' : </label>';
                            echo '<span>'.$shipment->shipment_id.'</span>';
                            echo '</div>';

                            echo '<div class="shiptable-cell">';
                            echo '<label>'.__('Date','wxp-partial-shipment').' : </label>';
                            echo '<span>'.esc_attr(date_i18n(wc_date_format(),$shipment->shipment_date)).'</span>';
                            echo '</div>';

                            echo '</div>';

                            $provider = array_search($shipment->shipment_url,$tlist);

                            echo '<div class="shiptable-row">';

                            if($provider){
                                $turl = sprintf($shipment->shipment_url,$shipment->shipment_num);
                                echo '<div class="shiptable-cell">';
                                echo '<label>'.__('Shipment Provider','wxp-partial-shipment').' : </label>';
                                echo '<span>'.$provider.'</span>';
                                echo '</div>';

                                echo '<div class="shiptable-cell">';
                                echo '<label>'.__('Tracking URL','wxp-partial-shipment').' : </label>';
                                echo '<span><a target="_blank" href="'.$turl.'">'.$turl.'</a></span>';
                                echo '</div>';
                            }
                            else
                            {
                                echo '<div class="shiptable-cell">';
                                echo '<label>'.__('Tracking URL','wxp-partial-shipment').' : </label>';
                                echo '<span><a target="_blank" href="'.$shipment->shipment_url.'">'.$shipment->shipment_url.'</a></span>';
                                echo '</div>';

                                echo '<div class="shiptable-cell">';
                                echo '<label>'.__('Tracking Number','wxp-partial-shipment').' : </label>';
                                echo '<span>'.$shipment->shipment_num.'</span>';
                                echo '</div>';
                            }



                            echo '</div>';

                            echo '</div>';
                            echo '</div>';
                            //Shipment Table End

                            //Item Table Start
                            echo '<div class="shiptable-items">';

                            echo '<div class="shiptable-head">';
                            echo '<div class="shiptable-row">';
                            echo '<div class="shiptable-cell">'.__('Item','wxp-partial-shipment').'</div>';
                            echo '<div class="shiptable-cell">'.__('Shipped','wxp-partial-shipment').'</div>';
                            echo '</div>';
                            echo '</div>';

                            echo '<div class="shiptable-body">';
                            $items = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment_items WHERE shipment_id=".$shipment->id);
                            if(is_array($items) && !empty($items)){
                                foreach($items as $item){

                                    $itm = $order->get_item($item->item_id);
	                                $product = is_a($itm,'WC_Order_Item_Product') ? $itm->get_product() :  new stdClass();
                                    if(!is_a($itm,'WC_Order_Item_Product') || (is_a($product,'WC_Product') && $product->is_virtual())){
                                        continue;
                                    }
                                    echo '<div class="shiptable-row">';

                                    echo '<div class="shiptable-cell">';
                                    echo '<strong>'.esc_attr($itm->get_name()).'</strong>';
                                    echo '<span class="item-sku"><strong>SKU:</strong>'.esc_html($product->get_sku()).'</span>';
                                    echo '<span class="item-meta">'.wc_display_item_meta($itm).'</span>';
                                    echo '</div>';

                                    echo '<div class="shiptable-cell">'.$item->item_qty.'</div>';
                                    echo '</div>';
                                }
                            }
                            elseif(is_array($items) && count($items)==0){
                                echo '<div class="shiptable-row">';
                                echo '<div class="shiptable-cell"><strong>'.__('No item shipped.','wxp-partial-shipment').'</strong></div>';
                                echo '<div class="shiptable-cell"></div>';
                                echo '</div>';
                            }
                            echo '</div>';

                            echo '</div>';
                            //Item Table End


                            echo '</div>';
                            //Dispatched Box End
                            $i++;
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <?php
        $shipment_id = isset($_REQUEST['shipment-id']) ? abs($_REQUEST['shipment-id']) : 0;
        if(isset($_REQUEST['shipment-id']) && isset($_REQUEST['action']) && $_REQUEST['action']=='edit-shipment' && $_REQUEST['shipment-id']){
            ob_start();
            include(__DIR__.'/edit-shipment.php');
            $html = ob_get_clean();

            echo '<div class="pf-order-row">';
            echo '<div class="pf-mr-1">';
            echo '<div class="new-shipment" id="new-shipment">'.$html.'</div>';
            echo '</div>';
            echo '</div>';
        }
        else
        {
            ob_start();
            include(__DIR__.'/shipment.php');
            $html = esc_attr(ob_get_clean());

            echo '<div class="pf-order-row">';
            echo '<div class="pf-mr-1">';
            echo '<button data-row="'.$html.'" class="button primary add-shipment">'.__('Add Shipment','wxp-partial-shipment').'</button>';
            echo '<div class="new-shipment" id="new-shipment"></div>';
            echo '</div>';
            echo '</div>';
        }
        ?>

    </div>
</div>
