<style>
	.leasenow_button {
		margin-top:10px
	}

	.leasenow_button-image {
		width:200px;
	}

	.leasenow_button-content {
		min-height:86px;
		position:relative;
		float:left;
		width:100%;
	}

	.leasenow_button-overlay {
		position:absolute;
		left:0;
		top:0;
		right:0;
		bottom:0;
		z-index:2;
		background-color:rgba(255, 255, 255, 0.8);
	}

	.leasenow_button-overlay {
		position:absolute;
		transform:translateY(-50%);
		-webkit-transform:translateY(-50%);
		-ms-transform:translateY(-50%);
		top:50%;
		left:0;
		right:0;
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
		position:absolute;
		top:0;
		bottom:0;
		left:0;
		right:0;
		margin:auto;
	}

	.leasenow_d--none {
		display:none;
	}
</style>

<div class="leasenow_button-content">
	<div class="leasenow_button-overlay leasenow_d--none">
		<div class="leasenow_button-overlay-content">
			<img class="leasenow_loading_gif-center" src="<?php echo esc_url(WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/leasenow_loading.gif'); ?>" alt="Loading..."/>
		</div>
	</div>
	<a id="leasenow_redirect_url" href="#">
		<div class="leasenow_button">
			<img class="leasenow_button-image" alt="LeaseNow" src="<?php echo esc_url(WOOCOMMERCE_LEASENOW_PLUGIN_URL . 'resources/images/leasenow_button_test.png'); ?>">
		</div>
	</a>
</div>
