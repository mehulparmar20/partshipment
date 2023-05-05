<?php
defined( 'ABSPATH' ) || exit;
$order_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$order = wc_get_order($order_id);
$order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types','line_item'));
$shipping_country = $order->get_shipping_country();
$tracking_list = wphub_partial_shipment()->partial_shipment->get_tracking_list();
$tlist = isset($tracking_list[$shipping_country]) ? $tracking_list[$shipping_country] : array();
?>
<form method="post">
    <div class="shipment-box">
        <div class="shiptable">
            <div class="shiptable-body">
                <div class="shiptable-row">
                    <div class="shiptable-cell">
                        <?php
                        if(is_array($tlist) && count($tlist)>0){
                            echo '<label>'.__('Shipment Provider','wxp-partial-shipment').'</label>';
                            echo '<select name="shipment[url]" class="shipment-text">';
                            foreach($tlist as $tlk=>$tl){
                                echo '<option value="'.$tl.'">'.$tlk.'</option>';
                            }
                            echo '</select>';
                        }
                        else
                        {
                            echo '<label>'.__('Tracking URL','wxp-partial-shipment').'</label>';
                            echo '<input type="text" name="shipment[url]" class="shipment-text">';
                        }
                        ?>
                    </div>
                    <div class="shiptable-cell">
                        <label><?php echo __('Tracking Number','wxp-partial-shipment'); ?></label>
                        <input type="text" name="shipment[num]" class="shipment-text">
                    </div>
                </div>
            </div>
        </div>
        <div class="shiptable-items">
            <div class="shiptable-head">
                <div class="shiptable-row">
                    <div class="shiptable-cell"><?php echo __('Item','wxp-partial-shipment'); ?></div>
                    <div class="shiptable-cell"><?php echo __('Qty','wxp-partial-shipment'); ?></div>
                </div>
            </div>
            <div class="shiptable-body">
                <?php
                foreach($order_items as $item_id => $item){
	                $product = is_a($item,'WC_Order_Item_Product') ? $item->get_product() :  new stdClass();
                    if(!is_a($item,'WC_Order_Item_Product') || (is_a($product,'WC_Product') && $product->is_virtual())){
                        continue;
                    }
                    elseif(!is_a($product,'WC_Product')){
	                    continue;
                    }

                    $data = $item->get_data();
                    $product = $item->get_product();
                    $qty = $item->get_quantity();
                    $qty = wphub_partial_shipment()->partial_shipment->get_qty($item_id,$order_id,$qty);
                    if($qty==0){
                        continue;
                    }

                    echo '<div class="shiptable-row">';

                    echo '<div class="shiptable-cell">';
                    echo '<strong>'.$item->get_name().'</strong>';
                    echo '<span class="item-sku"><strong>'.__('SKU','wxp-partial-shipment').':</strong>'.$product->get_sku().'</span>';
                    echo '<span class="item-meta">'.wc_display_item_meta($item).'</span>';
                    echo '</div>';

                    echo '<div class="shiptable-cell">';
                    echo '<input type="hidden" name="item['.$item_id.'][id]" value="'.$item_id.'">';
                    echo '<input type="number" name="item['.$item_id.'][qty]" value="'.$qty.'" step="1" max="'.$qty.'" min="0">';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
            <div class="shiptable-foot">
                <div class="shiptable-row">
                    <div class="shiptable-cell">
                        <button type="submit" class="button button-primary"><?php echo __('Save Shipment','wxp-partial-shipment'); ?></button>
                    </div>
                    <div class="shiptable-cell">
                        <input type="hidden" name="shipment[order]" value="<?php echo $order->get_id(); ?>">
                        <input type="hidden" name="shipment[new]" value="0">
                        <input type="hidden" name="shipment[check]" value="<?php echo wp_create_nonce('add-pf-shipment'); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
