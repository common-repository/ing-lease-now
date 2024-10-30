<p><?php echo __('We have received your application form. We will let know know what next steps are in few hours. In case you have any questions do not hesitate to contact us:', 'leasenow'); ?>
	<a href="mailto:leasenow@inglease.pl">leasenow@inglease.pl</a>
</p>

<?php if(is_user_logged_in()) { ?>
	<div>
		<a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button"><?php echo __('Show orders', 'leasenow'); ?></a>
	</div>
<?php } ?>
