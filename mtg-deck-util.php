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
define( "USE_SQLITE", false);
define( "SQLITE_DB_PATH", __DIR__ . '/db/mtgdb.db' );

/// define card type
define( "CREATURE"    , 0x01);
define( "LAND"        , 0x02);
define( "ARTIFACT"    , 0x04);
define( "ENCHANTMENT" , 0x08);
define( "INSTANT"     , 0x10);
define( "SORCERY"     , 0x20);
define( "PLANESWALKER", 0x40);
define( "TRIBAL"      , 0x80);

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
PRIMARY KEY  (refkey),
INDEX decklistidx (id)
) $collate;
SQL;
		require_once( ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta( $sql );
	}

	/// check db version when plugin update
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

		/// shortcode
		add_shortcode( 'deck', array( $this, 'deck_import' ) );
	}

	/// register or enqueue css
	function register_styles(){
		/// css for deck register 
		wp_register_style(
			'menu_deck',
			plugins_url( 'styles/menu_deck.css', __FILE__ )
		);
	}

	/// register or enqueue javascript
	function enqueue_scripts(){
		wp_enqueue_script( 'google-chart', 'https://www.google.com/jsapi' );
	}

	/// register options
	function register_options(){
		/// data base version
		add_option( 'mtg_decklist_db_version' );
	}


	/// init admin
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
		
		add_action( 'admin_print_styles-' . $deck_menu,
					array(
						$this,
						'load_deck_menu_style'
					)
		);
	}

	function menu_page(){
		include( __DIR__ . "/pages/decks_list_view.php");
	}

	/// load deck_menu.css
	function load_deck_menu_style(){
		wp_enqueue_style( 'menu_deck' );
	}

	/// shortcode deck
	function deck_import( $atts, $contents = null ){
		global $wpdb;
		$atts = shortcode_atts(
			array(
				'ref' => ''
			),
			$atts,
			'deck'
		);

		if ( empty( $atts['ref'] ) ){
			return;
		} else {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM wp_mtg_decklist WHERE refkey = %s",
					$atts['ref']
				),
				ARRAY_A
			);
			$this->draw_deck_list( $result );
		}
	}

	/// Decklist Drawer
	function draw_deck_list( $deck ){
		$deckname  = $deck['deckname'];
		$format    = $deck['format'];
		$player    = $deck['player'];
		$decklist  = json_decode( $deck['decklist_json'], true);
		$mainboard = $decklist["MainBoard"];
		$lands     = isset( $mainboard[LAND] )        ? $mainboard[LAND]        : array();
		$creatures = isset( $mainboard[CREATURE] )    ? $mainboard[CREATURE]    : array();
		$instant   = isset( $mainboard[INSTANT] )     ? $mainboard[INSTANT]     : array();
		$sorcery   = isset( $mainboard[SORCERY] )     ? $mainboard[SORCERY]     : array();
		$artifact  = isset( $mainboard[ARTIFACT] )    ? $mainboard[ARTIFACT]    : array();
		$enchant   = isset( $mainboard[ENCHANTMENT] ) ? $mainboard[ENCHANTMENT] : array();
		$planeswlaker = isset( $mainboard[PLANESWALKER] ) ? $mainboard[PLANESWALKER] : array();
		$sideboard = $decklist["SideBoard"];
		include(__DIR__ . "/pages/decklist.php");
	}

	function list_of_decks(){
		global $wpdb;
		
		$decks = $wpdb->get_results(
"
SELECT refkey, deckname, format, player from wp_mtg_decklist;
"
		);
		DEBUG_DUMP($decks);
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
		$decklisttxt = '';
		
		if ( isset( $_POST['deck-submitted'] ) ){
			$deckname = sanitize_text_field( $_POST['deck_name'] );
			$player   = sanitize_text_field( $_POST['player_name'] );
			$format   = $_POST['format'];
			$refkey   = sanitize_text_field( $_POST['ref_key'] );
			$decklisttxt = esc_textarea( $_POST["decklist"] );
			$decklisttxt = str_replace( "\\", "", $decklisttxt );

			if ( ! empty( $decklisttxt ) ){
				list( $decklist, $manacurve, $colorpie, $typepie )
					= $this->parse_deck( $decklisttxt );

				$this->store_deck_to_db(
				   $refkey,
				   $deckname,
				   $format,
				   $player,
				   $decklisttxt,
				   $decklist,
				   $manacurve,
				   $colorpie,
				   $typepie
				   );
			}
		}
		
		include(__DIR__ . "/pages/deck_form.php");
	}

	function store_deck_to_db( $ref_key, $deck_name, $format, $player, $decklisttxt,
							   $decklist, $manacurve, $colorpie, $typepie ){

		$manacurve_str = json_encode( $manacurve );
		$colorpie_str = json_encode( $colorpie );
		$typepie_str = json_encode( $typepie );

		global $wpdb;
		$row = array(
			'refkey' => $ref_key,
			'deckname' => $deck_name,
			'format' => $format,
			'player' => $player,
			'decklist' => $decklisttxt,
			'decklist_json' => json_encode( $decklist ),
			'manacurve_json' => json_encode( $manacurve ),
			'colorpie_json' => json_encode( $colorpie ),
			'typepie_json' => json_encode( $typepie )
		);

		$row_fmt = array_fill( 0, 9, '%s' );
		$wpdb->replace(
			'wp_mtg_decklist',
			$row,
			$row_fmt
		);
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
					$pdo = new PDO(
						"sqlite:" . SQLITE_DB_PATH,
						'',
						''
					);
				} else {
					$pdo = new PDO(
						"mysql:dbname=mtgdb;host=localhost;port=3306;charset=utf8",
						"root",
						"wordpress",
						array(
							PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
							PDO::ATTR_EMULATE_PREPARES => false
						)
					);
				}
				$stmt = $pdo->prepare(
"
SELECT * FROM carddata
INNER JOIN cardname ON carddata.multiverseid=cardname.multiverseid
WHERE carddata.cardname=:search_target" );

				$stmt->bindValue( ":search_target", $name, PDO::PARAM_STR );
				$stmt->execute();
				$ret = $stmt->fetch();

				/// get main card type
				if ( !is_null( $ret ) ){
					/// decide cardname
					$retname = $ret["ja"];
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
						if ( isset( $color_hist[$ckey] ) ) {
							$color_hist[$ckey] += $num;
						} else {
							$color_hist[$ckey] = $num;
						}

						/// covered manacost
						$cmc = $ret["cmc"];

						if ( isset( $cmc_hist[$cmc] ) ){
							$cmc_hist[$cmc] += $num;
						} else {
							$cmc_hist[$cmc] = $num;
						}


						for ( $type = CREATURE; $type <= TRIBAL; $type = $type * 2 ){
							/// for type pie
							if ( $type == ( $ret["typecode"] & $type) ){
								if ( isset( $type_hist[$type] ) ){
									$type_hist[$type] += $num;
								} else {
									$type_hist[$type] = $num;
								}
								/// deck data
								if ( isset( $deck[$target][$type] ) ){
									array_push(
										$deck[$target][$type],
										array(
											"num" => $num,
											"name" => $retname
										)
									);
								} else {
									$deck[$target][$type] = array(
										array(
											"num" => $num,
											"name"=> $retname
										)
									);
								}
								break;
							}
						}
					} else {
						array_push(
							$deck[$target],
							array(
								"num" => $num,
								"name" => $retname
							)
						);
					}
				}
			} catch ( Exception $e ){
				$error = $e->getMessage();
				DEBUG_DUMP($error);
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
		return array( $deck, $ret_cmc_hist, $ret_colors, $ret_types );
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
