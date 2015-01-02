<?php
/*
 * View List of Decks
 */

if ( ! class_exists( 'WP_List_Table' ) ){
	require_once( ABSPATH . "wp-admin/includes/class-wp-list-table.php" );
}

class Deck_List_Table extends WP_List_Table{

	function get_columns(){
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'refkey' => __( 'Reference Key', 'mtg-deck-util' ),
			'deckname' => __( 'Deck Name', 'mtg-deck-util' ),
			'format' => __( 'Format', 'mtg-deck-util' ),
			'player' => __( 'Player', 'mtg-deck-util' ),
		);
		return $columns;
	}

	function prepare_items(){
		global $wpdb;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $wpdb->get_results(
			"SELECT id, refkey, deckname, format, player from wp_mtg_decklist",
			ARRAY_A
		);
	}

	function column_default( $item, $column_name ){
		switch ( $column_name ){
			case 'refkey':
			case 'deckname':
			case 'format':
			case 'player':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}

	function get_bulk_actions(){
		$actions = array(
			'delete' => __( 'Delete', 'mtg-deck-util' )
		);
		return $actions;
	}

	function column_cb( $item ){
		return sprintf(
			'<input type="checkbox" name="deck[]" value="%s" />',
			$item['id']
		);
	}
}
?>

