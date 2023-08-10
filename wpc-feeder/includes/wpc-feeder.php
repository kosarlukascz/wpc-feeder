<?php
require_once 'WPCFeederUtilites.php';
require_once 'WPCFeedStatics.php';

if ( ! class_exists( 'WPCFeeder' ) ) {

	class WPCFeeder {

		private static $instance; // singleton instance
		private $prefix               = 'WPCFeeder';
		private $reldir_files_uploads = '/wpc-feeder/';
		private $reldir_chunk_folder  = '/wpc-feeder/chunks/';
        private $tempFilePath;
		private $chunk_size  = 100;
        private $chunk_count = 1;
		private $offset      = 0;
        private $probbablyChunkCount;
        private $job_start;
        private $currency;

        /**
         * @var string[][] pipes for child processes
         */
        private $desc = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'file', '/tmp/error-output.txt', 'a' ],
        ];

        /**
         * @var bool checking if class calls from child process
         */
        private $registered = false;

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
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

            if ( ! $this->registered ) {
                register_activation_hook( __FILE__, [ $this, 'activation' ] );
                // if defined CLI
                add_action( 'rest_api_init', [ $this, 'register_routes' ] );

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::add_command( 'wpc-feeder', [ $this, 'wpcfeeder_main' ] );
                    WP_CLI::add_command( 'wpc-feeder-meta', [ $this, 'wpcfeeder_meta' ] );
                }

                $this->registered = true;
            }
		}

		public function register_routes() {
			register_rest_route(
                '/wpcfeeder/v1',
                '/check/(?P<id>\d+)',
                [
					'methods'  => 'GET',
					'callback' => [ $this, 'generate' ],
                ]
            );
		}

		public function generate( $request ) {
			$id         = $request['id'];
			$product    = wc_get_product( $id );
			$assoc_args = [];
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
			$products = get_posts(
                [
					'post_type'      => 'product',
					'post_status'    => 'any',
					'posts_per_page' => - 1,
					'fields'         => 'ids',
				]
            );
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

            register_shutdown_function( [ $this, 'shutdown_action' ] );

            $this->run_command('wp redis disable');
            $this->process_generation( $assoc_args );
		}

        /**
         * @return void command to run on shutting down.
         */
        public function shutdown_action() {
            $this->run_command('wp redis enable');
        }

        /**
         * Executes command
         *
         * @param $command string command to run.
         * @return void
         */
        private function run_command($command) {
            exec($command, $output, $returnCode);
            if ($returnCode !== 0) {
                echo "Error running the command: " . implode("\n", $output);
            } else {
                echo "Command executed successfully." . PHP_EOL;
            }
        }

        public function process_generation( $assoc_args ) {
            wp_suspend_cache_addition( true );
            $uploadDir = wp_upload_dir();
            $targetDir = $uploadDir['basedir'] . $this->reldir_files_uploads;

            if ( ! file_exists( $targetDir ) ) {
                wp_mkdir_p( $targetDir );
                WP_CLI::log( 'Directory ' . $targetDir . ' created.' );
            }

            $tempFilePath       = $targetDir . $assoc_args['generate'] . '-temp.xml';
            $finalFilePath      = $targetDir . $assoc_args['generate'] . '.xml';
            $this->tempFilePath = $tempFilePath;

            if ( file_exists( $tempFilePath ) ) {
                unlink( $tempFilePath );
                WP_CLI::log( 'File ' . $tempFilePath . ' deleted.' );
            }

            $data = $this->write()->startDocument();

            $handle = fopen( $tempFilePath, 'a' );
            fwrite( $handle, $data );
            fclose( $handle );

            $count_for_loaded          = $this->get_count_of_products( $assoc_args['generate'], $assoc_args );
            $chunkSize                 = $this->chunk_size;
            $probbablyChunkCount       = ceil( $count_for_loaded / $this->chunk_size );
            $this->probbablyChunkCount = $probbablyChunkCount;
            $this->currency            = get_option( 'woocommerce_currency' );

            WP_CLI::log( 'Chunk size is set to ' . $chunkSize . ' products.' );
            WP_CLI::success( 'I found ' . $count_for_loaded . ' products. I will generate ' . $probbablyChunkCount . ' chunks.' );

            $this->job_start = microtime( true );

            while ( $this->chunk_count <= $probbablyChunkCount ) {
                $this->run_job( $assoc_args );
                $this->offset += $this->chunk_size;
                $this->chunk_count++;
            }

            $data = $this->write()->endDocument();

            $handle = fopen( $tempFilePath, 'a+' );
            fwrite( $handle, $data );
            fclose( $handle );

            $this->offset = 0;

            if ( file_exists( $finalFilePath ) ) {
                unlink( $finalFilePath );
            }
            rename( $tempFilePath, $finalFilePath );
            wp_suspend_cache_addition( false );

            $this->run_command('wp redis enable');
            WP_CLI::success( 'XML feed generated.' );
        }

        /**
         * Starts process for current chunk.
         *
         * @param $assoc_args
         * @return void
         */
        public function run_job( $assoc_args ) {

            // Executing wpc-process in child process.
            $cmd     = ( 'php wp-content/plugins/wpc-feeder/includes/wpc-process.php' );
            $p       = proc_open( $cmd, $this->desc, $pipes );
            $process = [
                'process' => $p,
                'pipes'   => $pipes,
            ];

            // Data to send in child process.
            $data = [
                'offset'              => $this->offset,
                'chunk_size'          => $this->chunk_size,
                'tempFilePath'        => $this->tempFilePath,
                'probbablyChunkCount' => $this->probbablyChunkCount,
                'chunk_count'         => $this->chunk_count,
                'assoc_args'          => $assoc_args,
                'job_start'           => $this->job_start,
                'currency'            => $this->currency
            ];

            $serializedData = serialize( $data );

            //Sending data.
            fwrite( $pipes[0], $serializedData . PHP_EOL );
            fclose( $pipes[0] );

            $process_running = $this->_monitor_process( $process['process'], $process['pipes'] );
            if ( ! $process_running ) {
                unset( $processes );
                print( "\nProcess finished." );
                print( "\n-------------------\n" );
            }
        }

        /**
         * Prints messages from child process.
         *
         * @param $process
         * @param $pipes
         * @return mixed
         */
        public function _monitor_process( $process, $pipes ) {
            $status = proc_get_status( $process );
            while ( $status['running'] ) {
                foreach ( $pipes as $id => $pipe ) {
                    if ( $id == 0 ) {
                        // Don't read from stdin!
                        continue;
                    }
                    $messages = stream_get_contents( $pipe );
                    if ( ! empty( $messages ) ) {
                        foreach ( explode( "\n", $messages ) as $message ) {
                            $message = trim( $message );
                            if ( ! empty( $message ) ) {
                                print( " -> $message\n" );
                            }
                        }
                    }
                }
                $status = proc_get_status( $process );
            }

            proc_close( $process );
            return $status['running'];
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
            $data    .= $this->process_variable_product_parent( $product, $parentID, 'variable', $assoc_args );
            $data    .= $this->process_variable_product_variants( $product, $parentID, $type, $assoc_args );

            return $data;
		}

		public function process_variable_product_parent( $product, $parentID, $type, $assoc_args ) {

            $data  = '<item>' . PHP_EOL;
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
            $data .= $this->helper()->wpcPriceWriter( $product, $type, $this->write() );
            $data .= $this->write()->writeCdataElement( 'g:link', $this->utilites()->wpc_get_link( $product, $type ) );
            $data .= $this->write()->writeCdataElement( 'g:image_link', $this->utilites()->wpc_get_image_link( $product, $type ) );
            $data .= $this->helper()->wpc_get_additional_images( $product, $type, $this->write() );
            $data .= $this->write()->writeElement( 'g:item_group_id', $parentID );
            $data .= '</item>' . PHP_EOL;

            return $data;
		}

		public function process_variable_product_variants( $product, $parentID, $type, $assoc_args ) {
            $products = $product->get_available_variations( 'objects' );
            $count    = count( $products );
            $data     = '';

            for ( $i = 0; $i < $count; $i++ ) {

                $product = $products[ $i ];
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

                $data     .= '</item>' . PHP_EOL;
                $variation = '';
                $product   = '';
			}

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

        /**
         * Getting full count of products to generate
         *
         * @param $generate
         * @param $assoc_args
         * @return mixed
         */
		public function get_count_of_products( $generate, $assoc_args ) {
            global $wpdb;

            $query = "SELECT COUNT(ID)
              FROM $wpdb->posts
              WHERE post_type = 'product'
              AND post_status = 'publish'";

            if ( $generate == 'zero' ) {
                $query .= " AND ( NOT EXISTS (
                            SELECT MAX(meta_value)
                            FROM $wpdb->postmeta
                            WHERE post_id = $wpdb->posts.ID
                            AND meta_key = 'total_sales'
                        ) OR (
                            SELECT MAX(meta_value)
                            FROM $wpdb->postmeta
                            WHERE post_id = $wpdb->posts.ID
                            AND meta_key = 'total_sales'
                        ) = '0' )";
            } elseif ( $generate == 'one' ) {
                $query .= " AND (
                        SELECT MAX(meta_value)
                        FROM $wpdb->postmeta
                        WHERE post_id = $wpdb->posts.ID
                        AND meta_key = 'total_sales'
                    ) > '0'";
            }

            if ( array_key_exists( 'limit', $assoc_args ) ) {
                $limit  = intval( $assoc_args['limit'] );
                $query .= " LIMIT $limit";

                $prepared_query = $wpdb->prepare( $query, $limit );
            } else {
                $prepared_query = $wpdb->prepare( $query );
            }

            $count = $wpdb->get_var( $prepared_query );

            return $count;
		}

		public function create_dir() {
			$upload_dir = ABSPATH . $this->reldir_files;
			if ( ! file_exists( $upload_dir ) ) {
				mkdir( $upload_dir, 0777, true );
			}
		}
	}
}
