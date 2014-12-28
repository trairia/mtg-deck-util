<?php
/*
  Plugin Name: Magic the Gathering Deck Utility
  Description: Chart generator for Magic: the Gathering decks.
  Author: trairia
  Author URI: http://twitter.com/trairia
  License: GPL3
 */

defined( 'ABSPATH' ) OR exit;

/// define type code
define( "CREATURE"    , 0x01);
define( "LAND"	     , 0x02);
define( "ENCHANTMENT" , 0x04);
define( "ARTIFACT"    , 0x08);
define( "INSTANT"     , 0x10);
define( "SORCERY"     , 0x20);
define( "TRIBAL"	     , 0x40);
define( "PLANESWALKER", 0x80);

/// for using sqlite db
define( "USE_SQLITE", true);
define( "SQLITE_DB_PATH", __DIR__ . '/db/mtgdb.db' );

class ChartDrawer{
	const MANACURVE = 1;
	const TYPEPIE = 2;
	const COLORPIE = 3;
	function draw( $data, $type ){
		$id = null;
		switch ( $type ){
			case self::MANACURVE:
				$id = "manacurve";
				break;
			case self::TYPEPIE:
				$id = "typepie";
				break;
			case self::COLORPIE:
				$id = "colorpie";
				break;
			default:
				$id = null;
		}
		if ( !is_null($id) ){
			echo <<<HTML
<div id="$id" data='$data'></div>
HTML;
		}
	}
};

/// DeckList Dumper Interface
class DeckListGenerator{
	var $deckinfo;
	var $deckdata;
	var $config;

	function __construct( $info, $deck, $config = null ){
		$this->deckinfo = $info;
		$this->deckdata = $deck;
		$this->config = $config;
	}

	public function gen_header(){
		$player = $this->deckinfo["player"];
		$format = $this->deckinfo["format"];
		$deckname = $this->deckinfo["name"];
		echo <<<HTML
	<table class="decklist">
		<thead>
			<tr>
				<th>
					<?php echo $player . '-' .  $deckname; ?>
				</th>
			</tr>
		</thead>
HTML;
	}

    public function gen_body(){
		echo "<tbody><tr><td>";

		foreach ( array( LAND, CREATURE ) as $t ){
			$num = 0;
			$datas = $this->deckdata["MainBoard"][$t];
			foreach ( $datas as $c ){
				echo esc_html( sprintf( "%d", $c["num"] ) . "  " . $c["name"] ), "<br />";
				$num += $c["num"];
			}
			echo "<hr><span>";
			if ( LAND == $t ){
				echo "-", __( "Lands" , "mtgdeckutil" ), "(", $num, ")-";
			} else {
				echo "-", __( "Creatures", "mtgdeckutil" ), "(", $num, ")-";
			}
			echo "</span>";
			echo "<br />";
		}
		echo "</td><td>";
		$num = 0;
		foreach ( array( ENCHANTMENT, ARTIFACT, INSTANT, SORCERY, TRIBAL, PLANESWALKER ) as $t ){
			$datas = isset( $this->deckdata["MainBoard"][$t] )
				? $this->deckdata["MainBoard"][$t]
				: null;
			if ( is_null($datas) ){
				continue;
			}

			foreach ( $datas as $c ){
				echo esc_html( sprintf( "%d", $c["num"] ) . "  " . $c["name"] ), "<br />";
				$num += $c["num"];
			}
		}
		echo "<hr><span>";
		echo "-", __( "Spells", "mtgdeckutil"), "(", $num, ")-";
		echo "</span><br />";

		$datas = $this->deckdata["SideBoard"];
		if ( is_null($datas) ){
			return;
		}
		$num = 0;
		foreach ( $datas as $c ){
			echo esc_html( sprintf( "%d", $c["num"] ) . "  " . $c["name"] ), "<br />";
			$num += $c["num"];
		}
		echo "<hr><span>";
		echo "-", __( "Sideboard", "mtgdeckutil"), "(", $num, ")-";
		echo "</span><br /></td>";
		echo "</tr></tbody>";		
    }
    public function generate(){
		$this->gen_header();
		$this->gen_body();
		echo "</table>";
	}
}

