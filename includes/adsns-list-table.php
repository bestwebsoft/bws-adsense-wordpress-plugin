<?php
/**
 * Display Table with Ads
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'Adsns_List_Table' ) ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	/**
	 * Class for display Adsns_List_Table
	 */
	class Adsns_List_Table extends WP_List_Table {

		/**
		 * Data for table
		 *
		 * @var array
		 */
		public $adsns_table_data;
		/**
		 * Units for tabel
		 *
		 * @var array
		 */
		public $adsns_table_adunits;
		/**
		 * Unit positions
		 *
		 * @var array
		 */
		public $adsns_adunit_positions;
		/**
		 * Unit positions for pro
		 *
		 * @var array
		 */
		public $adsns_adunit_positions_pro;
		/**
		 * Options for plugin
		 *
		 * @var array
		 */
		private $adsns_options;
		/**
		 * Counter for items
		 *
		 * @var int
		 */
		private $item_counter;

		/**
		 * Init for class
		 *
		 * @param array  $options       Options from plugin.
		 * @param string $current_tab   Current tab name.
		 * @param array  $table_data    Table data.
		 * @param array  $table_adunits Ads units.
		 * @param array  $adunit_positions Unit positions.
		 * @param array  $adunit_positions_pro Pro unit positions.
		 */
		public function __construct( $options, $current_tab, $table_data, $table_adunits, $adunit_positions, $adunit_positions_pro ) {
			$this->adsns_options = $options;
			$this->item_counter  = 0;

			$this->compat_fields = array_merge( $this->compat_fields, array( 'adsns_table_area', 'adsns_table_data', 'adsns_table_adunits', 'adsns_adunit_positions', 'adsns_adunit_positions_pro' ) );

			$this->adsns_table_area           = $current_tab;
			$this->adsns_table_data           = $table_data;
			$this->adsns_table_adunits        = $table_adunits;
			$this->adsns_adunit_positions     = $adunit_positions;
			$this->adsns_adunit_positions_pro = $adunit_positions_pro;

			parent::__construct(
				array(
					'singular' => __( 'item', 'bws-adsense-plugin' ),
					'plural'   => __( 'items', 'bws-adsense-plugin' ),
					'ajax'     => false,
				)
			);
		}

		/**
		 * Get table classes
		 */
		public function get_table_classes() {
			return array( 'adsns-list-table', 'widefat', 'fixed', 'striped', $this->_args['plural'] );
		}

		/**
		 * Get all columns
		 */
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

		/**
		 * Sortable for info
		 *
		 * @param array $a Order info.
		 * @param array $b Order info.
		 */
		public function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'name';
			$order   = ( ! empty( $_GET['order'] ) ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
			$result  = strcasecmp( $a[ $orderby ], $b[ $orderby ] );
			return ( 'asc' === $order ) ? $result : -$result;
		}

		/**
		 * Get sortable columns
		 */
		public function get_sortable_columns() {
			$sortable_columns = array(
				'name'    => array( 'name', false ),
				'code'    => array( 'code', false ),
				'summary' => array( 'summary', false ),
				'status'  => array( 'status', false ),
			);
			return $sortable_columns;
		}

		/**
		 * Prepare info for display
		 */
		public function prepare_items() {
			$columns               = $this->get_columns();
			$hidden                = array();
			$sortable              = $this->get_sortable_columns();
			$primary               = 'name';
			$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

			usort( $this->adsns_table_data, array( &$this, 'usort_reorder' ) );

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

		/**
		 * Display columns
		 *
		 * @param array  $item        The current item data.
		 * @param string $column_name The current column name.
		 * @return string $item[ $column_name ]
		 */
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
		/**
		 * Display cb columns
		 *
		 * @param array $item The current item data.
		 */
		public function column_cb( $item ) {
			if ( 'DISPLAY' === $item['type'] ) {
				return sprintf( '<input class="adsns_adunit_ids" type="checkbox" name="adsns_adunit_ids[]" value="%s" %s/>', $item['id'], checked( array_key_exists( $item['id'], $this->adsns_table_adunits ), true, false ) );
			}
		}
		/**
		 * Display column position
		 *
		 * @param array $item        The current item data.
		 * @return string Item info.
		 */
		public function column_position( $item ) {
			if ( 'DISPLAY' === $item['type'] && 'Archived' !== $item['status'] ) {
				$adsns_adunit_positions = is_array( $this->adsns_adunit_positions ) ? $this->adsns_adunit_positions : array();

				$disabled = ( ! array_key_exists( $item['id'], $this->adsns_table_adunits ) ) ? ' disabled="disabled"' : '';

				$adsns_adunit_positions_pro = is_array( $this->adsns_adunit_positions_pro ) ? $this->adsns_adunit_positions_pro : array();
				$adsns_position             = '';
				$adsns_position_pro         = '';
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
			}
		}
	}
}
