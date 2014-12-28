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

/// register hooks
register_activation_hook(   __FILE__, array( 'MtGDeckUtil', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'MtGDeckUtil', 'on_deactive' ) );
register_uninstall_hook(    __FILE__, array( 'MtGDeckUtil', 'on_uninstall' ) );

/// Plugin Implement
class MtGDeckUtil{

	/// callback to register_activation_hook
	public static function on_activate(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_${plugin}" );
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
	
	/// Initialize the plugin
	function __construct(){

		/// admin_hook
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		/// plugin menu page
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
	}

	function admin_init(){
		wp_register_style(
			'menu_deck',
			plugins_url( 'styles/menu_deck.css', __FILE__ )
		);
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
	
	function register_new_deck(){
		
		$formats = array(
			'Standard' => __('Standard', 'mtg-deck-util'),
			'Modern' => __('Modern', 'mtg-deck-util'),
			'Legacy' => __('Legacy', 'mtg-deck-util')
		);
?>
	<div id="register_deck_div">
		<div class="header"><h2 class="menu_title"><?php echo __("Register New Deck", 'mtg-deck-util') . "<br/>" ?></h2></div>
		<form action="<?php echo esc_url( $_SERVER['REQUEST_URI']) ?>" method="post">
			<div class="elem_name"><?php echo __( 'Player', 'mtg-deck-util' ) ?>:</div>
			<div class="elem"><input type="text" name="player_name" size="40"></div>
			<div class="elem_name"><?php echo __( 'Deckname', 'mtg-deck-util' ) ?>:</div>
			<div class="elem"><input type="text" name="deck_name" size="40"></div>
			<div class="elem_name"><?php echo __( 'Reference Key', 'mtg-deck-util' ) ?>:</div>
			<div class="elem"><input type="text" name="ref_key" size="40"></div>
			<div class="elem_name"><?php echo __( 'Format', 'mtg-deck-util' ) ?>:</div>
			<div class="elem">
				<select name="format">
					<?php
					foreach ( $formats as $t => $name ) {
						echo "<option value=\"$t\">$name</option>";
					}
					?>
				</select>
			</div>
			<div class="elem_name"><?php echo __( 'Decklist', 'mtg-deck-util' ) ?>:</div>
			<div class="elem"><textarea name="decklist" align="left" rows="20" cols="40"></textarea></div>
			<div class="submit"><input type="submit" align="right"" value=<?php echo __('Register Deck', 'mtg-deck-util') ?>></div>
		</form>
	</div>
<?php
	}
	
}

function MtGDeckUtil(){
	global $mtg_deck_util;
	$mtg_deck_util = new MtGDeckUtil();
}
add_action( 'init', 'MtGDeckUtil' );
