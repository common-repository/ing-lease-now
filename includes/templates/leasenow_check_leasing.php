<?php
/**
 * @var string $leasenow_reservation_id
 */

$leasenow_error_status = __( 'The status could not be retrieved. Please try again later', 'leasenow' );
?>

<style>
	.leasenow_check-leasing {
		text-align:center;
	}
	.leasenow_loading_gif-center {
		top:0;
		bottom:0;
		left:0;
		right:0;
		margin:auto;
		display:none;
	}
	.leasenow_leasing_status {
		display:none;
	}
</style>

<div class="leasenow_leasing_status">
	<span class="leasenow_leasing_status-value"></span>
</div>

<div class="leasenow_check-leasing">
	<div class="">
		<img class="leasenow_loading_gif-center" src="<?php echo esc_url( WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/leasenow_loading.gif' ); ?>" alt="Loading..."/>
	</div>
	<button type="button" class="button-primary button-check-leasing"><?php echo __( 'Check leasing status', 'leasenow' ); ?></button>
</div>

<script type="application/javascript">

	var $leasenow_button = jQuery('.button-check-leasing'),
		$leasenow_loading_gif = jQuery('.leasenow_loading_gif-center'),
		$leasenow_leasing_status_value = jQuery('.leasenow_leasing_status-value'),
		$leasenow_leasing_status = jQuery('.leasenow_leasing_status');

	$leasenow_button.on('click', function () {
		$leasenow_loading_gif.show();
		$leasenow_button.hide();

		jQuery.ajax({
			type:       "POST",
			dataType:   "json",
			url:        "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
			beforeSend: function () {
				$leasenow_loading_gif.show();
				$leasenow_button.hide();
			},
			data:       {
				action:                  "leasenow_check_leasing",
				leasenow_reservation_id: "<?php echo esc_html( $leasenow_reservation_id ); ?>"
			}
		})
			.done(function (data, textStatus, jqXHR) {

				if (!data.success
					|| !data.data.status) {
					leasenow_insert_error_status();
					return;
				}

				$leasenow_leasing_status_value.text(data.data.status);
			})
			.fail(function () {
				leasenow_insert_error_status();
			})
			.always(function () {
				$leasenow_leasing_status.toggle();
				$leasenow_loading_gif.toggle()
			})
	});

	function leasenow_insert_error_status() {
		$leasenow_leasing_status.text('<?php echo $leasenow_error_status; ?>')
	}
</script>