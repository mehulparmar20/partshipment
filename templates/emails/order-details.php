<?php

defined( 'ABSPATH' ) || exit;

$text_align  = is_rtl() ? 'right' : 'left';
$margin_side = is_rtl() ? 'left' : 'right';
if(!$shipment_id){
    return null;
}

$show_image = false;
$show_sku = true;
$image_size = array(32,32);

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<h2>
    <?php
    if ( $sent_to_admin ) {
        $before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
        $after  = '</a>';
    } else {
        $before = '';
        $after  = '';
    }
    /* translators: %s: Order ID. */
    echo wp_kses_post( $before . sprintf( __( '[Order #%s]', 'wxp-partial-shipment' ) . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
    ?>
</h2>

<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
        <tr>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Product', 'wxp-partial-shipment' ); ?></th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Qty', 'wxp-partial-shipment' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $items = wphub_partial_shipment()->partial_shipment->get_shipment_items($shipment_id,$order);
        if(is_array($items) && !empty($items)){
            foreach($items as $itm) :
                $item          = $order->get_item($itm->item_id);
                $product       = $item->get_product();
                $sku           = '';
                $purchase_note = '';
                $image         = '';
                $item_id = $item->get_id();

                if(!apply_filters( 'woocommerce_order_item_visible',true,$item)){
                    continue;
                }

                if(is_object($product)){
                    $sku           = $product->get_sku();
                    $purchase_note = $product->get_purchase_note();
                    $image         = $product->get_image($image_size);
                }
                ?>
                <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
                    <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                        <?php

                        // Show title/image etc.
                        if ( $show_image ) {
                            echo wp_kses_post( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
                        }

                        // Product name.
                        echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );

                        // SKU.
                        if ( $show_sku && $sku ) {
                            echo wp_kses_post( ' (#' . $sku . ')' );
                        }

                        // allow other plugins to add additional product information here.
                        do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );

                        wc_display_item_meta(
                            $item,
                            array(
                                'label_before' => '<strong class="wc-item-meta-label" style="float: ' . esc_attr( $text_align ) . '; margin-' . esc_attr( $margin_side ) . ': .25em; clear: both">',
                            )
                        );

                        // allow other plugins to add additional product information here.
                        do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );

                        ?>
                    </td>
                    <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
                        <?php
                        $qty          = $itm->item_qty;
                        $refunded_qty = $order->get_qty_refunded_for_item( $item_id );

                        if($refunded_qty){
                            $qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * -1 ) ) . '</ins>';
                        } else {
                            $qty_display = esc_html( $qty );
                        }
                        echo wp_kses_post( apply_filters( 'woocommerce_email_order_item_quantity', $qty_display, $item ) );
                        ?>
                    </td>
                </tr>
            <?php endforeach;
        }
        ?>
        </tbody>
    </table>

    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <?php
        $shipment = wphub_partial_shipment()->partial_shipment->get_shipment_details($shipment_id);
        $shipping_country = $order->get_shipping_country();
        $tracking_list = wphub_partial_shipment()->partial_shipment->get_tracking_list();
        $tlist = isset($tracking_list[$shipping_country]) ? $tracking_list[$shipping_country] : array();
        $provider = array_search($shipment->shipment_url,$tlist);
        if($provider!=''){
            $turl = sprintf($shipment->shipment_url,$shipment->shipment_num);
            echo '<thead>';
            echo '<tr>';
            echo '<th class="td" scope="col" style="text-align:'.esc_attr($text_align).';width:30%;">'.esc_html__('Shipment Provider','wxp-partial-shipment').'</td>';
            echo '<th class="td" scope="col" style="text-align:'.esc_attr($text_align).';width:70%;">'.esc_html__('Tracking URL','wxp-partial-shipment').'</td>';
            echo '</tr>';
            echo '</thead>';

            echo '<tbody>';
            echo '<tr>';
            echo '<td class="td" style="text-align:'.esc_attr( $text_align ).'; vertical-align: middle; font-family:\'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">'.$provider.'</td>';
            echo '<td class="td" style="text-align:'.esc_attr( $text_align ).'; vertical-align: middle; font-family:\'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;"><a target="_blank" href="'.$turl.'">'.$turl.'</a></td>';
            echo '</tr>';
            echo '</tbody>';
        }
        elseif(isset($shipment->shipment_num) && trim($shipment->shipment_num)!='')
        {
            echo '<thead>';
            echo '<tr>';
            echo '<th class="td" scope="col" style="text-align:'.esc_attr($text_align).';width:30%;">'.esc_html__('Tracking Number','wxp-partial-shipment').'</td>';
            echo '<th class="td" scope="col" style="text-align:'.esc_attr($text_align).';width:70%;">'.esc_html__('Tracking URL','wxp-partial-shipment').'</td>';
            echo '</tr>';
            echo '</thead>';

            echo '<tbody>';
            echo '<tr>';
            echo '<td class="td" style="text-align:'.esc_attr( $text_align ).'; vertical-align: middle; font-family:\'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">'.$shipment->shipment_num.'</td>';
            echo '<td class="td" style="text-align:'.esc_attr( $text_align ).'; vertical-align: middle; font-family:\'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;"><a target="_blank" href="'.$shipment->shipment_url.'">'.$shipment->shipment_url.'</a></td>';
            echo '</tr>';
            echo '</tbody>';
        }

        ?>
    </table>

</div>
<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>
