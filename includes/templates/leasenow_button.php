<?php
/**
 * @var bool        $leasenow_display
 * @var string      $leasenow_message
 * @var string      $leasenow_redirect_url
 * @var string      $leasenow_image_url
 * @var string      $leasenow_loading_gif
 * @var string      $leasenow_code
 * @var bool        $leasenow_nofollow
 * @var string      $leasenow_image_scale
 * @var bool        $leasenow_tooltip_display
 * @var string|null $leasenow_missing_amount_text
 * @var bool        $leasenow_availability
 */

$leasenow_product_id = null;
if(isset($id) && $id) {
	$leasenow_product_id = $id;
}

?>

<style>
	.leasenow_button:hover {
		background-color:rgba(0, 0, 0, 0);
	}

	.leasenow_button:focus {
		outline:none;
	}

	.leasenow_button-content {
		position:relative;
		float:left;
		width:100%;
		margin-bottom:5px;
		margin-top:5px;
	}

	.leasenow_button-overlay {
		left:0;
		top:0;
		right:0;
		bottom:0;
		z-index:2;
		background-color:rgba(255, 255, 255, 0.8);
		transform:translateY(-50%);
		-webkit-transform:translateY(-50%);
		-ms-transform:translateY(-50%);
		text-align:center;
		color:#555;
		height:100%;
	}

	.leasenow_lds-roller div {
		animation:lds-roller 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
		transform-origin:40px 40px;
	}

	.leasenow_lds-roller div:after {
		content:" ";
		display:block;
		position:absolute;
		width:7px;
	}

	.leasenow_loading_gif-center {
		top:0;
		bottom:0;
		left:0;
		right:0;
		margin:5px auto auto;
	}

	.leasenow_d--none {
		display:none;
	}

	.leasenow_tooltip span {
		margin-bottom:12px;
		font-weight:normal;
		line-height:normal;
		z-index:10;display:none;
		padding:6px 8px;
		box-shadow:0px 0px 0px 1px rgba(25, 25, 43, 0.04);
		filter:drop-shadow(0px 9px 24px rgba(25, 25, 43, 0.09)) drop-shadow(0px 3px 6px rgba(25, 25, 43, 0.06));
	}

	.leasenow_tooltip:hover span {
		width:100%;
		display:block;
		position:absolute;
		bottom:100%;
		left:0;
		color:#373535;
		background:#fffFff;
	}

	.leasenow_button {

		width:<?php if(isset($leasenow_image_scale) && $leasenow_image_scale) {
		echo esc_html($leasenow_image_scale);
	} else {
		echo 100;
	} ?>% !important;
		height:auto !important;
		border:none;
		padding: 0;
		position: relative;
		background-color:rgba(0, 0, 0, 0);
		margin: 0 !important;
	}

	.leasenow_button-image {
		width:100%;
		height: 100%;
	}

</style>

<div class="leasenow_button-content <?php echo $leasenow_display
	? ''
	: 'leasenow_d--none'; ?>">

	<?php
	if(isset($leasenow_code) && $leasenow_code) { ?>
		<div class="leasenow_d--none">
			ln__<?php echo esc_html($leasenow_code); ?>
		</div>
	<?php } ?>
	<div class="leasenow_button-overlay leasenow_d--none">
		<div class="leasenow_button-overlay-content">
			<img class="leasenow_loading_gif-center" src="<?php echo esc_url($leasenow_loading_gif); ?>" alt="Loading..."/>
		</div>
	</div>

	<button type="button" class="leasenow_tooltip leasenow_button" <?php if($leasenow_availability) { ?> onclick="window.open('<?= $leasenow_redirect_url ?>', '_blank')" <?php } ?>>
		<img alt="ING Lease Now" class="leasenow_button-image" src="<?= $leasenow_image_url ?>">
		<?php if(isset($leasenow_tooltip_display) && $leasenow_tooltip_display) { ?>
			<span id="leasenow_toooltip_message" class="<?php echo $leasenow_missing_amount_text
				? ''
				: 'leasenow_d--none'; ?>"><?= $leasenow_missing_amount_text ?></span>
		<?php } ?>
	</button>
