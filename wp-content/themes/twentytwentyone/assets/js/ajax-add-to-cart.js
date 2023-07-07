jQuery(function($){
    /* global wc_add_to_cart_params */
    if ( typeof wc_add_to_cart_params === 'undefined' ) {
		return false;
    }
  
    $(document).on('submit', 'form.cart', function(e){
        
        var form = $(this),
            button = form.find('.single_add_to_cart_button');
        
        var formFields = form.find('input:not([name="product_id"]), select, button, textarea');
        // create the form data array
        var formData = [];
        formFields.each(function(i, field){
            // store them so you don't override the actual field's data
            var fieldName = field.name,
                fieldValue = field.value;
            if(fieldName && fieldValue){
                // set the correct product/variation id for single or variable products
                if(fieldName == 'add-to-cart'){
                    fieldName = 'product_id';
                    fieldValue = form.find('input[name=variation_id]').val() || fieldValue;
                }
                // if the fiels is a checkbox/radio and is not checked, skip it
                if((field.type == 'checkbox' || field.type == 'radio') && field.checked == false){
                    return;
                }
                // add the data to the array
                formData.push({
                    name: fieldName,
                    value: fieldValue
                });                
            }
        });
        if(!formData.length){
            return;
        }
        
        e.preventDefault();
        
        form.block({ 
            message: null, 
            overlayCSS: {
                background: "#ffffff",
                opacity: 0.6 
            }
        });
        $(document.body).trigger('adding_to_cart', [button, formData]);
  
        $.ajax({
            type: 'POST',
            url: woocommerce_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
            data: formData,
            success: function(response){
                if(!response){
                    return;
                }
                if(response.error & response.product_url){
                    window.location = response.product_url;
                    return;
                }
                
                $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, button]);
            },
            complete: function(){
                form.unblock();
            }
        });
  
      return false;
  
    });
});