<?php
/*
Plugin Name: MtgDeckUtil
Version: 0.1-alpha
Description: Magic the Gathering Deck Utilities for Wordpress
Author: Kazunori Kimura
Author URI: http://twitter.com/trairia
Plugin URI: PLUGIN SITE HERE
Text Domain: mtg-deck-util
Domain Path: /languages
*/

/// for using sqlite db
define( "USE_SQLITE", true);
define( "SQLITE_DB_PATH", __DIR__ . '/db/mtgdb.db' );

/// register hooks
register_activation_hook(   __FILE__, array( 'MtGDeckUtil', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'MtGDeckUtil', 'on_deactive' ) );
register_uninstall_hook(    __FILE__, array( 'MtGDeckUtil', 'on_uninstall' ) );

/// Plugin Implement
class MtGDeckUtil{

	/// constants
	const MTG_DECK_UTIL_VER   = "0.1";
	const MTG_DECKLIST_STR    = "mtg_decklist";
	const MTG_DECKLIST_DB_VER = "0.1";
	
	/// callback to register_activation_hook
	public static function on_activate(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_${plugin}" );
		self::update_db_check();
	}

	/// callback to register_deactivation_hook
	public static function on_deactivate(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_${plugin}" );
	}

	/// callback to register_uninstall_hook
	public static function on_uninstall(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		check_admin_referer( 'bulk-plugins' );
		if ( __FILE__ != WP_UNINSTALL_PLUGIN )
			return;
	}

