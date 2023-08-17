<?php
require_once 'wpc-feeder.php';

// Require wordpress env in process.
$wordpress_path = dirname( __FILE__ ) . '/../../../../wp-load.php';
require_once $wordpress_path;

class WPC_Process {

    private $offset;
    private $chunk_size;
    private $temp_file_path;
    private $probbably_chunk_count;
    private $chunk_count;
    private $job_start;
	private $var_count = 0;
    private $currency;

    /**
     * Defines vars from parent and start process.
     *
     * @param $arguments
     */
    public function __construct( $arguments ) {
        $this->offset                = $arguments['offset'];
        $this->chunk_size            = $arguments['chunk_size'];
        $this->temp_file_path        = $arguments['tempFilePath'];
        $this->probbably_chunk_count = $arguments['probbablyChunkCount'];
        $this->chunk_count           = $arguments['chunk_count'];
        $this->job_start             = $arguments['job_start'];
        $this->currency              = $arguments['currency'];
        $this->generate_chunk( $arguments['assoc_args'] );
    }

    public function write() {
        return WPCWriter::get_instance();
    }

    public function utilites() {
        return WPCFeederUtilites::get_instance();
    }

    public function helper() {
        return WPCFeederHelper::get_instance();
    }

    /**
     * Chunk generation.
     *
     * @param $assoc_args
     * @return void
     */
    private function generate_chunk( $assoc_args ) {
        $probbably_chunk_count = $this->probbably_chunk_count;

        $products = $this->load_products( $assoc_args );
        echo 'Chunk: ' . $this->chunk_count . ' / ' . $probbably_chunk_count . PHP_EOL;

        if ( empty( $products ) ) {
            return;
        }

        $start = microtime( true );

        $this->generate_products( $products );

        unset( $products );

        // end messuring time of foreach loop
        $end             = microtime( true );
        $full_time       = $end - $this->job_start;
        $average_time    = $full_time / $this->chunk_count;
        $expected_finish = ( $probbably_chunk_count - $this->chunk_count ) * $average_time;

        echo 'Chunk generated in ' . round( ( $end - $start ), 2 ) . ' seconds.' . PHP_EOL;
        echo 'Average time: ' . round( ( $average_time ), 2 ) . ' seconds. / Full time: ' . round( ( $full_time / 60 ), 0 ) . ' min / Expected time to finish: ' . round( ( $expected_finish / 60 ), 0 ) . ' min' . PHP_EOL;
        set_transient('wpc_feeder_expected_finish',round( ( $expected_finish / 60 ), 0 ), 3600);

        $usage = $this->helper()->getSystemUsage();
        echo 'Využití paměti: ' . $usage['memory_usage_formatted'] . PHP_EOL;
        echo 'Využití CPU: ' . $usage['cpu_usage_formatted'] . PHP_EOL;
	    echo 'Variations num: ' . $this->var_count . PHP_EOL;
        wp_reset_postdata();
    }

    private function generate_products( $products ) {
        $list_of_ids = array_column( $products, 'ID' );
        $wc_products = wc_get_products(
            [
				'include' => $list_of_ids,
				'limit'   => -1,
			]
        );
        unset( $products );

        foreach ( $wc_products as $product ) {
            if ( ! $this->check_product_consistention( $product ) ) {
                continue;
            }

            if ( $product->is_type( 'variable' ) ) {
                $this->process_variable_product( $product );
            } else {
                $this->process_simple_product( $product, 'simple' );
            }

            unset( $product );
        }
    }

    public function check_product_consistention( $product ) {
        if ( is_null( $product ) ) {
            return false;
        }

        if ( ! is_bool( $product ) ) {
            if ( empty( $product->get_price() ) ) {
                return false;
            }
        } else {
            return false;
        }

//        $main_img = wp_get_attachment_image_src( $product->get_image_id(), 'full' )[0];
//        if ( empty( $main_img ) ) {
//            return false;
//        }

        if ( empty( $product->get_image_id() ) ) {
            return false;
        }

        return true;
    }

    public function process_variable_product( $product ) {
        $parentID = $product->get_id();
        $this->process_variable_product_parent( $product, $parentID, 'variable' );
        $this->process_variable_product_variants( $product, $parentID, 'variant' );
    }

    public function process_variable_product_parent( $product, $parentID, $type ) {

        $this->utilites()->set_product( $product, $type );

        $data = '<item>' . PHP_EOL;

        $data .= $this->write()->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:description', $this->utilites()->wpc_get_description( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
        $data .= $this->write()->writeCdataElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
        $data .= $this->helper()->wpcPriceWriter( $product, $type, $this->write(), $this->currency );
        $data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
        $data .= $this->helper()->wpc_get_additional_images( $product, $type, $this->write() );
        $data .= $this->write()->writeElement( 'g:item_group_id', $parentID );
        $data .= '</item>' . PHP_EOL;

        $handle = fopen( $this->temp_file_path, 'a' );
        fwrite( $handle, $data );
        fclose( $handle );
    }

