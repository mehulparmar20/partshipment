<?php
defined( 'ABSPATH' ) || exit;

if(!$shipment_id){
    return null;
}

$show_image = false;
$show_sku = true;
$image_size = array(32,32);

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );

/* translators: %1$s: Order ID. %2$s: Order date */
echo wp_kses_post( wc_strtoupper( sprintf( esc_html__( '[Order #%1$s] (%2$s)', 'wxp-partial-shipment'), $order->get_order_number(), wc_format_datetime( $order->get_date_created() ) ) ) ) . "\n";
echo "\n";
$items = wphub_partial_shipment()->partial_shipment->get_shipment_items($shipment_id,$order);
if(is_array($items) && !empty($items)){
    foreach ( $items as $itm ) :
        $item          = $order->get_item($itm->item_id);
        if ( apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
            $product       = $item->get_product();
            $sku           = '';
            $purchase_note = '';
            $item_id = $item->get_id();

            if ( is_object( $product ) ) {
                $sku           = $product->get_sku();
                $purchase_note = $product->get_purchase_note();
            }

            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );
            if ( $show_sku && $sku ) {
                echo ' (#' . $sku . ')';
            }
            echo ' X ' . apply_filters( 'woocommerce_email_order_item_quantity', $itm->item_qty, $item );
            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

            // allow other plugins to add additional product information here.
            do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo strip_tags(
                wc_display_item_meta(
                    $item,
                    array(
                        'before'    => "\n- ",
                        'separator' => "\n- ",
                        'after'     => '',
                        'echo'      => false,
                        'autop'     => false,
                    )
                )
            );

            // allow other plugins to add additional product information here.
            do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );
        }
        // Note.
        if ( $show_purchase_note && $purchase_note ) {
            echo "\n" . do_shortcode( wp_kses_post( $purchase_note ) );
        }
        echo "\n\n";
    endforeach;
}
echo "==========\n\n";

$item_totals = $order->get_order_item_totals();

if ( $item_totals ) {
    foreach ( $item_totals as $total ) {
        echo wp_kses_post( $total['label'] . "\t " . $total['value'] ) . "\n";
    }
}

if ( $order->get_customer_note() ) {
    echo esc_html__( 'Note:', 'wxp-partial-shipment') . "\t " . wp_kses_post( wptexturize( $order->get_customer_note() ) ) . "\n";
}

if ( $sent_to_admin ) {
    /* translators: %s: Order link. */
    echo "\n" . sprintf( esc_html__( 'View order: %s', 'wxp-partial-shipment'), esc_url( $order->get_edit_order_url() ) ) . "\n";
}

do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