	/// create deck database to wordpress db
	/// on activation
	static function db_install(){
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . self::MTG_DECKLIST_STR;
		$sql = <<<SQL
CREATE TABLE $table_name (
id mediumint(9) NOT NULL AUTO_INCREMENT,
refkey varchar(30) NOT NULL,
deckname varchar(40) NOT NULL,
format varchar(20) NOT NULL,
player varchar(40),
decklist text,
decklist_json text,
manacurve_json text,
colorpie_json text,
typepie_json text,
UNIQUE KEY id (id)
) $collate;
SQL;
		require_once( ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta( $sql );
	}

	public static function update_db_check(){
		if ( get_option( 'mtg_decklist_db_version' ) != self::MTG_DECKLIST_DB_VER ){
			self::db_install();
			update_option( 'mtg_decklist_db_version', self::MTG_DECKLIST_DB_VER );
		}
	}
	
	/// Initialize the plugin
	function __construct(){
		/// admin_hook
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		/// plugin menu page
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
	}

	function register_styles(){
		wp_register_style(
			'menu_deck',
			plugins_url( 'styles/menu_deck.css', __FILE__ )
		);
	}
	
	function enqueue_scripts(){
		wp_enqueue_script( 'google-chart', 'https://www.google.com/jsapi' );
	}

	function register_options(){
		/// data base version
		add_option( 'mtg_decklist_db_version' );
	}
	
	function admin_init(){
		$this->register_styles();
		$this->enqueue_scripts();
		$this->register_options();
	}

	function plugin_menu(){
		
		add_menu_page(
			__('Magic the Gaghering Deck Utility', 'mtg-deck-util'),
			__('MtG Deck Utility', 'mtg-deck-util'),
			'manage_options',
			'mtgdeckutil',
			array( $this, 'menu_page' )
		);
		
		$deck_menu =  add_submenu_page(
			'mtgdeckutil',
			__('Add New Deck', 'mtg-deck-util'),
			__('Add New Deck', 'mtg-deck-util'),
			'manage_options',
			'mtgdeckutil-add-deck',
			array( $this, 'register_new_deck' )
		);
		add_action( 'admin_print_styles-' . $deck_menu, array( $this, 'load_deck_menu_style' ) );
	}

	function menu_page(){
	}

	/// load deck_menu.css
	function load_deck_menu_style(){
		wp_enqueue_style( 'menu_deck' );
	}

	function draw_deck_list( $deck ){
		include(__DIR__ . "/pages/decklist.php");
	}
	
	function register_new_deck(){
		
		$formats = array(
			'Standard' => __('Standard', 'mtg-deck-util'),
			'Modern' => __('Modern', 'mtg-deck-util'),
			'Legacy' => __('Legacy', 'mtg-deck-util')
		);

		$deckname = '';
		$player   = '';
		$refkey   = '';
		$decklist = '';
		
		if ( isset( $_POST['deck-submitted'] ) ){
			$deckname = sanitize_text_field( $_POST['deck_name'] );
			$player   = sanitize_text_field( $_POST['player_name'] );
			$format   = $_POST['format'];
			$refkey   = sanitize_text_field( $_POST['ref_key'] );
			$decklist = esc_textarea( $_POST["decklist"] );
		}
		
		include(__DIR__ . "/pages/deck_form.php");
	}

	function parse_deck( $deck ){
		
		$lines = explode( "\n", $deck );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, 'strlen' );

		/// parse lines to deck data

		$deck = array(
			"MainBoard" => array(),
			"SideBoard" => array() );
		$cmc_hist = array();
		$type_hist = array();
		$color_hist = array();
		$target = "MainBoard";

		foreach ( $lines as $l ){

			if ( 0 == strcasecmp( "sideboard", $l ) ){
				$target = "SideBoard";
				continue;
			}
			list( $num, $name ) = array_map( 'trim', explode( ' ', $l , 2 ) );
			$num = intval($num);


			try{
				/// pull card data from db
				$pdo = null;
				if ( USE_SQLITE ){
					$pdo = new PDO("sqlite:" . SQLITE_DB_PATH,
								   '',
								   '');
				} else {
					$pdo = new PDO("mysql:dbname=mtgdb;host=localhost;port=3306;charset=utf8",
								   "mtgdb",
								   "mtgdb",
								   array( PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION,
										  PDO::ATTR_EMULATE_PREPARES => false ) );
				}
				$stmt = $pdo->prepare('SELECT * FROM carddata INNER JOIN cardnames ON carddata.multiverseid = cardnames.multiverseid WHERE carddata.cardname = :cardname' );
				$stmt->bindValue( ':cardname', $name );
				$stmt->execute();
				$ret = $stmt->fetch();
				/// get main card type
				if ( !is_null( $ret ) ){
					/// decide cardname
					$retname = $atts["lang"] ? $ret[$atts["lang"]] : $name;

					if ( $target == "MainBoard" ){
						/// for color pie
						$ckey = "multicolor";
						switch ( strlen( $ret["colors"] ) ){
							case 0:
								$ckey = "colorless";
								break;
							case 1:
								$ckey = $ret["colors"];
								break;
							default:
								break;
						}
						if ( array_key_exists( $ckey, $color_hist ) ){
							$color_hist[$ckey] += $num;
						} else {
							$color_hist[$ckey] = $num;
						}

						/// covered manacost
						$cmc = $ret["cmc"];

						if ( array_key_exists( $cmc, $cmc_hist ) ){
							$cmc_hist[$cmc] += $num;
						} else {
							$cmc_hist[$cmc] = $num;
						}


						for ( $type = CREATURE; $type <= PLANESWALKER; $type = $type * 2 ){
							/// for type pie
							if ( $type == ( $ret["typecode"] & $type) ){
								if ( array_key_exists( $type, $type_hist) ){
									$type_hist[$type] += $num;
								} else {
									$type_hist[$type] = $num;
								}
								/// deck data
								if ( array_key_exists( $type, $deck[$target] ) ){
									array_push( $deck[$target][$type], array( "num" => $num,
																			  "name" => $retname ) );
								} else {
									$deck[$target][$type] = array( array("num" => $num, "name"=> $retname) );
								}
								break;
							}
						}
					} else {
						array_push($deck[$target], array( "num" => $num, "name" => $retname ));
					}
				}
			} catch ( Exception $e ){
				$error = $e->getMessage();
				echo $error;
			}


		}
		ksort($cmc_hist);

		// create manacurve data for google chart
		$ret_cmc_hist = array( array( "CMC", "ammount" ) );
		$last_key = key( array_slice( $cmc_hist, -1, 1, true ) );
		for ( $i = 0; $i <= $last_key; $i++ ){
			$ret_cmc = isset($cmc_hist[$i]) ? $cmc_hist[$i] : 0 ;
			array_push( $ret_cmc_hist, array( $i, $ret_cmc ) );
		}

		// create color pie data for google chart
		$ret_colors = array( array( "color", "ammount") );
		foreach ( $color_hist as $c => $v ){
			array_push( $ret_colors, array( $c, $v ) );
		}

		// create type pie data
		$ret_types = array( array( "type", "ammount" ) );
		foreach ( $type_hist as $k => $v ){
			array_push( $ret_types, array( $k, $v ) );
		}
	}
}

function MtGDeckUtil(){
	global $mtg_deck_util;
	$mtg_deck_util = new MtGDeckUtil();
}

if ( WP_DEBUG ){
	function DEBUG_DUMP( $obj ){
		echo "<pre>";
		var_dump( $obj );
		echo "</pre>";
	}
} else {
	function DEBUG_DUMP( $obj ){
	}
}
add_action( 'plugin_loaded', array( 'MtGDeckUtil', 'update_db_check' ) );
add_action( 'init', 'MtGDeckUtil' );
