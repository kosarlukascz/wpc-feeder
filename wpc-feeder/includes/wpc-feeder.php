<?php
require_once( 'WPCFeederUtilites.php' );
require_once( 'WPCFeedStatics.php' );

if ( ! class_exists( 'WPCFeeder' ) ) {

	class WPCFeeder {

		private static $instance; //singleton instance
		private $prefix = 'WPCFeeder';
		private $reldir_files_uploads = '/wpc-feeder/';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self;

			}

			return self::$instance;
		}

		public function utilites() {
			return WPCFeederUtilites::get_instance();
		}

		public function helper() {
			return WPCFeederHelper::get_instance();
		}

		public function statics() {
			return WPCFeedStatics::get_instance();
		}

		public function __construct() {

			register_activation_hook( __FILE__, array( $this, 'activation' ) );
			//if defined CLI
			add_action( 'init', array( $this, 'init' ) );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'wpc-feeder', array( $this, 'wpcfeeder_main' ) );
			}

		}

		public function init() {
			if ( isset( $_GET['test'] ) ) {
				echo $this->statics()->size_translate( '3m' );
				exit;
			}
		}

		public function wpcfeeder_main( $args, $assoc_args ) {
			if ( $assoc_args['generate'] !== 'one' && $assoc_args['generate'] !== 'zero' && $assoc_args['generate'] !== 'all' ) {
				WP_CLI::error( 'Please use --generate=one or --generate=zero or --generate=all' );
			}
			$start = microtime( true );
			WP_CLI::success( 'Initiation succesful. Loading products...' );
			$products = $this->load_products( $assoc_args['generate'] );
			$end      = microtime( true );
			WP_CLI::success( 'Products loaded. I found ' . count( $products ) . ' products by given criteria in ' . round( ( $end - $start ), 2 ) . ' seconds.' );
			$this->process_generation( $products, $assoc_args );
		}

		public function process_generation( $products, $assoc_args ) {

			$uploadDir = wp_upload_dir();
			$targetDir = $uploadDir['basedir'] . $this->reldir_files_uploads;

			if ( ! file_exists( $targetDir ) ) {
				wp_mkdir_p( $targetDir );
				WP_CLI::log( 'Directory ' . $targetDir . ' created.' );
			}

			$tempFilePath  = $targetDir . $assoc_args['generate'] . '-temp.xml';
			$finalFilePath = $targetDir . $assoc_args['generate'] . '.xml';

			if ( file_exists( $tempFilePath ) ) {
				unlink( $tempFilePath );
				WP_CLI::log( 'File ' . $tempFilePath . ' deleted.' );
			}
			$xmlWriter = new XMLWriter();
			$xmlWriter->openURI( $tempFilePath );
			$xmlWriter->startDocument( '1.0', 'UTF-8' );
			$xmlWriter->setIndent( true );
			$xmlWriter->startElement( 'rss' ); // Začátek kořenového elementu <rss>
			$xmlWriter->writeAttribute( 'xmlns:g', 'http://base.google.com/ns/1.0' );
			$xmlWriter->writeAttribute( 'version', '2.0' );


			$xmlWriter->startElement( 'channel' );    // Element <channel>

			//make progressbar
			$progress = \WP_CLI\Utils\make_progress_bar( 'Generating XML feed', count( $products ) );
			$usage    = $this->helper()->getSystemUsage();
			WP_CLI::log( 'Využití paměti: ' . $usage['memory_usage_formatted'] );
			WP_CLI::log( 'Využití CPU: ' . $usage['cpu_usage_formatted'] );
			gc_enable();
			foreach ( $products as $product_id ) {

				$product_object = wc_get_product( $product_id );
				if ( ! $this->check_product_consistention( $product_object ) ) {
					continue;
				}


				if ( $product_object->is_type( 'variable' ) ) {
					$this->process_variable_product( $product_object, $xmlWriter, 'variable', $assoc_args );
				} else {
					$this->process_simple_product( $product_object, $xmlWriter, 'simple', $assoc_args );
				}

				$progress->tick();

				$product_object = null;
				$product_id     = null;
				unset( $product_object );
				unset( $product_id );
				gc_collect_cycles();
			}

			$xmlWriter->endElement(); // </channel>
			$xmlWriter->endElement(); // </rss>

			$xmlWriter->endDocument();
			$xmlWriter->flush();

			if ( file_exists( $finalFilePath ) ) {
				unlink( $finalFilePath );
			}
			rename( $tempFilePath, $finalFilePath );
			$progress->finish();
			WP_CLI::success( 'XML feed generated.' );
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

			$main_img = wp_get_attachment_image_src( $product->get_image_id(), 'full' )[0];
			if ( empty( $main_img ) ) {
				return false;
			}

			return true;
		}

		public function process_variable_product( $product, $xmlWriter, $type, $assoc_args ) {
			$parentID = $product->get_id();
			$this->process_variable_product_parent( $product, $parentID, $xmlWriter, 'variable', $assoc_args );
			$this->process_variable_product_variants( $product, $parentID, $xmlWriter, $type, $assoc_args );
		}

		public function process_variable_product_parent( $product, $parentID, $xmlWriter, $type, $assoc_args ) {
			$xmlWriter->startElement( 'item' ); //začatek item


			$xmlWriter->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:description', $this->utilites()->wpc_get_description( $product, $type ) );
			$xmlWriter->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
			$xmlWriter->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
			$xmlWriter->writeElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
			$xmlWriter->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
			$this->helper()->wpcPriceWriter( $xmlWriter, $product, $type );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
			$this->helper()->wpcAddtionalImages( $xmlWriter, $product, $type );
			$xmlWriter->writeElement( 'g:item_group_id', $parentID );


			$xmlWriter->endElement(); //konec item
			$variation = null;
			unset( $variation );
		}

		public function process_variable_product_variants( $product, $parentID, $xmlWriter, $type, $assoc_args ) {
			$variations = $product->get_children( array(
				'posts_per_page' => - 1,
				'numberposts'    => - 1,
			) );
			foreach ( $variations as $variation ) {
				$product = wc_get_product( $variation );
				if ( ! $this->check_product_consistention( $product ) ) {
					continue;
				}

				$xmlWriter->startElement( 'item' ); //začatek item

				$xmlWriter->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
				$this->helper()->wpcWriterCData( $xmlWriter, 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
				$this->helper()->wpcWriterCData( $xmlWriter, 'g:description', $this->utilites()->wpc_get_description( $product, $type ) );
				$xmlWriter->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
				$xmlWriter->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
				$xmlWriter->writeElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
				$xmlWriter->writeElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
				$xmlWriter->writeElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
				$xmlWriter->writeElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
				$xmlWriter->writeElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
				$xmlWriter->writeElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
				$xmlWriter->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
				$this->helper()->wpcPriceWriter( $xmlWriter, $product, $type );
				$this->helper()->wpcWriterCData( $xmlWriter, 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
				$this->helper()->wpcWriterCData( $xmlWriter, 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
				$this->helper()->wpcAddtionalImages( $xmlWriter, $product, $type );
				$this->helper()->wpcGender( $xmlWriter, $product, $type );
				$this->helper()->wpcColor( $xmlWriter, $product, $type );
				$this->helper()->wpcSize( $xmlWriter, $product, $type );
				$xmlWriter->writeElement( 'g:item_group_id', $parentID );


				$xmlWriter->endElement(); //konec item
				$variation = null;
				unset( $variation );
			}
		}

		public function process_simple_product( $product, $xmlWriter, $type, $assoc_args ) {
			$xmlWriter->startElement( 'item' ); //začatek item


			$xmlWriter->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:title', $this->utilites()->wpc_get_name( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:description', $this->utilites()->wpc_get_description( $product, $type ) );
			$xmlWriter->writeElement( 'g:condition', $this->utilites()->wpc_get_condition( $product, $type ) );
			$xmlWriter->writeElement( 'g:brand', $this->utilites()->wpc_get_brand() );
			$xmlWriter->writeElement( 'g:mpn', $this->utilites()->wpc_get_mpn( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_0', $this->utilites()->wpc_get_custom_label_0( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_1', $this->utilites()->wpc_get_custom_label_1( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_2', $this->utilites()->wpc_get_custom_label_2( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_3', $this->utilites()->wpc_get_custom_label_3( $product, $type ) );
			$xmlWriter->writeElement( 'g:custom_label_4', $this->utilites()->wpc_get_custom_label_4( $product, $type ) );
			$xmlWriter->writeElement( 'g:availability', $this->utilites()->wpc_get_availability( $product, $type ) );
			$this->helper()->wpcPriceWriter( $xmlWriter, $product, 'simple' );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
			$this->helper()->wpcWriterCData( $xmlWriter, 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
			$this->helper()->wpcAddtionalImages( $xmlWriter, $product, 'simple' );
			$this->helper()->wpcGender( $xmlWriter, $product, 'simple' );
			$xmlWriter->writeElement( 'g:item_group_id', $this->utilites()->wpc_get_item_group_id( $product, $type ) );


			$xmlWriter->endElement(); //konec item
		}


		public
		function load_products(
			$generate
		) {
			if ( $generate == 'zero' ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'meta_query'     => array(
						'relation' => ' or ',
						array(
							'key'     => 'total_sales',
							'value'   => '',
							'compare' => 'NOT EXISTS'
						),
						array(
							'key'     => 'total_sales',
							'value'   => '0',
							'compare' => ' = '
						)
					),
					'fields'         => 'ids'

				);
			}
			if ( $generate == 'all' ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'fields'         => 'ids'

				);
			}
			if ( $generate == 'one' ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					//meta total sales > 0
					'meta_query'     => array(
						array(
							'key'     => 'total_sales',
							'value'   => '0',
							'compare' => ' > '
						)
					),
					'fields'         => 'ids'
				);
			}


			return get_posts( $args );

		}


		public
		function activation() {
			if ( ! extension_loaded( 'xmlwriter' ) ) {
				exit( 'Please install xmlwriter extension for PHP on your server . ' );
			}
			$this->create_dir();
		}

		public
		function create_dir() {
			$upload_dir = ABSPATH . $this->reldir_files;
			if ( ! file_exists( $upload_dir ) ) {
				mkdir( $upload_dir, 0777, true );
			}
		}
	}
}