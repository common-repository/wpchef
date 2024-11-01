<tr>
	<th>Option
	<th>New Value
	<th>Action
<?php foreach ( $changelist as $option => $val ): $val = wpchef_ingredient::instance()->value_encode( $val ) ?>
<tr class="option-list-item">
	<th><?=esc_html( $option )?>
	<td><div style="word-break:break-all;"><?=esc_html( $val )?></div>
	<td>
		<a class="button use-recent-option button-small" data-option="<?=esc_attr($option)?>" data-value="<?=esc_attr( $val )?>">Add to Recipe</a>
		<i class="fa fa-refresh fa-spin"></i>
<?php endforeach ?>