    public function process_variable_product_variants( $parent_product, $parentID, $type ) {
        $products = $parent_product->get_available_variations( 'objects' );
        $count    = count( $products );
	    $this->var_count += $count;

        $data = '';

        for ( $i = 0; $i < $count; $i++ ) {

            $product = $products[ $i ];
            if ( ! $this->check_product_consistention( $product ) ) {
                continue;
            }
            $this->utilites()->set_product( $product, $type );

            $data .= '<item>' . PHP_EOL;

            $data .= $this->write()->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:description', $this->utilites()->wpc_get_description( $parent_product, $type ) );
            $data .= $this->write()->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
            $data .= $this->write()->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
            $data .= $this->write()->writeCdataElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
            $data .= $this->write()->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
            $data .= $this->helper()->wpcPriceWriter( $product, $type, $this->write(), $this->currency );
            $data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
            $data .= $this->helper()->wpc_get_additional_images( $product, $type, $this->write() );
            $data .= $this->helper()->wpc_get_gender( $product, $type, $this->write() );
            $data .= ( $color = $this->helper()->wpc_get_color( $product, $type, $this->write() ) ) !== false ? $color : '';
            $data .= ( $size = $this->helper()->wpc_get_size( $product, $type, $this->write() ) ) !== false ? $size : '';
            $data .= $this->write()->writeElement( 'g:item_group_id', $parentID );

            $data .= '</item>' . PHP_EOL;
        }

        $handle = fopen( $this->temp_file_path, 'a' );
        fwrite( $handle, $data );
        fclose( $handle );
    }

    public function process_simple_product( $product, $type ) {
        $this->utilites()->set_product( $product, $type );

        $data = '<item>' . PHP_EOL;

        $data .= $this->write()->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:description', $this->utilites()->wpc_get_description( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
        $data .= $this->write()->writeCdataElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
        $data .= $this->write()->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
        $data .= $this->helper()->wpcPriceWriter( $product, 'simple', $this->write(), $this->currency );
        $data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
        $data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
        $data .= $this->helper()->wpc_get_additional_images( $product, 'simple', $this->write() );
        $data .= $this->helper()->wpc_get_gender( $product, 'simple', $this->write() );
        $data .= $this->write()->writeElement( 'g:item_group_id', $this->utilites()->wpc_get_item_group_id( $product, $type ) );
        $data .= '</item>' . PHP_EOL;

        $handle = fopen( $this->temp_file_path, 'a' );
        fwrite( $handle, $data );
        fclose( $handle );
    }

    /**
     * loding product IDs for generating from database.
     *
     * @param array $assoc_argss arguments.
     * @return array|object|stdClass[]|null
     */
    public function load_products( $assoc_args ) {
        global $wpdb;

        $limit = array_key_exists( 'limit', $assoc_args ) ? $assoc_args['limit'] : $this->chunk_size;

        $query = "SELECT ID
              FROM $wpdb->posts
              WHERE post_type = 'product'
              AND post_status = 'publish'";

        if ( $assoc_args['generate'] == 'zero' ) {
            $query .= " AND ( NOT EXISTS (
                            SELECT meta_value
                            FROM $wpdb->postmeta
                            WHERE post_id = $wpdb->posts.ID
                            AND meta_key = 'total_sales'
                        ) OR (
                            SELECT meta_value
                            FROM $wpdb->postmeta
                            WHERE post_id = $wpdb->posts.ID
                            AND meta_key = 'total_sales'
                        ) = '0' )";
        } elseif ( $assoc_args['generate'] == 'one' ) {
            $query .= " AND (
                        SELECT meta_value
                        FROM $wpdb->postmeta
                        WHERE post_id = $wpdb->posts.ID
                        AND meta_key = 'total_sales'
                    ) > '0'";
        }

        $query .= " LIMIT $limit OFFSET $this->offset";

        $prepared_query = $wpdb->prepare( $query );

        $results = $wpdb->get_results( $prepared_query );

        return $results;
    }

}

// Getting data from parent process.
$serializedArguments = '';
while ( ( $line = fgets( STDIN ) ) !== false ) {
    $serializedArguments .= $line;
}

// Deserialize the arguments (convert back to array)
$arguments = unserialize( $serializedArguments );

// Calls current process class with data from parent.
new WPC_Process( $arguments );
