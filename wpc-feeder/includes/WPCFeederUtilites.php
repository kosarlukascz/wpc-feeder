<?php

if ( ! class_exists( 'WPCFeederUtilites' ) ) {

	class WPCFeederUtilites {
		private static $instance; //singleton instance
		private $prefix = 'WPCFeederUtilites';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public function wpc_get_name( $product, $type ) {
			$name = $product->get_name();

			return $name;
		}

		public function wpc_get_description( $product, $type ) {
			$desc = $product->get_description();
			$desc = strip_tags( html_entity_decode( $desc ) );

			return $desc;
		}

		public function wpc_get_condition( $product, $type ) {
			return 'new';
		}

		public function wpc_get_mpn( $product, $type ) {
			$mpn = $product->get_id();

			return $mpn;
		}

		public function wpc_get_custom_label_0( $product, $type ) {
			/*TODO: add custom label data*/
			return '';
		}

		public function wpc_get_custom_label_1( $product, $type ) {
			/*TODO: add custom label data*/
			return '';
		}

		public function wpc_get_custom_label_2( $product, $type ) {
			/*TODO: add custom label data*/
			return '';
		}

		public function wpc_get_custom_label_3( $product, $type ) {
			/*TODO: add custom label data*/
			return '';
		}

		public function wpc_get_custom_label_4( $product, $type ) {
			/*TODO: add custom label data*/
			return '';
		}

		public function wpc_get_gender( $product, $type ) {
			/*TODO: add gender*/
			return '';
		}

		public function wpc_get_availability( $product, $type ) {
			return 'in stock';
		}

		public function wpc_get_item_group_id( $product, $type ) {
			if ( $type == 'simple' ) {
				return $product->get_id();
			} else {
				return $product->get_parent_id();
			}
		}

		public function wpc_get_brand() {
			$brand = get_bloginfo( 'name' );

			return ucfirst( $brand );
		}

		public function wpc_get_id( $product, $type ) {
			$id = $product->get_id();

			return $id;
		}

		public function wpc_get_link( $product, $type ) {
			return $product->get_permalink();
		}

		public function wpc_get_image_link( $product, $type ) {
			$main_img = wp_get_attachment_image_src( $product->get_image_id(), 'full' )[0];

			return $main_img;
		}

		public function wpc_get_price( $product, $type ) {
			if ( ! empty( $product->get_sale_price() ) ) {
				$xml .= '<g:price>' . $this->dsb_round( $product->get_regular_price() ) . ' ' . $currency_symbol . '</g:price>' . PHP_EOL;
				$xml .= '<g:sale_price>' . $this->dsb_round( $product->get_sale_price() ) . ' ' . $currency_symbol . '</g:sale_price>' . PHP_EOL;
			} else {
				$xml .= '<g:price>' . $this->dsb_round( $product->get_price() ) . ' ' . $currency_symbol . '</g:price>' . PHP_EOL;
			}
		}
	}

	class WPCFeederHelper {
		private static $instance; //singleton instance
		private $prefix = 'WPCFeederHelper';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public function wpcWriterCdata( $xmlWriter, $name, $data ) {
			$xmlWriter->startElement( $name );
			$xmlWriter->writeCData( $data );
			$xmlWriter->endElement();
		}

		public function wpcPriceWriter( $xmlWriter, $product, $type ) {
			$currency_symbol = get_option( 'woocommerce_currency' );

			if ( $type == 'simple' ) {
				if ( ! empty( $product->get_sale_price() ) ) {
					$xmlWriter->writeElement( 'g:price', $this->wpc_price_round( $product->get_regular_price() ) . ' ' . $currency_symbol );
					$xmlWriter->writeElement( 'g:sale_price', $this->wpc_price_round( $product->get_sale_price() ) . ' ' . $currency_symbol );
				} else {
					$xmlWriter->writeElement( 'g:price', $this->wpc_price_round( $product->get_regular_price() ) . ' ' . $currency_symbol );
				}
			}
		}

		public function wpcGender( $xmlWriter, $product, $type ) {
			$gender = '';
			$string = strtolower( $product->get_name() );
			//male, female, unisex
			if ( strpos( $string, 'pánsk' ) !== false ) {
				$gender = 'male';
			} elseif ( strpos( $string, 'muž' ) !== false ) {
				$gender = 'male';
			} elseif ( strpos( $string, 'chlap' ) !== false ) {
				$gender = 'male';
			} elseif ( strpos( $string, 'dáms' ) !== false ) {
				$gender = 'female';
			} elseif ( strpos( $string, 'žens' ) !== false ) {
				$gender = 'female';
			} elseif ( strpos( $string, 'dívč' ) !== false ) {
				$gender = 'female';
			} else {
				$gender = 'unisex';
			}
			if ( $gender ) {
				$xmlWriter->writeElement( 'g:gender', $gender );
			}
		}

		public function wpcColor( $xmlWriter, $product, $type ) {
			/*TODO: do */
			return '';
		}

		public function wpcSize( $xmlWriter, $product, $type ) {
			/*TODO: do */
			return '';
		}

		public function wpcAddtionalImages( $xmlWriter, $product, $type ) {
			$limit  = 1;
			$images = $product->get_gallery_image_ids();
			if ( $images ) {
				foreach ( $images as $image ) {
					if ( $limit >= 10 ) {
						continue;
					}
					$img_link = wp_get_attachment_image_src( $image, 'full' )[0];
					if ( $img_link ) {
						$this->wpcWriterCdata( $xmlWriter, 'g:additional_image_link', ( $img_link ) );
					}
					$limit ++;
				}
			}
		}

		public function wpc_price_round( $price ) {
			if ( get_locale() == 'cs_CZ' ) {
				return round( $price );
			} else {
				return round( $price, 2 );
			}
		}

		public function getSystemUsage() {
			$memoryUsage          = memory_get_usage();
			$memoryUsageFormatted = $this->formatBytes( $memoryUsage );

			$cpuUsage          = sys_getloadavg()[0];
			$cpuUsageFormatted = round( $cpuUsage, 2 ) . '%';

			return [
				'memory_usage'           => $memoryUsage,
				'memory_usage_formatted' => $memoryUsageFormatted,
				'cpu_usage'              => $cpuUsage,
				'cpu_usage_formatted'    => $cpuUsageFormatted
			];
		}

		public function formatBytes( $bytes, $precision = 2 ) {
			$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

			$bytes = max( $bytes, 0 );
			$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow   = min( $pow, count( $units ) - 1 );

			$bytes /= ( 1 << ( 10 * $pow ) );

			return round( $bytes, $precision ) . ' ' . $units[ $pow ];
		}
	}
}