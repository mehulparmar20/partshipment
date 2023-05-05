<?php
defined('ABSPATH') || exit;

if(isset($order) && is_a($order,'WC_Order')){
    global $wpdb;

    $shipping_country = $order->get_shipping_country();
    $tracking_list = wphub_partial_shipment()->partial_shipment->get_tracking_list();
    $tlist = isset($tracking_list[$shipping_country]) ? $tracking_list[$shipping_country] : array();

    $shipments = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment WHERE order_id=".$order->get_id());
    if(is_array($shipments) && !empty($shipments)){
        foreach($shipments as $shipment){
            //echo '<pre>'; print_r($shipment); echo '</pre>';
            ?>
            <div class="shipment-section">
            <div class="shipment-head">
                <?php echo __('Shipment','wxp-partial-shipment'); ?> #<?php echo $shipment->shipment_id; ?>
                <span><?php echo date_i18n(get_option('date_format'),$shipment->shipment_date); ?></span>
            </div>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details wphub-shipment-provider-details">
                <?php
                $provider = array_search($shipment->shipment_url,$tlist);
                if($provider!=''){
                    $turl = sprintf($shipment->shipment_url,$shipment->shipment_num);
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>'.__('Shipment Provider','wxp-partial-shipment').'</th>';
                    echo '<th>'.__('Tracking URL','wxp-partial-shipment').'</th>';
                    echo '</tr>';
                    echo '</thead>';

                    echo '<tbody>';
                    echo '<tr>';
                    echo '<td>'.$provider.'</td>';
                    echo '<td><a target="_blank" href="'.$turl.'">'.$turl.'</a></td>';
                    echo '</tr>';
                    echo '</tbody>';
                }
                elseif(isset($shipment->shipment_num) && trim($shipment->shipment_num)!='')
                {
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>'.__('Tracking URL','wxp-partial-shipment').'</th>';
                    echo '<th>'.__('Tracking Number','wxp-partial-shipment').'</th>';
                    echo '</tr>';
                    echo '</thead>';

                    echo '<tbody>';
                    echo '<tr>';
                    echo '<td>'.$shipment->shipment_num.'</td>';
                    echo '<td><a target="_blank" href="'.$shipment->shipment_url.'">'.$shipment->shipment_url.'</a></td>';
                    echo '</tr>';
                    echo '</tbody>';
                }
                ?>
            </table>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details partial_shipment_details">
                <thead>
                <tr>
                    <th class="pf-product-name"><?php esc_html_e( 'Product', 'wxp-partial-shipment'); ?></th>
                    <th class="pf-shipped"><?php esc_html_e( 'Qty', 'wxp-partial-shipment'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $items = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."partial_shipment_items WHERE shipment_id=".$shipment->id);
                if(is_array($items) && !empty($items)){
                    foreach($items as $item){
                        $itm = $order->get_item($item->item_id);
                        $product = $itm->get_product();
                        if(!is_a($itm,'WC_Order_Item_Product') || (is_a($product,'WC_Product') && $product->is_virtual())){
                            continue;
                        }

                        echo '<tr>';
                        echo '<td>';
                        echo '<span class="item-title">'.esc_attr($itm->get_name()).'</span>';
                        echo wc_display_item_meta($itm);
                        echo '</td>';
                        echo '<td>'.$item->item_qty.'</td>';
                        echo '</tr>';
                    }
                }
                ?>
                </tbody>
            </table>
            </div>
            <?php
        }
    }
}