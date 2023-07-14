<?php
require_once( 'WPCFeederUtilites.php' );
require_once( 'WPCFeedStatics.php' );

if ( ! class_exists( 'WPCFeeder' ) ) {

	class WPCFeeder {

		private static $instance; //singleton instance
		private $prefix = 'WPCFeeder';
		private $reldir_files_uploads = '/wpc-feeder/';
		private $reldir_chunk_folder = '/wpc-feeder/chunks/';
		private $chunk_size = 50;
		private $offset = 0;

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

		public function write() {
			return WPCWriter::get_instance();
		}

		public function __construct() {

			register_activation_hook( __FILE__, array( $this, 'activation' ) );
			//if defined CLI
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'wpc-feeder', array( $this, 'wpcfeeder_main' ) );
				WP_CLI::add_command( 'wpc-feeder-meta', array( $this, 'wpcfeeder_meta' ) );

			}

		}

		public function register_routes() {
			register_rest_route( '/wpcfeeder/v1', '/check/(?P<id>\d+)', array(
				'methods'  => 'GET',
				'callback' => array( $this, 'generate' ),
			) );
		}

		public function generate( $request ) {
			$id         = $request['id'];
			$product    = wc_get_product( $id );
			$assoc_args = array();
			ob_start();
			$data = $this->write()->startDocument();
			if ( $product->is_type( 'variable' ) ) {
				$data .= $this->process_variable_product( $product, 'variable', $assoc_args );
			} else {
				$data .= $this->process_simple_product( $product, 'simple', $assoc_args );
			}
			$data .= $this->write()->endDocument();
			ob_end_clean();
			echo $data;
			exit;
		}

		public function wpcfeeder_meta( $args, $assoc_args ) {
			WP_CLI::log( 'Starting to recalculate meta sales' );
			$products = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => - 1,
				'fields'         => 'ids',
			) );
			WP_CLI::log( 'Found ' . count( $products ) . ' products' );
			$progress = \WP_CLI\Utils\make_progress_bar( 'Recalculating sales ', count( $products ) );
			foreach ( $products as $product_id ) {

				if ( $assoc_args['force'] !== 'true' ) {
					if ( get_post_meta( $product_id, 'wpc-feeder-sales-30', true ) !== '' ) {
						$progress->tick();
						continue;
					}
				}

				update_post_meta( $product_id, 'wpc-feeder-sales-30', WPCFeederHelper::get_instance()->get_sales_for_product_id( $product_id, 30 ) );
				update_post_meta( $product_id, 'wpc-feeder-sales-60', WPCFeederHelper::get_instance()->get_sales_for_product_id( $product_id, 60 ) );
				$progress->tick();
			}
			$progress->finish();
			WP_CLI::success( 'Recalculating sales finished' );
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
			$this->process_generation( $assoc_args );
		}

		public function process_generation( $assoc_args ) {
			wp_suspend_cache_addition( true );

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
			$this->save_data_to_file( $tempFilePath, $this->write()->startDocument(), 'w' );
			$count_for_loaded    = $this->get_count_of_products( $assoc_args['generate'], $assoc_args );
			$offset              = $this->offset;
			$chunkSize           = $this->chunk_size;
			$chunkCount          = 1;
			$probbablyChunkCount = ceil( $count_for_loaded / $chunkSize );
			WP_CLI::log( 'Chunk size is set to ' . $chunkSize . ' products.' );
			WP_CLI::success( 'I found ' . $count_for_loaded . ' products. I will generate ' . $probbablyChunkCount . ' chunks.' );

			do {
				$products = $this->load_products( $assoc_args['generate'], $offset, $chunkSize, $assoc_args );
				$progress = \WP_CLI\Utils\make_progress_bar( 'Generating XML feed ', count( $products ) );
				WP_CLI::log( 'Chunk: ' . $chunkCount . ' / ' . $probbablyChunkCount );

				if ( empty( $products ) ) {
					break;
				}

				$data = '';
				//start messuring time of foreach loop
				$start = microtime( true );

				foreach ( $products as $product_id ) {

					$product = wc_get_product( $product_id );
					if ( ! $this->check_product_consistention( $product ) ) {
						continue;
					}


					if ( $product->is_type( 'variable' ) ) {
						$data .= $this->process_variable_product( $product, 'variable', $assoc_args );
					} else {
						$data .= $this->process_simple_product( $product, 'simple', $assoc_args );
					}

					$progress->tick();

					$product = '';
				}
				//end messuring time of foreach loop
				$end = microtime( true );
				WP_CLI::log( 'Chunk generated in ' . round( ( $end - $start ), 2 ) . ' seconds.' );
				$this->save_data_to_file( $tempFilePath, $data );
				$data = '';


				$products = '';
				$progress->finish();
				$offset += $chunkSize;
				$chunkCount ++;

				$usage = $this->helper()->getSystemUsage();
				wp_reset_postdata();
				WP_CLI::log( 'Využití paměti: ' . $usage['memory_usage_formatted'] );
				WP_CLI::log( 'Využití CPU: ' . $usage['cpu_usage_formatted'] );
			} while ( $chunkCount <= $probbablyChunkCount );


			$this->save_data_to_file( $tempFilePath, $this->write()->endDocument() );

			if ( file_exists( $finalFilePath ) ) {
				unlink( $finalFilePath );
			}
			rename( $tempFilePath, $finalFilePath );
			wp_suspend_cache_addition( false );

			WP_CLI::success( 'XML feed generated.' );
		}


		public function save_data_to_file( $file, $data, $mode = 'a' ) {
			if ( $handle = fopen( $file, $mode ) ) {
				// Přidej XML do souboru
				fwrite( $handle, $data );
				// Uzavři soubor
				fclose( $handle );
				WP_CLI::log( 'Data byla zapsána do XML' );
			} else {
				WP_CLI::error( 'Nepodařilo se otevřít soubor XML: ' . $file );
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

			$main_img = wp_get_attachment_image_src( $product->get_image_id(), 'full' )[0];
			if ( empty( $main_img ) ) {
				return false;
			}

			return true;
		}

		public function process_variable_product( $product, $type, $assoc_args ) {
			$data     = '';
			$parentID = $product->get_id();
			$data     .= $this->process_variable_product_parent( $product, $parentID, 'variable', $assoc_args );
			$data     .= $this->process_variable_product_variants( $product, $parentID, $type, $assoc_args );

			return $data;
		}

		public function process_variable_product_parent( $product, $parentID, $type, $assoc_args ) {
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
			$data .= $this->helper()->wpcPriceWriter( $product, $type );
			$data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
			$data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
			$data .= $this->helper()->wpc_get_additional_images( $product, $type );
			$data .= $this->write()->writeElement( 'g:item_group_id', $parentID );


			$data      .= '</item>' . PHP_EOL;
			$variation = '';
			$product   = '';

			return $data;
		}

		public function process_variable_product_variants( $product, $parentID, $type, $assoc_args ) {
			$data       = '';
			$variations = $product->get_children( array(
				'posts_per_page' => - 1,
				'numberposts'    => - 1,
			) );
			foreach ( $variations as $variation ) {
				$product = wc_get_product( $variation );
				if ( ! $this->check_product_consistention( $product ) ) {
					continue;
				}

				$data .= '<item>' . PHP_EOL;

				$data .= $this->write()->writeElement( 'g:id', $this->utilites()->wpc_get_id( $product, $type ) );
				$data .= $this->write()->writeCdataElement( 'g:title', $this->utilites()->wpc_get_name( $product, 'variant' ) );
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
				$data .= $this->helper()->wpcPriceWriter( $product, $type );
				$data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
				$data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
				$data .= $this->helper()->wpc_get_additional_images( $product, $type );
				$data .= $this->helper()->wpc_get_gender( $product, $type );
				$data .= ( $color = $this->helper()->wpc_get_color( $product, $type ) ) !== false ? $color : '';
				$data .= ( $size = $this->helper()->wpc_get_size( $product, $type ) ) !== false ? $size : '';
				$data .= $this->write()->writeElement( 'g:item_group_id', $parentID );


				$data      .= '</item>' . PHP_EOL;
				$variation = '';
				$product   = '';
			}
			$variations = '';

			return $data;
		}

		public function process_simple_product( $product, $type, $assoc_args ) {
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
			$data .= $this->helper()->wpcPriceWriter( $product, 'simple' );
			$data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
			$data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
			$data .= $this->helper()->wpc_get_additional_images( $product, 'simple' );
			$data .= $this->helper()->wpc_get_gender( $product, 'simple' );
			$data .= $this->write()->writeElement( 'g:item_group_id', $this->utilites()->wpc_get_item_group_id( $product, $type ) );


			$data .= '</item>' . PHP_EOL;

			return $data;
		}


		public function load_products( $generate, $offset, $limit, $assoc_argss ) {

			if ( $assoc_argss['limit'] ) {
				$limit = $assoc_argss['limit'];
			}


			$args = array(
				'post_type'      => 'product',
				'offset'         => $offset,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'fields'         => 'ids'
			);

			if ( $generate == 'zero' ) {
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => 'total_sales',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'     => 'total_sales',
						'value'   => '0',
						'compare' => '='
					)
				);
			} elseif ( $generate == 'one' ) {
				$args['meta_query'] = array(
					array(
						'key'     => 'total_sales',
						'value'   => '0',
						'compare' => '>'
					)
				);
			}

			return get_posts( $args );
		}

		public function get_count_of_products( $generate, $assoc_args ) {

			if ( $assoc_args['limit'] ) {
				$limit = $assoc_args['limit'];
			} else {
				$limit = '-1';
			}

			$args = array(
				'post_type'      => 'product',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'fields'         => 'ids'
			);

			if ( $generate == 'zero' ) {
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => 'total_sales',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'     => 'total_sales',
						'value'   => '0',
						'compare' => '='
					)
				);
			} elseif ( $generate == 'one' ) {
				$args['meta_query'] = array(
					array(
						'key'     => 'total_sales',
						'value'   => '0',
						'compare' => '>'
					)
				);
			}

			return count( get_posts( $args ) );
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
