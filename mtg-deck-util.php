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

/// about card database
define( "MTGDB_NAME", 'mtgdb' );
define( "MTGDB_HOST", 'localhost' );
define( "MTGDB_USER", 'mtgdb' );
define( "MTGDB_PASS", 'mtgdb' );
define( "MTGDB_PORT", '3306' );
define( "MTGDB_CHARSET", 'utf8' );

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

$COLOR_NAME_CONV = array(
	'W' => __( 'White', 'mtg-deck-util' ),
	'U' => __( 'Blue', 'mtg-deck-util' ),
	'B' => __( 'Black', 'mtg-deck-util' ),
	'R' => __( 'Red', 'mtg-deck-util' ),
	'G' => __( 'Green', 'mtg-deck-util' ),
	'multicolor' => __( 'MultiColored', 'mtg-deck-util' ),
	'colorless' => __( 'ColorLess', 'mtg-deck-util' )
);

/// sanitizer for hex color string
function sanitize_color( $str ){
	return esc_html( $str );
}

function get_attr( array $arr, $attr, $default=null ){
	return isset( $arr[$attr] ) ? $arr[$attr] : $default;
}

function update_attr( array $arr, $attr, $value ){
	if ( isset( $arr[$attr] ) ){
		$arr[$attr] += $value;
	}else{
		$arr[$attr] = $value;
	}
	return $arr;
}

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
		/// setting language path
		load_plugin_textdomain(
			'mtg-deck-util',
			false,
			dirname( plugin_basename(__FILE__) ) . '/languages'
		);
		
		/// admin_hook
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		/// plugin menu page
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );

		/// shortcode
		add_shortcode( 'deck', array( $this, 'deck_import' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
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
	function register_scripts(){
		wp_enqueue_script( 'google-chart',
						   'https://www.google.com/jsapi',
						   '',
						   '',
						   true);
		wp_enqueue_script(
			'chart-js',
			plugins_url( '/js/chart.js', __FILE__ ),
			array( 'google-chart' ),
			'',
			true
		);

		if ( is_admin() ){
			wp_enqueue_script(
				'jscolor',
				plugins_url( 'js/jscolor.js', __FILE__),
				array(),
				'',
				false
			);
		}
	}

	/// register options
	function register_options(){
		/// data base version
		add_option( 'mtg_decklist_db_version' );

		//// general setting section option
		/// language
		register_setting( 'mtg-deck-util-group', 'mtg-util-lang', 'esc_attr' );
		register_setting( 'mtg-deck-util-group', 'mtg-cardname-fmt', 'esc_html' );
		add_settings_section(
			'mtg-deck-util-general',
			__( 'General', 'mtg-deck-util'),
			null,
			'mtgdeckutil'
		);

		add_settings_field(
			'mtg-util-lang',
			__( 'Language', 'mtg-deck-util' ),
			array( $this, 'set_lang_callback' ),
			'mtgdeckutil',
			'mtg-deck-util-general'
		);

		add_settings_field(
			'mtg-cardname-fmt',
			__( 'Cardname Format', 'mtg-deck-util' ),
			array( $this, 'set_cardname_format' ),
			'mtgdeckutil',
			'mtg-deck-util-general'
		);
		
		/// color setting section
		add_settings_section(
			'mtg-deck-util-colors',
			__( 'Colors', 'mtg-deck-util' ),
			null,
			'mtgdeckutil'
		);

		$conf_colors = array(
			array(
				'name' => 'mtg-color-manacurve',
				'help' => __( 'Color of Manacurve', 'mtg-deck-util' ),
				'default' => '#0000FF'
			),
			array(
				'name' => 'mtg-color-white',
				'help' => __('White Color', 'mtg-deck-util' ),
				'default' => '#FFFFFF'
			),
			array(
				'name' => 'mtg-color-blue',
				'help' => __( 'Blue Color', 'mtg-deck-util' ),
				'default' => '#0000FF'
			),
			array(
				'name' => 'mtg-color-black',
				'help' => __( 'Black Color', 'mtg-deck-util' ),
				'default' => '#000000'
			),
			array(
				'name' => 'mtg-color-red',
				'help' => __( 'Red Color', 'mtg-deck-util' ),
				'default' => '#FF0000'
			),
			array(
				'name' => 'mtg-color-green',
				'help' => __( 'Green Color', 'mtg-deck-util' ),
				'default' => '#00FF00'
			),
			array(
				'name' => 'mtg-color-colorless',
				'help' => __( 'ColorLess Color', 'mtg-deck-util' ),
				'default' => '#808080'
			),
			array(
				'name' => 'mtg-color-multicolor',
				'help' => __( 'MultiColored Color', 'mtg-deck-util' ),
				'default' => '#CCCC00'
			)
		);

		foreach( $conf_colors as $color ){
			register_setting(
				'mtg-deck-util-group',
				$color['name'],
				'sanitize_color'
			);

			add_settings_field(
				$color['name'],
				$color['help'],
				array( $this, 'set_color_callback' ),
				'mtgdeckutil',
				'mtg-deck-util-colors',
				$color
			);
		}
	}

	
	function set_lang_callback(){
		$setting = esc_attr( get_option( 'mtg-util-lang' ) );
		$lang_map = array(
			'en' => __( 'English', 'mtg-deck-util' ),
			'ja' => __( 'Japanese', 'mtg-deck-util' ),
			'zh_cn' => __( 'Simplified Chinese', 'mtg-deck-util' ),
			'zh_tw' => __( 'Traditional Chinese', 'mtg-deck-util' ),
			'fr' => __( 'French', 'mtg-deck-util' ),
			'de' => __( 'German', 'mtg-deck-util' ),
			'it' => __( 'Italiano', 'mtg-deck-util' ),
			'ko' => __( 'Korean', 'mtg-deck-util' ),
			'ru' => __( 'Russian', 'mtg-deck-util' ),
			'pt' => __( 'Portuguese', 'mtg-deck-util' )
		);
		echo "<select name='mtg-util-lang' value='$setting'>";
		foreach( $lang_map as $key => $val ){
			$selected = '';
			if ( $setting == $key ){
				$selected="selected='selected'";
			}
			echo "<option value='$key' $selected>$val</option>";
		}
		echo "</select>";
	}

	function set_color_callback($args){
		$name = get_attr( $args, 'name', '');
		$default = get_attr( $args, 'default' );
		$val = get_option( $name, $default );
		if ( $val ){
			$val = "value='" . $val . "'";
		}
		echo "<input class='color' name='$name' $val>";
	}

	function set_cardname_format(){
		$fmt = get_option( 'mtg-cardname-fmt', esc_html('<%n>'));
		echo "<input class='text' name='mtg-cardname-fmt' value='$fmt'><br/>";
		echo esc_html("%n : " . __("Card name in locale selected", 'mtg-deck-util'));
		echo "<br/>";
		echo esc_html("%N : " . __("English card name", 'mtg-deck-util'));
		echo "<br/>";
		echo esc_html("Example <%n/%N> ==> <name in locale/name in English>");
	}
	
	/// init admin
	function admin_init(){
		$this->register_styles();
		$this->register_scripts();
		$this->register_options();
	}
	function plugin_menu(){
		
		add_options_page(
			__('Magic the Gaghering Deck Utility', 'mtg-deck-util'),
			__('MtG Deck Utility', 'mtg-deck-util'),
			'manage_options',
			'mtgdeckutil',
			array( $this, 'menu_page' )
		);
		
		$deck_menu =  add_options_page(
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
		echo <<<HTML
<div class="wrap">
  <h2> MtG Deck Util Options </h2>
  <form action="options.php" method="POST">
HTML;
		settings_fields( 'mtg-deck-util-group' );
		do_settings_sections( 'mtgdeckutil' );
		submit_button();
		echo "</form></div>";

		/// include list of decks
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
			$manacurve = $result['manacurve_json'];
			$manacurve_color = get_option( 'mtg-manacurve-color', 'blue' );
			$chart_opt = json_encode(
				array(
					'colors' => array( $manacurve_color ),
					'legend' => array( 'position' => 'none' )
				)
			);
			$colorpie = $result['colorpie_json'];
			$pie_opt = $this->generate_colors_from_pie( $colorpie );

			include( __DIR__ . "/pages/chart.php");
		}
	}

	function generate_colors_from_pie( $json ){
		global $COLOR_NAME_CONV;
		$color_map = array(
			$COLOR_NAME_CONV['R'] => get_option( 'mtg-color-red', 'red' ),
			$COLOR_NAME_CONV['W'] => get_option( 'mtg-color-white', 'white' ),
			$COLOR_NAME_CONV['U'] => get_option( 'mtg-color-blue', 'blue' ),
			$COLOR_NAME_CONV['B'] => get_option( 'mtg-color-black', 'black' ),
			$COLOR_NAME_CONV['G'] => get_option( 'mtg-color-green', 'green' ), 
			$COLOR_NAME_CONV['multicolor'] => get_option( 'mtg-color-multicolor', 'yellow' ),
			$COLOR_NAME_CONV['colorless'] => get_option( 'mtg-color-colorless', 'gray' )
		);
		$colors = array();
		$pie_data = json_decode( $json, true );
		for( $i = 1; $i < count($pie_data); $i++ ){
			array_push($colors, $color_map[$pie_data[$i][0]]);
		}
		return json_encode( array( 'colors' => $colors ), true );
	}
	
	/// Decklist Drawer
	function draw_deck_list( $deck ){
		$deckname  = $deck['deckname'];
		$format    = $deck['format'];
		$player    = $deck['player'];
		$decklist  = json_decode( $deck['decklist_json'], true);
		$mainboard = $decklist["MainBoard"];
		$lands     = get_attr( $mainboard, LAND, array() );
		$creatures = get_attr( $mainboard, CREATURE, array() );
		$instant   = get_attr( $mainboard, INSTANT, array() );
		$sorcery   = get_attr( $mainboard, SORCERY, array() );
		$artifact  = get_attr( $mainboard, ARTIFACT, array() );
		$enchant   = get_attr( $mainboard, ENCHANTMENT, array() );
		$planeswalker = get_attr( $mainboard, PLANESWALKER, array() );
		$sideboard = get_attr( $decklist, "SideBoard", array() );
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
		$lang = get_option( 'mtg-util-lang' );
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
					$dsn = "mysql:dbname=" . MTGDB_NAME . ";host=" . MTGDB_HOST;
					$dsn = $dsn . ";port=" . MTGDB_PORT . ";charset=" . MTGDB_CHARSET;
					$pdo = new PDO(
						$dsn,
						MTGDB_USER,
						MTGDB_PASS,
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
					$retname = $ret[ $lang ];
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
						$color_hist = update_attr( $color_hist, $ckey, $num );
						/// covered manacost
						$cmc = $ret["cmc"];
						$cmc_hist = update_attr( $cmc_hist, $cmc, $num );

						for ( $type = CREATURE; $type <= TRIBAL; $type = $type * 2 ){
							/// for type pie
							if ( $type == ( $ret["typecode"] & $type) ){
								$type_hist = update_attr( $type_hist, $type, $num );

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
		global $COLOR_NAME_CONV;
		$ret_colors = array( array( "color", "ammount") );
		foreach ( $color_hist as $c => $v ){
			array_push( $ret_colors, array( $COLOR_NAME_CONV[$c], $v ) );
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
