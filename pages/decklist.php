<?php
function count_num( $data ){
	$num = 0;
	foreach( $data as $d ){
		$num += $d['num'];
	}
}
?>
<div class="decklist">
	<div class="deck_list_header"><?php echo $deckname ?>/<?php echo $player ?></div>
	<div class="pile_land">
		<?php foreach ( $lands as $l) {?>
			<?php echo $l['num'] ?> <?php echo $formatter->format( $l ) ?> <br />
		<?php } ?>
	</div>
	<div class="pile_creature">
		<?php foreach ( $creatures as $c) {?>
			<?php echo $c['num'] ?> <?php echo $formatter->format( $c ) ?> <br />
		<?php } ?>
	</div>
	<div class="pile_spell">
		<?php foreach ( array_merge( $instant, $sorcery, $artifact, $enchant, $planeswalker ) as $s) {?>
			<?php echo $s['num'] ?> <?php echo $formatter->format( $s ) ?> <br />
		<?php } ?>
	</div>
	<div class="sideboard">
		<?php echo __('Sideboard', 'mtg-deck-util') ?> <br />
		<?php foreach ( $sideboard as $s) {?>
			<?php echo $s['num'] ?> <?php echo $formatter->format( $s ) ?> <br />
		<?php } ?>
	</div>
</div>
