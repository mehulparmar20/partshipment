<?php
defined( 'ABSPATH' ) || exit;
$order_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$shipment_id = isset($_REQUEST['shipment-id']) ? $_REQUEST['shipment-id'] : 0;
$order = wc_get_order($order_id);
$shipment_edit = wphub_partial_shipment()->partial_shipment->get_shipment($shipment_id,$order->get_id());
if(!isset($shipment_edit->id)){
    exit;
}
$shipment_items = wphub_partial_shipment()->partial_shipment->get_items($shipment_edit->id);

$shipping_country = $order->get_shipping_country();
$tracking_list = wphub_partial_shipment()->partial_shipment->get_tracking_list();
$tlist = isset($tracking_list[$shipping_country]) ? $tracking_list[$shipping_country] : array();

//echo '<pre>'; print_r($shipment_edit->shipment_url); echo '</pre>';
//echo '<pre>'; print_r($shipment_items); echo '</pre>';
//die;
?>
<form method="post">
    <div class="shipment-box edit-shipment-box">
        <div class="shiptable">
            <div class="shiptable-head">
                <div class="shiptable-row">
                    <div class="shiptable-cell"><?php echo __('Edit shipment','wxp-partial-shipment'); ?> #<?php echo $shipment_edit->shipment_id; ?></div>
                    <div class="shiptable-cell pf-shipment-action"></div>
                </div>
            </div>
            <div class="shiptable-body">
                <div class="shiptable-row">
                    <div class="shiptable-cell">
                        <?php
                        $provider = array_search($shipment_edit->shipment_url,$tlist);
                        $provider = $provider!='' ? $provider : 'Other';
                        if(is_array($tlist) && count($tlist)>0){
                            echo '<label>'.__('Shipment Provider','wxp-partial-shipment').'</label>';
                            echo '<select name="shipment[url]" class="shipment-text">';
                            foreach($tlist as $tlk=>$tl){
                                echo '<option value="'.$tl.'" '.selected($tl,$shipment_edit->shipment_url,false).'>'.$tlk.'</option>';
                            }
                            echo '</select>';
                        }
                        else
                        {
                            echo '<label>'.__('Tracking URL','wxp-partial-shipment').'</label>';
                            echo '<input type="text" name="shipment[url]" class="shipment-text" value="'.$shipment_edit->shipment_url.'">';
                        }
                        ?>
                    </div>
                    <div class="shiptable-cell">
                        <label><?php echo __('Tracking Number','wxp-partial-shipment'); ?></label>
                        <input type="text" name="shipment[num]" class="shipment-text" value="<?php echo $shipment_edit->shipment_num; ?>">
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
                if(is_array($shipment_items) && !empty($shipment_items)){
                    foreach($shipment_items as $item_key => $item_row){

                        $item = $order->get_item($item_row->item_id);
	                    $product = is_a($item,'WC_Order_Item_Product') ? $item->get_product() :  new stdClass();
                        if(!is_a($item,'WC_Order_Item_Product') || (is_a($product,'WC_Product') && $product->is_virtual())){
                            continue;
                        }
                        elseif(!is_a($product,'WC_Product')){
	                        continue;
                        }

                        $data = $item->get_data();
                        $qty = $item->get_quantity(); 
                        $qty = wphub_partial_shipment()->partial_shipment->get_edit_qty($item_row->item_id,$order_id,$shipment_edit->id,$qty);
                        echo '<div class="shiptable-row">';

                        echo '<div class="shiptable-cell">';
                        echo '<strong>'.$item->get_name().'</strong>';
                        echo '<span class="item-sku"><strong>'.__('SKU','wxp-partial-shipment').':</strong>'.$product->get_sku().'</span>';
                        echo '<span class="item-meta">'.wc_display_item_meta($item).'</span>';
                        echo '</div>';

                        echo '<div class="shiptable-cell">';
                        echo '<input type="hidden" name="item['.$item_row->item_id.'][row-id]" value="'.$item_row->id.'">';
                        echo '<input type="hidden" name="item['.$item_row->item_id.'][id]" value="'.$item_row->item_id.'">';
                        echo '<input type="number" name="item['.$item_row->item_id.'][qty]" value="'.$item_row->item_qty.'" step="1" max="'.$qty.'" min="0">';
                        echo '</div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            <div class="shiptable-foot">
                <div class="shiptable-row">
                    <div class="shiptable-cell"></div>
                    <div class="shiptable-cell">
                        <input type="hidden" name="shipment[order]" value="<?php echo $order->get_id(); ?>">
                        <input type="hidden" name="shipment[update]" value="<?php echo $shipment_edit->id; ?>">
                        <input type="hidden" name="shipment[check]" value="<?php echo wp_create_nonce('add-pf-shipment'); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pf-order-row">
        <div class="pf-mr-0">
            <button type="submit" class="button button-primary"><?php echo __('Update Shipment','wxp-partial-shipment'); ?></button>
        </div>
    </div>
</form>