class MtGDeckUtil{

	const PLUGIN_NAME         = "MtGDeckUtil"; /// plugin name
	const PLUGIN_VERSION      = "0.1";      /// plugin version
	const PLUGIN_DB_VERSION   = "0.1";   /// plugin database version
	const DECKLIST_TABLE_NAME = "mtg_decklist";

	var $decklists;
	
	/// indicator for deck_id in contents
	var $num_deck = 0;
	
	/// instllation hook
	public static function on_activation(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );
		self::db_install();
	}

	/// Initialize the plugin and registering fooks
	public function __construct(){
		wp_enqueue_script( 'google-chart',
						   'https://www.google.com/jsapi');

		wp_enqueue_script( 'chartdrawer',
						   plugins_url( 'js/chart.js', __FILE__ ),
						   array( 'google-chart' ) );
		/// filter hooks
		add_filter( 'the_content', array( $this, 'shortcode_hack' ), 7 );

		/// load localization domains
		load_plugin_textdomain( 'mtgdeckutil', false, '/mtgdeckutil/localization' );

		/// Admin hooks
		add_action( 'admin_menu', array( $this, 'register_setting_page' ) );
		/// add db setting
		add_option( 'decklist_db_ver' );

		add_action( 'save_post', array( $this, 'update_decklist_db' ) );
		
		add_action( 'plugins_loaded', array( $this, 'update_db_check' ) );

	}

	public static function update_db_check(){
		$db_version = self::PLUGIN_DB_VERSION;
		$installed = get_option( 'decklist_db_ver' );
		if ( $db_version != $installed ){
			$this->db_install();
			update_option( 'decklist_db_ver', $db_version );
		}
	}

	static function db_install(){
		global $wpdb;

		$table_name = $wpdb->prefix . self::DECKLIST_TABLE_NAME;
		$charset_collated = $wpdb->get_charset_collate();
		$sql = <<<SQL
CREATE TABLE $table_name (
id bigint(20) UNSIGNED NOT NULL,
PRIMARY KEY id (id),
deckid tinyint(3) UNSIGNED NOT NULL,
player varchar(64),
format varchar(30),
deckname varchar(64),
decklist text,
manacurve text,
colorpie text,
typepie text
) $charset_collated;
SQL;
		require_once( ABSPATH . "wp-admin/includes/upgrade.php" );
		dbDelta( $sql );
	}
	
	/// short code wpautop() hack
	/// thanks to : http://wpforce.com/prevent-wpautop-filter-shortcode/
	function shortcode_hack( $content ){
		global $shortcode_tags;

		/// back up current shortcodes and clear them
		$orig_shortcode_tags = $shortcode_tags;

		$short_code_tags = array();
		
		/// add shortcode hook
		add_shortcode( 'deck', array( $this, 'deck_handler' ) );

		// Do the short code
		$content = do_shortcode( $content );

		// Put the original shortcodes back
		$shortcode_tags = $orig_shortcode_tags;

		// reset number of decks in content
		$this->num_deck = 0;

		return $content;
	}

	function register_setting_page(){
		add_options_page( __( 'MtG Deck Utility Settings', 'mtgdeckutil' ),
						  'Magic the Gathering Deck Utility',
						  'manage_options',
						  'mtgdeckutil',
						  array( $this, 'settings_page' ) );
	}

	function update_decklist_db( $post_id ){
		if ( wp_is_post_revision( $post_id ) )
			return;
		global $wpdb;
		global $post;
		$content = $post->post_content;
		$fp = fopen(__DIR__ . "/data.txt", "w");
		fwrite($fp, $content);
		$content = $this->shortcode_hack( $content );
		fwrite($fp, "%d decks", count($this->decklists));

		foreach ( $this->decklist as $deck ){

			$row = array(
				'id' => $post_id,
				'deckid' => $i,
				'format' => $deck[0]['format'],
				'player' => $deck[0]['player'],
				'deckname' => $deck[0]['deckname'],
				'decklist' => json_encode( $deck[1] ),
				'manacorve' => json_encode( $deck[2] ),
				'colorpie' => json_encode( $deck[3] ),
				'typepie' => json_encode( $deck[4] ) );
//			fwrite($fp, $deck );
			$wpdb->replace(
				$wpdb->prefix . self::DECKLIST_TABLE_NAME,
				$row,
				array(
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s'
				)
			);
			$i++;
		}
		fclose($fp);
	}
	
	function deck_handler( $atts, $content = null){
		if ( null == $content ){
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::DECKLIST_TABLE_NAME;
		$id = get_the_ID();
		$sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %d AND deckid = %d",
							  $id,
							  $this->num_deck
		);
		$ret = $wpdb->query( $sql );
		$deckinfo = null;
		$decklist = null;
		$manacurve = null;
		$types = null;
		$colors = null;
		/*
		   /// future implementation
		   /// this is pesudo code for table loading
		   if ( deck_in_decklist_table ) {
		   list ($decklist ... ) = get_deck_list_from_db( $unique_id );
		   } else {
		     $this->deck_list_parse(...);
		   }
		*/
		
		$this->deck_list_parse( $atts, $content );
		$deckinfo = $this->decklists[$this->num_deck][0];
		$decklist = $this->decklists[$this->num_deck][1];
		$manacurve = $this->decklists[$this->num_deck][2];
		$types = $this->decklists[$this->num_deck][3];
		$colors = $this->decklists[$this->num_deck][4];

		/* $generator = new DeckListGenerator( $deckinfo, $decklist );
		   $generator->generate();
		   $cgen = new ChartDrawer();
		   $cgen->draw( json_encode($manacurve), ChartDrawer::MANACURVE );
		   $cgen->draw( json_encode($colors), ChartDrawer::COLORPIE ); */

		/// increment indicator
		$this->num_deck += 1;

	}

	function deck_list_parse( $atts, $content ){

		$atts = shortcode_atts( array(
			'player' => '',
			'name'  => '',
			'format' => '',
			'lang' => 'ja',
			'joint' => false
		), $atts, 'deck');

		/// split into line
		$lines = explode( "\n", $content );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, 'strlen' );

		/// parse lines to deck data
		$deckinfo = array(
			"player" => $atts['player'],
			"name" => $atts['name'],
			"format" => $atts['format']
			);
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

		array_push( $this->decklists,
					array(
						$deckinfo,
						$deck,
						$ret_cmc_hist,
						$ret_types,
						$ret_colors
					)
		);
		return array( $deckinfo, $deck, $ret_cmc_hist, $ret_types, $ret_colors );
	}

	function settings_page(){
		/// write setting page code here
		echo ABSPATH . "wp-content/plugins/mtgdeckutil/mtgdeckutil.php" ;
	}
}

if ( WP_DEBUG ){
	function DEBUG_DUMP( $object ){
		echo "<pre>";
		var_dump( $object );
		echo "</pre>";
	}
} else {
	function DEBUG_DUMP( $object ){
	}
}

function MtGDeckUtil(){
	global $mtg_deck_util;
	$mtg_deck_util = new MtGDeckUtil();
}

add_action('init', 'MtGDeckUtil', 5);
add_action('plugins_loaded', array( 'MtGDeckUtil', 'update_db_check' ) );
register_activation_hook( ABSPATH . "wp-content/plugins/mtgdeckutil/mtgdeckutil.php",
						  array( 'MtGDeckUtil', 'on_activation' ) );

?>
