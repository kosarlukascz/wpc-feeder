<?php

if ( ! class_exists( 'WPCFeederUtilites' ) ) {

	class WPCWriter {
		private static $instance; //singleton instance
		private $prefix = 'WPCWriter';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public function startDocument() {
			return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
			       '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL .
			       '<channel>' . PHP_EOL;
		}

		public function endDocument() {
			return '</channel>' . PHP_EOL . '</rss>';
		}

		public function writeElement( $name, $data ) {
			return '<' . $name . '>' . $data . '</' . $name . '>' . PHP_EOL;
		}

		public function writeCdataElement( $name, $data ) {
			return '<' . $name . '><![CDATA[' . $data . ']]></' . $name . '>' . PHP_EOL;
		}
	}

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

			if ( $type == 'variant' ) {
				$explode          = explode( ' - ', $product->get_name() );
				$name             = $explode[0];
				$title_attributes = WPCFeederHelper::get_instance()->clean_string( WPCFeederHelper::get_instance()->process_variant_name( $product ) );
				if ( $title_attributes ) {
					return $name .= ' - ' . $title_attributes;
				} else {
					$attributes = $product->get_attributes();


					return $name .= ' - ' . WPCFeederHelper::get_instance()->clean_string( str_replace( '-', ' ', implode( ',', $attributes ) ) );

				}
			} else {
				return $product->get_name();
			}

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

			$id = ( $type != 'simple' ) ? $product->get_parent_id() : $product->get_id();
			/**
			 * Returning number of sales for wholetime
			 * 0 sale
			 * 1 sale
			 * 2 and more sales
			 */
			$sales = get_post_meta( $id, 'total_sales', true );

			if ( empty( $sales ) ) {
				$sales = '0 sales';
			} elseif ( $sales == 1 ) {
				$sales = '1 sale';
			} else {
				$sales = '2 and more sales';
			}

			return $sales;
		}


		public function wpc_get_custom_label_1( $product, $type ) {
			$id = ( $type != 'simple' ) ? $product->get_parent_id() : $product->get_id();
			/***
			 * Returning number of sales for last 30 days
			 * 0
			 * 1
			 * 2
			 * 3
			 * 5
			 * 6
			 * 7
			 * 8
			 * 9
			 * 10
			 * 10-20
			 * 20-50
			 * 50-100
			 * 100-200
			 * 200-500
			 * 500-1000
			 * 1000+
			 */
			$sales = get_post_meta( $id, 'wpc-feeder-sales-30', true );
			if ( $sales == 0 ) {
				return '0';
			} elseif ( $sales == 1 ) {
				return '1';
			} elseif ( $sales == 2 ) {
				return '2';
			} elseif ( $sales == 3 ) {
				return '3';
			} elseif ( $sales == 5 ) {
				return '5';
			} elseif ( $sales == 6 ) {
				return '6';
			} elseif ( $sales == 7 ) {
				return '7';
			} elseif ( $sales == 8 ) {
				return '8';
			} elseif ( $sales == 9 ) {
				return '9';
			} elseif ( $sales == 10 ) {
				return '10';
			} elseif ( $sales >= 11 && $sales <= 20 ) {
				return '10-20';
			} elseif ( $sales >= 21 && $sales <= 50 ) {
				return '20-50';
			} elseif ( $sales >= 51 && $sales <= 100 ) {
				return '50-100';
			} elseif ( $sales >= 101 && $sales <= 200 ) {
				return '100-200';
			} elseif ( $sales >= 201 && $sales <= 500 ) {
				return '200-500';
			} elseif ( $sales >= 501 && $sales <= 1000 ) {
				return '500-1000';
			} else {
				return '1000+';
			}

		}

		public function wpc_get_custom_label_2( $product, $type ) {

			$id = ( $type != 'simple' ) ? $product->get_parent_id() : $product->get_id();
			/***
			 * Returning number of sales for last 30 days
			 * 0
			 * 1
			 * 2
			 * 3
			 * 5
			 * 6
			 * 7
			 * 8
			 * 9
			 * 10
			 * 10-20
			 * 20-50
			 * 50-100
			 * 100-200
			 * 200-500
			 * 500-1000
			 * 1000+
			 */
			$sales = get_post_meta( $id, 'wpc-feeder-sales-60', true );
			if ( $sales == 0 ) {
				return '0';
			} elseif ( $sales == 1 ) {
				return '1';
			} elseif ( $sales == 2 ) {
				return '2';
			} elseif ( $sales == 3 ) {
				return '3';
			} elseif ( $sales == 5 ) {
				return '5';
			} elseif ( $sales == 6 ) {
				return '6';
			} elseif ( $sales == 7 ) {
				return '7';
			} elseif ( $sales == 8 ) {
				return '8';
			} elseif ( $sales == 9 ) {
				return '9';
			} elseif ( $sales == 10 ) {
				return '10';
			} elseif ( $sales >= 11 && $sales <= 20 ) {
				return '10-20';
			} elseif ( $sales >= 21 && $sales <= 50 ) {
				return '20-50';
			} elseif ( $sales >= 51 && $sales <= 100 ) {
				return '50-100';
			} elseif ( $sales >= 101 && $sales <= 200 ) {
				return '100-200';
			} elseif ( $sales >= 201 && $sales <= 500 ) {
				return '200-500';
			} elseif ( $sales >= 501 && $sales <= 1000 ) {
				return '500-1000';
			} else {
				return '1000+';
			}

		}

		public function wpc_get_custom_label_3( $product, $type ) {

			/**
			 * Returning publishing date of product (mm-yyyy)
			 */

			return $product->get_date_created()->date( 'm-Y' );
		}

		public function wpc_get_custom_label_4( $product, $type ) {
			/**
			 * Returning last date of product sale
			 * <1 day
			 * <2 days
			 * <3 days
			 * <4 days
			 * <5 days
			 * <6 days
			 * <1 week
			 * <2 week
			 * <3 week
			 * <1 month
			 * <2 month
			 * <3 month
			 * <4 month
			 * <5 month
			 * <6 month
			 * <7 month
			 * <8 month
			 * <9 month
			 * <10 month
			 * <11 month
			 * <1 year
			 * <2 year
			 * <3 year
			 * <4 year
			 */
			$id = ( $type != 'simple' ) ? $product->get_parent_id() : $product->get_id();

			return WPCFeederHelper::get_instance()->transform_date_to_label( WPCFeederHelper::get_instance()->get_last_order_date_by_product_id( $id ) );

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
	}

	class WPCWCGetter {
		private static $instance; //singleton instance
		private $prefix = 'WPCWCGetter';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self;
			}

			return self::$instance;
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

		public function clean_string( $title ) {
			$title = str_replace( 'CM', 'cm', $title );
			$title = str_replace( 'MM', 'mm', $title );
			$title = str_replace( '-X-', ' x ', $title );
			$title = str_replace( '-cm', ' cm', $title );
			$title = str_replace( 'cm', ' cm', $title );
			$title = str_replace( 'pcs', 'ks', $title );
			$title = str_replace( 'pc', 'ks', $title );
			$title = str_replace( 'for', 'pro', $title );
			$title = str_replace( 'months', 'měsíců', $title );

			$title = str_replace( '  ', ' ', $title );

			return $this->process_string( $title );

		}

		public function process_string( $input ) {
			$words           = explode( ' ', $input );
			$processed_words = array();
			foreach ( $words as $word ) {
				$original_word = $word;
				if ( strpos( $word, '-' ) !== false ) {
					$temp_words = explode( '-', $word );

					$temp_words_edited = array();
					foreach ( $temp_words as $temp_word ) {
						$temp = str_replace( ',', '', $temp_word );

						$temp                = WPCFeedStatics::get_instance()->color_translate( $temp );
						$temp                = WPCFeedStatics::get_instance()->size_translate( $temp );
						$temp_words_edited[] = $temp;
					}
					$word = implode( '-', $temp_words_edited ) . ',';
				}
				$word              = WPCFeedStatics::get_instance()->color_translate( $word );
				$word              = WPCFeedStatics::get_instance()->size_translate( $word );
				$processed_words[] = $word;
			}
			if ( str_ends_with( $processed_words[ count( $processed_words ) - 1 ], ',' ) ) {
				$processed_words[ count( $processed_words ) - 1 ] = substr( $processed_words[ count( $processed_words ) - 1 ], 0, - 1 );
			}

			return implode( ' ', $processed_words );

		}

		public function transform_date_to_label( $date ) {

			//if date is empty or null
			if ( empty( $date ) || is_null( $date ) ) {
				return 'never sold';
			}
			$today      = date( 'Y-m-d H:i:s' );
			$difference = strtotime( $today ) - strtotime( $date );

			if ( $difference < 60 ) {
				return "<1 minute";
			} elseif ( $difference < 3600 ) {
				$minutes = floor( $difference / 60 );

				return "<$minutes minutes";
			} elseif ( $difference < 86400 ) {
				$hours = floor( $difference / 3600 );

				return "<$hours hours";
			} elseif ( $difference < 604800 ) {
				$days = floor( $difference / 86400 );

				return "<$days days";
			} elseif ( $difference < 2592000 ) {
				$weeks = floor( $difference / 604800 );

				return "<$weeks weeks";
			} elseif ( $difference < 31536000 ) {
				$months = floor( $difference / 2592000 );

				return "<$months months";
			} else {
				$years = floor( $difference / 31536000 );

				return "<$years years";
			}
		}


		public function wpcWriterCdata( $xmlWriter, $name, $data ) {
			$xmlWriter->startElement( $name );
			$xmlWriter->writeCData( $data );
			$xmlWriter->endElement();
		}

		public function get_sales_for_product_id( $id, $days ) {
			global $wpdb;

			$query = $wpdb->prepare( "
        SELECT COUNT(DISTINCT oi.order_item_id) AS pocet_prodeju
        FROM {$wpdb->posts} AS p
        JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim ON oi.order_item_id = oim.order_item_id
        WHERE p.post_type = 'shop_order'
        AND oim.meta_key IN ('_product_id', '_variation_id')
        AND oim.meta_value = %d
        AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
    ", $id, $days );

			$result = $wpdb->get_var( $query );

			return $result;
		}

		public function get_last_order_date_by_product_id( $id ) {
			global $wpdb;

			$query = $wpdb->prepare( "
        SELECT MAX(p.post_date)
        FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
        WHERE p.post_type = 'shop_order'
        AND oi.order_item_type = 'line_item'
        AND oim.meta_key = '_product_id'
        AND oim.meta_value = %d
    ", $id );

			return $wpdb->get_var( $query );
		}


		public function wpcPriceWriter( $product, $type ) {
			$currency_symbol = get_option( 'woocommerce_currency' );
			$data            = '';
			if ( $type == 'simple' ) {
				if ( ! empty( $product->get_sale_price() ) ) {
					$data .= WPCWriter::get_instance()->writeElement( 'g:price', $this->wpc_price_round( $product->get_regular_price() ) . ' ' . $currency_symbol );
					$data .= WPCWriter::get_instance()->writeElement( 'g:sale_price', $this->wpc_price_round( $product->get_sale_price() ) . ' ' . $currency_symbol );
				} else {
					$data .= WPCWriter::get_instance()->writeElement( 'g:price', $this->wpc_price_round( $product->get_regular_price() ) . ' ' . $currency_symbol );
				}
			}

			return $data;
		}

		public function wpc_get_gender( $product, $type ) {
			$gender = '';
			$string = strtolower( $product->get_name() );

			$genderMappings = [
				'pánsk' => 'male',
				'muž'   => 'male',
				'chlap' => 'male',
				'dáms'  => 'female',
				'žens'  => 'female',
				'dívč'  => 'female',
			];

			foreach ( $genderMappings as $pattern => $mappedGender ) {
				if ( strpos( $string, $pattern ) !== false ) {
					$gender = $mappedGender;
					break;
				}
			}

			if ( $gender ) {
				return WPCWriter::get_instance()->writeElement( 'g:gender', $gender );
			}
		}


		public function process_variant_name( $product ) {
			$static     = WPCFeedStatics::get_instance();
			$to_return  = array();
			$attributes = $product->get_attributes();
			//COLOR
			$color_keys = $static->possible_attributes_color();
			foreach ( $attributes as $key => $value ) {
				if ( preg_match( "/(" . $color_keys . ")/", $key ) ) {
					{


						$to_return[] = $static->color_translate( trim( strtolower( $value ) ) );
						unset( $attributes[ $key ] );
					}

				}
			}

			//SIZE
			$size_keys = $static->possible_attributes_sizes();
			foreach ( $attributes as $key => $value ) {
				if ( preg_match( "/(" . $size_keys . ")/", $key ) ) {
					{
						$to_return[] = 'velikost ' . strtoupper( $static->size_translate( trim( strtolower( $value ) ) ) );
						unset( $attributes[ $key ] );

					}

				}
			}
			if ( ! empty( $to_return ) ) {
				//if is more than 1 element in array
				$all = array_merge( $to_return, $attributes );

				return implode( ', ', $all );

			}

			return implode( ', ', $attributes );
		}

		public function wpc_get_color( $product, $type ) {
			$color_keys = WPCFeedStatics::get_instance()->possible_attributes_color();
			$attributes = $product->get_attributes();
			foreach ( $attributes as $key => $value ) {
				if ( preg_match( "/(" . $color_keys . ")/", $key ) ) {
					{
						return WPCWriter::get_instance()->writeElement( 'g:color', WPCFeedStatics::get_instance()->color_translate( trim( strtolower( $value ) ) ) );
					}

				}
			}

			return false;
		}

		public function wpc_get_size( $product, $type ) {
			$size_keys  = WPCFeedStatics::get_instance()->possible_attributes_sizes();
			$attributes = $product->get_attributes();
			foreach ( $attributes as $key => $value ) {
				if ( preg_match( "/(" . $size_keys . ")/", $key ) ) {
					{
						return WPCWriter::get_instance()->writeElement( 'g:size', strtoupper( WPCFeedStatics::get_instance()->size_translate( trim( strtolower( $value ) ) ) ) );
					}

				}
			}

			return false;
		}

		public function wpc_get_additional_images( $product, $type ) {
			$limit  = 1;
			$images = $product->get_gallery_image_ids();
			$data   = '';
			if ( $images ) {
				foreach ( $images as $image ) {
					if ( $limit >= 10 ) {
						continue;
					}
					$img_link = wp_get_attachment_image_src( $image, 'full' )[0];
					if ( $img_link ) {
						$data .= WPCWriter::get_instance()->writeCdataElement( 'g:additional_image_link', ( $img_link ) );
					}
					$limit ++;
				}
			}
			if ( $data ) {
				return $data;
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
