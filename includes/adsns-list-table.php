<?php
if ( ! class_exists( 'Adsns_List_Table' ) ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	class Adsns_List_Table extends WP_List_Table {

		public $adsns_table_data;
		public $adsns_table_adunits;
		public $adsns_table_area;
		public $adsns_adunit_positions;
		public $adsns_adunit_positions_pro;
		public $adsns_vi_publisher_id;
		public $adsns_vi_token;
		private $adsns_options;
		private $item_counter;

		public function __construct( $options ) {
			$this->adsns_options = $options;
			$this->item_counter  = 0;
			parent::__construct(
				array(
					'singular' => __( 'item', 'bws-adsense-plugin' ),
					'plural'   => __( 'items', 'bws-adsense-plugin' ),
					'ajax'     => false,
				)
			);
		}

		public function get_table_classes() {
			return array( 'adsns-list-table', 'widefat', 'fixed', 'striped', $this->_args['plural'] );
		}

		public function get_columns() {
			$columns = array(
				'cb'       => __( 'Display', 'bws-adsense-plugin' ),
				'name'     => __( 'Name', 'bws-adsense-plugin' ),
				'code'     => __( 'Id', 'bws-adsense-plugin' ),
				'summary'  => __( 'Type / Size', 'bws-adsense-plugin' ),
				'status'   => __( 'Status', 'bws-adsense-plugin' ),
				'position' => __( 'Position', 'bws-adsense-plugin' ),
			);
			if ( ! $this->adsns_adunit_positions ) {
				unset( $columns['position'] );
			}
			return $columns;
		}

		public function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'name';
			$order   = ( ! empty( $_GET['order'] ) ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
			$result  = strcasecmp( $a[ $orderby ], $b[ $orderby ] );
			return ( 'asc' === $order ) ? $result : -$result;
		}

		public function get_sortable_columns() {
			$sortable_columns = array(
				'name'    => array( 'name', false ),
				'code'    => array( 'code', false ),
				'summary' => array( 'summary', false ),
				'status'  => array( 'status', false ),
			);
			return $sortable_columns;
		}

		public function prepare_items() {
			$columns               = $this->get_columns();
			$hidden                = array();
			$sortable              = $this->get_sortable_columns();
			$primary               = 'name';
			$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

			$vi_story_tbl_data = null;
			if ( array_key_exists( 'vi_story', $this->adsns_table_data ) ) {
				$vi_story_tbl_data = $this->adsns_table_data['vi_story'];
				unset( $this->adsns_table_data['vi_story'] );
			}

			usort( $this->adsns_table_data, array( &$this, 'usort_reorder' ) );

			if ( $vi_story_tbl_data && $this->adsns_vi_token ) {
				array_unshift( $this->adsns_table_data, $vi_story_tbl_data );
			}

			$this->items = $this->adsns_table_data;
		}

		/**
		 * Add necessary css classes depending on item status
		 *
		 * @param     array $item        The current item data.
		 * @return    void
		 */
		public function single_row( $item ) {
			$row_class  = 'adsns_table_row';
			$row_class .= isset( $item['status_value'] ) && 'INACTIVE' === $item['status_value'] ? ' adsns_inactive' : '';
			if ( '1' !== $this->adsns_options['include_inactive_ads'] ) {
				if ( isset( $item['status_value'] ) && 'INACTIVE' !== $item['status_value'] ) {
					if ( 0 === $this->item_counter % 2 ) {
						$row_class .= ( '' !== $row_class ) ? ' adsns_table_row_odd' : '';
					}
					$this->item_counter++;
				} elseif ( isset( $item['status_value'] ) && 'INACTIVE' === $item['status_value'] ) {
					$row_class .= ( '' !== $row_class ) ? ' hidden' : '';
				}
			} else {
				if ( 0 === $this->item_counter % 2 ) {
					$row_class .= ( '' !== $row_class ) ? ' adsns_table_row_odd' : '';
				}
				$this->item_counter++;
			}

			$row_class = ( '' !== $row_class ) ? ' class="' . $row_class . '"' : '';

			echo wp_kses_post( "<tr{$row_class}>" );
				$this->single_row_columns( $item );
			echo wp_kses_post( '</tr>' );
		}

		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'cb':
				case 'name':
				case 'code':
				case 'summary':
				case 'status':
				case 'position':
					return $item[ $column_name ];
				default:
					return print_r( $item, true );
			}
		}

		public function column_cb( $item ) {
			if ( 'DISPLAY' === $item['type'] ) {
				if ( 'vi_story' !== $item['id'] ) {
						return sprintf( '<input class="adsns_adunit_ids" type="checkbox" name="adsns_adunit_ids[]" value="%s" %s/>', $item['id'], checked( array_key_exists( $item['id'], $this->adsns_table_adunits ), true, false ) );
				} else {
						return sprintf( '<input class="adsns_adunit_ids" type="checkbox" name="adsns_vi_id" value="%s" %s/>', $item['id'], checked( ( isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ) && true === $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ), true, false ) );
				}
			}
		}

		public function column_position( $item ) {
			if ( 'DISPLAY' === $item['type'] && 'Archived' !== $item['status'] ) {
				$adsns_adunit_positions = is_array( $this->adsns_adunit_positions ) ? $this->adsns_adunit_positions : array();

				if ( 'vi_story' !== $item['id'] ) {
					$disabled = ( ! array_key_exists( $item['id'], $this->adsns_table_adunits ) ) ? ' disabled="disabled"' : '';

					$adsns_adunit_positions_pro = is_array( $this->adsns_adunit_positions_pro ) ? $this->adsns_adunit_positions_pro : array();
					$adsns_position             = $adsns_position_pro = '';
					foreach ( $adsns_adunit_positions as $value => $name ) {
						$adsns_position .= sprintf( '<option value="%s" %s>%s</option>', $value, ( array_key_exists( $item['id'], $this->adsns_table_adunits ) && $this->adsns_table_adunits[ $item['id'] ] === $value ) ? 'selected="selected"' : '', $name );
					}
					if ( $adsns_adunit_positions_pro ) {
						foreach ( $adsns_adunit_positions_pro as $value_pro => $name_pro ) {
							$adsns_position_pro .= sprintf( '<optgroup label="%s"></optgroup>', $name_pro );
						}
						$adsns_position .= $adsns_position_pro;
					}
					return sprintf(
						'<select class="adsns_adunit_position" name="adsns_adunit_position[%s]" %s>%s</select>',
						$item['id'],
						$disabled,
						$adsns_position
					);
				} else {
					$disabled          = ( ! ( isset( $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ) && treu === $this->adsns_options['vi_story'][ $this->adsns_vi_publisher_id ]['display'][ $this->adsns_table_area ] ) ) ? 'disabled="disabled"' : '';
					$vi_story_position = '';
					foreach ( $adsns_adunit_positions as $value => $name ) {
						$vi_story_position .= sprintf( '<option value="%s" selected="selected">%s</option>', $value, $name );
						break;
					}

					return sprintf(
						'<select class="adsns_adunit_position" name="adsns_vi_position" %s>%s</select>',
						$disabled,
						$vi_story_position
					);
				}
			}
		}
	}
}
