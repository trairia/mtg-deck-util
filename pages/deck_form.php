<div id="register_deck_div">
	<div class="header"><h2 class="menu_title"><?php echo __("Register New Deck", 'mtg-deck-util') . "<br/>" ?></h2></div>
	<form action="<?php echo esc_url( $_SERVER['REQUEST_URI']) ?>" method="post">
		<div class="elem_name"><?php echo __( 'Deckname', 'mtg-deck-util' ) ?>:</div>
		<div class="elem"><input type="text" name="deck_name" size="40" value="<?php echo $deckname ?>"></div>
		<div class="elem_name"><?php echo __( 'Player', 'mtg-deck-util' ) ?>:</div>
		<div class="elem"><input type="text" name="player_name" size="40" value="<?php echo $player ?>"></div>
		<div class="elem_name"><?php echo __( 'Reference Key', 'mtg-deck-util' ) ?>:</div>
		<div class="elem"><input type="text" name="ref_key" size="40" value="<?php echo $refkey ?>"></div>
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
		<div class="elem"><textarea name="decklist" align="left" rows="20" cols="40"><?php echo $decklisttxt ?></textarea></div>
		<div class="submit"><input type="submit" name="deck-submitted" align="right"" value=<?php echo __('Register Deck', 'mtg-deck-util') ?>></div>
	</form>
</div>
