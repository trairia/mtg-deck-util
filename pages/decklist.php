<div class="decklist">
	<div class="deck_list_header"><?php echo $deckname ?>/<?php echo $player ?></div>
	<div class="pile_land">
		<?php foreach ( $lands as $l) {?>
			<?php echo $l['num'] ?> <?php echo $l['name'] ?> <br />
		<?php } ?>
	</div>
	<div class="pile_creature">
		<?php foreach ( $creatures as $c) {?>
			<?php echo $c['num'] ?> <?php echo $c['name'] ?> <br />
		<?php } ?>
	</div>
	<div class="pile_spell">
		<?php foreach ( array_merge( $instant, $sorcery, $artifact ) as $s) {?>
			<?php echo $s['num'] ?> <?php echo $s['name'] ?> <br />
		<?php } ?>
	</div>
	<div class="sideboard">
		<?php echo __('Sideboard', 'mtg-deck-util') ?> <br />
		<?php foreach ( $sideboard as $s) {?>
			<?php echo $s['num'] ?> <?php echo $s['name'] ?> <br />
		<?php } ?>
	</div>
</div>