</div>

<script>
	var $leasenow_button_overlay = jQuery('.leasenow_button-overlay'),
		$leasenow_button = jQuery('.leasenow_button'),
		$leasenow_button_content = jQuery('.leasenow_button-content'),
		$leasenow_button_image = jQuery('.leasenow_button-image'),
		$leasenow_button_message = jQuery('#leasenow_button-message'),
		leasenow_class_d_none = 'leasenow_d--none',
		leasenow_tooltip_span = '<span id="leasenow_toooltip_message"></span>',
		leasenow_product_id = <?= $leasenow_product_id ?>,
		leasenow_variation_id = null,
		leasenow_is_during_call = false;

	if (leasenow_product_id) {
		jQuery(document).ready(function ($) {

			var $quantity_input = jQuery("input[name='quantity']");

			$quantity_input.change(function () {

				if ($quantity_input.val() > 0) {

					leasenow_make_call(leasenow_variation_id
						? {variation_id: leasenow_variation_id,}
						: {product_id: leasenow_product_id});
				}
			});
		});
	}

	jQuery('form.variations_form').on('found_variation',
		function (event, variation) {

			leasenow_variation_id = variation.variation_id;

			leasenow_make_call({variation_id: variation.variation_id,})
		}
	);

	function leasenow_before_send_request() {
		leasenow_is_during_call = true;

		var $tooltipMessage = jQuery('#leasenow_toooltip_message');

		if ($tooltipMessage) {
			$tooltipMessage.remove();
		}
		$leasenow_button.addClass(leasenow_class_d_none);
		$leasenow_button.removeAttr('onclick')
		$leasenow_button_overlay.removeClass(leasenow_class_d_none);
		$leasenow_button_content.removeClass(leasenow_class_d_none);
		$leasenow_button_message.html('');
	}

	/**
	 * @param json
	 */
	function leasenow_make_call(json) {

		if (leasenow_is_during_call) {
			return;
		}

		json.action = "leasenow_add_content_after_addtocart_button_func_ajax";
		json.quantity = jQuery("input[name='quantity']").val();

		jQuery.ajax({
			type:       "POST",
			dataType:   "json",
			url:        "<?php echo admin_url('admin-ajax.php'); ?>",
			data:       json,
			beforeSend: function () {
				leasenow_before_send_request();
			},
			success:    function (response) {

				if (!response.success
					|| !response.data.leasenow_image_url
					|| !response.data.leasenow_image_scale
					|| !(response.data.leasenow_redirect_url || response.data.leasenow_tooltip_message)) {
					$leasenow_button_content.addClass(leasenow_class_d_none);

					if (response.data && response.data.error?.code) {
						$leasenow_button_content.append('<div class="leasenow_d--none">leasenow__' + response.data.body.error.code + '</div>');
					}
					return ''
				}

				if (response.data.leasenow_redirect_url) {
					$leasenow_button.attr('onclick', "window.open('" + response.data.leasenow_redirect_url + "', '_blank')")
				}

				if (response.data.leasenow_tooltip_message) {
					$leasenow_button.append(leasenow_tooltip_span);
					var temp = jQuery('#leasenow_toooltip_message');
					temp.append(response.data.leasenow_tooltip_message);
				}

				$leasenow_button.removeClass(leasenow_class_d_none);
				$leasenow_button_image.attr('src', response.data.leasenow_image_url);
				$leasenow_button_overlay.addClass(leasenow_class_d_none);
				jQuery('#leasenow_redirect_url').attr('href', response.data.leasenow_redirect_url);
			},
			error:      function () {
				$leasenow_button_content.addClass(leasenow_class_d_none);
			},
			complete:   function () {
				leasenow_is_during_call = false;
			}
		});
	}

</script>
