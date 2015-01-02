<?php
/*
 * View List of Decks
 */

if ( ! class_exists( 'WP_List_Table' ) ){
	require_once( ABSPATH . "wp-admin/includes/class-wp-list-table.php" );
}

class Deck_List_Table extends WP_List_Table{

	var $num_selected = 0;
	function __construct(){
		global $status, $page;
		
		parent::__construct(
			array(
				'singular' => 'deck',
				'plural' => 'decks',
				'ajax' => false
			)
		);
	}
	
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
		$this->process_bulk_action();
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
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['id']
		);
	}

	function process_bulk_action(){
		if ( 'delete' === $this->current_action() ){
			foreach( $_GET['deck'] as $deck ){
				$this->delete_deck( $deck );
			}
		}
	}

	function delete_deck( $deck ){
		global $wpdb;
		$wpdb->delete(
			'wp_mtg_decklist',
			array( 'id' => intval($deck) ),
			array( '%d' )
		);
	}
}
$deck_list_table = new Deck_List_Table();
$deck_list_table->prepare_items();
?>

<div class="wrap">
	<h2>
	<?php echo __( 'List of Decks', 'mtg-deck-util' ) ?>
	</h2>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>">
		<?php $deck_list_table->display() ?>
	</form>
</div>
