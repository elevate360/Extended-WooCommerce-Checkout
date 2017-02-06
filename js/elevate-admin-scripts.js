/*
 * Admin Scripts
 * 
 */
jQuery(document).ready(function($){
	
	//el-checkout-page
	//When selecting the 'Enable Checkout Redirect' woocommerce option (custom), show associated field
	$('#el-checkout-redirect').on('change', function(){
		var redirectPage = $('#el-checkout-page').parents('tr');
		if($(this).is(':checked')){
			redirectPage.show();
		}else{
			redirectPage.hide();
		}
		
	});
	$('#el-checkout-redirect').trigger('change');
	
	
});
