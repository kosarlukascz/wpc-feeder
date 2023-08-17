<?php

class WPC_Woocommerce_Hooks {

    public function __construct()
    {
        add_action( 'woocommerce_new_order', [ $this, 'new_order_action' ], 10, 2 );
    }

    public function new_order_action( $order_id, $order ) {
        $order_items = $order->get_items();

        foreach ( $order_items as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            update_post_meta( $product_id, 'wpc-feeder-last-sale', $order->get_date_created()->format( 'Y-m-d H:i:s' ) );
            update_post_meta( $variation_id, 'wpc-feeder-last-sale', $order->get_date_created()->format( 'Y-m-d H:i:s' ) );
        }
    }
}
