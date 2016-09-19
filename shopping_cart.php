<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  require("includes/application_top.php");
  if ($cart->count_contents() > 0) {
    include(DIR_WS_CLASSES . 'payment.php');
    $payment_modules = new payment;
  }
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
  $breadcrumb->add('Winkelwagen', tep_href_link(FILENAME_SHOPPING_CART));
  require(DIR_WS_INCLUDES . 'template_top.php');

 if (tep_session_is_registered('customer_id')) {
      $shipping='';
      require(DIR_WS_CLASSES . 'order.php');
      $order = new order;
    }else{
        $order->delivery = array('country' => array('id' => STORE_COUNTRY, 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' =>  $country_info['countries_iso_code_3']),
                                 'country_id' => STORE_COUNTRY,
                                 'format_id' => tep_get_address_format_id($HTTP_POST_VARS['country_id']));
      // set the cost to be able to calculate free shipping
      $order->info = array('total' => $cart->show_total(), // TAX ????
                           'currency' => $currency,
                           'currency_value'=> $currencies->currencies[$currency]['value']);
    	}
    require(DIR_WS_CLASSES . 'shipping.php');
    $shipping_modules = new shipping;
    $quotes = $shipping_modules->quote();

	$cartShippingCost=get_best_shipping($quotes);
	$estimatedShipping='';
	if(!empty($cartShippingCost)):
			if($cartShippingCost['table']['costs'] > 0 ):
				$estimatedShipping=array('label'=>'Verzendkosten','value'=>$currencies->format($cartShippingCost['table']['costs']));
			else:
				$estimatedShipping=array('label'=>'','value'=>'<div class="greenclr">Gratis verzending!</div>');
			endif;
	endif;
//echo '<pre>'.print_r(get_best_shipping($quotes),1).'</pre>';
// if shippings costs == 0, show: Gratis verzending!

?>
<div class="animate-dropdown">
 <?php echo $breadcrumb->trail( '' ); ?>
</div>
<!--<?php //echo tep_draw_form('cart_quantity', tep_href_link(FILENAME_SHOPPING_CART, 'action=update_productcart')); ?>-->
<?php if ($cart->count_contents() > 0) { ?>
<div id="crat_contents">
<section id="cart-page" class="marbtm0">
    <div class="container">
	<div class="border h1 row">
		<h2 class="h1 col-md-6"><?php echo 'Winkelwagen'; ?></h2>
    	<div class="col-md-6 mtop20">
    		<a href="<?=tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')?>" class="le-button pull-right btnclr">Uw bestelling afrekenen</a>
            <a href="<?=tep_href_link(FILENAME_DEFAULT)?>" class="le-button pull-right mright20">Verder winkelen</a>
   		</div>
	</div>
         <div class="col-xs-12 col-md-12 items-holder no-margin">
             <div class="row no-margin">
             	<div class="col-xs-12 col-sm-1 no-margin"></div>
                <div class="col-xs-12 col-sm-1 no-margin"></div>
                <div class="col-xs-12 col-sm-4"></div>
                <div class="col-xs-12 col-sm-2 no-margin text-center">Prijs Excl BTW</div>
                <div class="col-xs-12 col-sm-2 no-margin text-center">Aantal</div>
                <div class="col-xs-12 col-sm-2 no-margin text-center">Regelprijs</div>
             </div>
            <?php
    $any_out_of_stock = 0; $taxrates = array();
	$totprice=0; $total_taxes=0; $productTotal=0;
	$tax_by_prod = array(); $taxamount=0;
        $producShipping_Id = array();
	$products = $cart->get_products();
	//echo '<pre>'; print_r($products); echo '</pre>';
	for ($i=0, $n=sizeof($products); $i<$n; $i++)
	{
		// Push all attributes information in an array
      	if(isset($products[$i]['attributes']) && is_array($products[$i]['attributes']))
		{
        	while (list($option, $value) = each($products[$i]['attributes']))
			{
          		echo tep_draw_hidden_field('id[' . $products[$i]['id'] . '][' . $option . ']', $value);
          			$attributes = tep_db_query("select
		  								popt.products_options_name,
										poval.products_options_values_name,
										pa.options_values_price,
										pa.price_prefix
										from " . TABLE_PRODUCTS_OPTIONS . " popt,
										" . TABLE_PRODUCTS_OPTIONS_VALUES . " poval,
										" . TABLE_PRODUCTS_ATTRIBUTES . " pa
										where pa.products_id = '" . (int)$products[$i]['id'] . "'
                                       	and pa.options_id = '" . (int)$option . "'
                                       	and pa.options_id = popt.products_options_id
                                       	and pa.options_values_id = '" . (int)$value . "'
                                       	and pa.options_values_id = poval.products_options_values_id
                                       	and popt.language_id = '" . (int)$languages_id . "'
                                       	and poval.language_id = '" . (int)$languages_id . "'");
          				$attributes_values = tep_db_fetch_array($attributes);
          				$products[$i][$option]['products_options_name'] = $attributes_values['products_options_name'];
          				$products[$i][$option]['options_values_id'] = $value;
         	 			$products[$i][$option]['products_options_values_name'] = $attributes_values['products_options_values_name'];
          				$products[$i][$option]['options_values_price'] = $attributes_values['options_values_price'];
          				$products[$i][$option]['price_prefix'] = $attributes_values['price_prefix'];
        	}
      	}
		//$final_price = getCorrectPrice($products[$i]['id'],$products[$i]['final_price']);
		$line_price=($products[$i]['quantity']*$products[$i]['final_price']);
		#echo tep_calculate_tax($line_price,tep_get_tax_rate($products[$i]['tax_class_id'])).'<br>';
		#echo tep_get_tax_description().':'.tep_get_tax_rate($products[$i]['tax_class_id']);
		$tax_totals[$products[$i]['tax_class_id']] +=number_format(tep_calculate_tax($line_price,tep_get_tax_rate($products[$i]['tax_class_id'])),2);

		$remarketing_id_array[]='"'.$products[$i]['unique_identifier'].'"';
	  }
	  //print_r($cart);
	//print_r($products);
    		for ($i=0, $n=sizeof($products); $i<$n; $i++) {

			$Product_tax_label=ProductTaxClassLabel($products[$i]['tax_class_id']);
			$product_stock = getProductStock($products[$i]['unique_identifier'],$products[$i]['suppliers_id']);
			$product_minorder = getProductSingle((int)$languages_id,(int)$products[$i]['id']);
			$pimg=!empty($products[$i]['image'])?DIR_WS_IMAGES.$products[$i]['image']:'http://placehold.it/73x73';
			$order_qty_cart = ($product_minorder['minorder'] > $products[$i]['quantity'])?$product_minorder['minorder']:$products[$i]['quantity'];
			$productFinalPrice = $products[$i]['final_price'];
			$productPriceSpecial =  SpecialPrice($products[$i]['id']);
			if(!empty($productPriceSpecial)) $productFinalPrice = $productPriceSpecial['specials_products_price'];
		?>
              <div class="row no-margin cart-item">
                <div class="col-xs-12 col-sm-1 no-margin">
   <a class="close-btn" href="<?=tep_href_link(FILENAME_SHOPPING_CART, 'products_id='.$products[$i]['id'].'&action=remove_product')?>"></a>
                </div>
                <div class="col-xs-12 col-sm-1 no-margin">
                    <a href="<?=tep_href_link(FILENAME_PRODUCT_INFO, 'products_id='.$products[$i]['id'])?>" class="thumb-holder">
                   <img class="lazy" alt="" src="<?=$pimg?>" width="<?=CART_PRODUCT_IMG_WIDTH?>" height="<?=CART_PRODUCT_IMG_HEIGHT?>" />
                    </a>
                </div>
                <div class="col-xs-12 col-sm-4 ">
                    <div class="title">
                        <a href="<?=tep_href_link(FILENAME_PRODUCT_INFO, 'products_id='.$products[$i]['id'])?>"><?=$products[$i]['name']?></a>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-2 no-margin">
                    <div class="price">
                        <?=$currencies->format($productFinalPrice)?>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-2 no-margin text-center">
                    <div class="quantity">
                        <div class="le-quantity">
                          <select id="product_qty_cart<?=$i?>" class="le-select" onChange="UpdateCartQty(this.value,<?=$products[$i]['id']?>)">
                            <?=getQtySelectValues($product_minorder['minorder'],20,$order_qty_cart)?>
                           
                            </select>
                            <input type="hidden" value="<?=$products[$i]['id']?>" class="txt_value">
                            <div class="meer_pop">               
                              <a class="close-btn c_pop_meer"></a>
                            	<p>Vul hieronder het te bestellen aantal in en klik op OK</p>
                              <form class="frm">
                                <input type="textbox" class="more" value="1"/>
                                <input type="button" value="OK" class="abc"/>
                                </form>
                                <script>
                  								
                  								$('.le-select').on('change',function(){
                                  if($(this).val()=='meer'){
                                  // show popup
                                  $(this).parent().find('.meer_pop').show();
                                  } else{
                                  // hide all popups
                                  $('.meer_pop').hide();
                                  }
                                  });
                  								$('.c_pop_meer').click(function(){
                  									$('.meer_pop').hide();
                                     var qty= $(this).parent().find('.more').val();
                                     $(this).parent().parent().parent().find('.le-select option:selected').text(qty);
                                      var productid=$(this).parent().parent().parent().find('.txt_value').val();
                                       UpdateQty(qty,productid);
                                    });

                  								$('.abc').click(function(){
                                      var qty= $(this).parent().find('.more').val();
                                      $(this).parent().parent().parent().find('.le-select option:selected').text(qty);
                                      $('.meer_pop').hide();
                                      var productid=$(this).parent().parent().parent().find('.txt_value').val();
                                      UpdateQty(qty,productid);

                  								});
                  								</script>
                            </div>
                        </div>
                        <div class="qty_status">
								<?php if($product_stock['products_quantity'] >= $order_qty_cart): ?>
									<div class="redclr">Op voorraad</div>
								<?php else: ?>
									<div class="redclr">Niet op voorraad</div>
								<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-2 no-margin">
                    <div class="price">
                       <?=$currencies->display_price($productFinalPrice, tep_get_tax_rate($products[$i]['tax_class_id']), $order_qty_cart)?>
                    </div>
                </div>
			</div>
         <?php
		 				$productTotal=round(($order_qty_cart*$productFinalPrice),2);
						$totprice+=$productTotal;
						$taxrate = ProductTaxClass($products[$i]['tax_class_id']);
						$taxamount = round((($productTotal*$taxrate['tax_rate'])/100),2);
						$taxrates['tax'.$taxrate['tax_rate']] += $taxamount;
						$taxlabel['tax'.$taxrate['tax_rate']] =$Product_tax_label;
						$total_taxes += $taxamount;
						$producShipping_Id[$i] = $products[$i]['shipping_id'];
}
			$shipping_spaicial = getShippingGroupRate($producShipping_Id); //print_r($shipping_spaicial);
			if($shipping_spaicial['shipping_rate'] > 0 and $cart->show_total() > 150):
				$estimatedShipping=array('label'=>'Verzendkosten','value'=>$currencies->format($shipping_spaicial['shipping_rate']));
			endif; 
			if($shipping_spaicial['shipping_rate'] > 0 and $cart->show_total() < 150):
				$estimatedShipping=array('label'=>'Verzendkosten','value'=>$currencies->format($shipping_spaicial['shipping_rate']));
			endif; 
			if(CheckIsFranco($customer_id)): 
				$shipping_cost = 0; $shipping['type'] = 'free'; 
				$estimatedShipping=array('label'=>'','value'=>'<div class="greenclr">Gratis verzending!</div>');
			endif;
			$estimatedShippingValue=preg_replace("/[^0-9.,]/",'',$estimatedShipping['value']);
			 if($estimatedShippingValue >0){
				 if(is_array($taxrates)){
					 if(array_key_exists('tax21.0000',$taxrates)){
						 $taxrates['tax'.$taxrate['tax_rate']] += number_format(($estimatedShippingValue*.21),2);
						 $total_taxes+=number_format(($estimatedShippingValue*.21),2);
					 }
				 }
				}

			?>
		</div>
       	<!--<div class="col-xs-12 col-md-12 items-holder">
        	<input type="submit" value="Update shopping cart" name="update_productcart" class="le-button pull-right" />
      	</div> -->
	</div>
</section>
<!--</form>-->
<section id="cart-page">
	<div class="container">
    <div class="border h1"></div>
    	<div class="col-xs-12 col-md-6 no-margin ">
    		<div class="widget cart-summary">
            	<div class="body">
                    <ul class="tabled-data no-border inverse-bold mtop20 no_border_btm">
                       <li>
                            <label>Veillig afrekenen bij Office Deals</label>
                        </li>
                     </ul>
                     <div class="securityLogos col-md-12">
                  	    <div class="afflogo_Qshops"><?=tep_image('/'.DIR_WS_IMAGES . AFFI_LOGOQshops, STORE_NAME)?></div>
            			<div class="afflogo_kiyoh"><?=tep_image('/'.DIR_WS_IMAGES . AFFI_LOGOkiyoh, STORE_NAME)?></div>
            			<div class="afflogo_ICTWaarborg"><?=tep_image('/'.DIR_WS_IMAGES . AFFI_LOGOICTWaarborg, STORE_NAME)?></div>
                        <div class="afflogo_sslcomodo"><?=tep_image('/'.DIR_WS_IMAGES . 'comodo-ssl_small.png', STORE_NAME)?></div>
                     </div>
                	</div>
                    <div class="pull-left col-md-3 col-xs-12 col-sm-12 mtop50">
                         <a class="simple-link block" href="<?=tep_href_link(FILENAME_DEFAULT)?>">verder winkelen </a>
                    </div>
             </div>
    	</div>
        <div class="col-xs-12 col-md-2 no-margin "></div>
      	<div class="col-xs-12 col-md-4 no-margin sidebar">
            <div class="widget cart-summary">
                 <div class="body">
                    <ul class="tabled-data no-border inverse-bold">
                        <li>
                            <label>Subtotaal:</label>
                            <div class="value pull-right"><?=$currencies->format($totprice)?></div>
                        </li>
                       <?php if(!empty($estimatedShipping)): ?>
                        <li>
                            <label><?=$estimatedShipping['label']?></label>
                            <div class="value pull-right"><?=$estimatedShipping['value']?></div>
                        </li>
                       <?php endif; ?>
                        <?php if(!empty($taxrates)):
								foreach($taxrates as $taxkey => $taxvalue): 
						?>
                        <li>
                            <label><?=$taxlabel[$taxkey]?></label>
                            <div class="value pull-right"><?=$currencies->format($taxvalue)?></div>
                        </li>
                        <?php endforeach; endif; ?>
                    </ul>
                    <ul id="total-price" class="tabled-data inverse-bold no-border">
                        <li>
                            <label>Totaal:</label>
                            <div class="value pull-right"><?=$currencies->format($totprice+$total_taxes+$estimatedShippingValue)?></div>
                        </li>
                    </ul>
                    <div class="buttons-holder">
               			<a href="<?=tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')?>" class="le-button btnclr">Uw bestelling afrekenen</a>
                        <div class="clrsc_link mtop10"><i class="fa fa-lock"></i>&nbsp; via een beveiligde verbinding </div>
                    </div>
                </div>
            </div>
       </div>
 	</div>
</section>
</div>
<!--</form>-->
<?php  } else { ?>
	<div class="border h1 row">
		<h2 class="h1 col-md-offset-1 col-md-5"><?php echo 'Winkelwagen'; ?></h2>
    </div>
    <section id="cart-page">
    	<div class="container">
        	<div class="col-xs-12 col-md-12 items-holder no-margin">
          		<div class="alert alert-danger">
					<?php echo TEXT_CART_EMPTY; ?>
               	</div>
    			<div class="text-center">
                    <a href="<?=tep_href_link(FILENAME_DEFAULT)?>" class="le-button">Verder winkelen</a>
                </div>
          	</div>
    	</div>
 	</section>
<?php }

  include(DIR_WS_MODULES . FILENAME_X_SELL_PRODUCTS);

	$remarketing_pagetype='cart';
	$remarketing_totalvalue=number_format($cart->show_total(),2,'.','');

	if(is_array($remarketing_id_array)){
		$remarketing_ids=implode(',',$remarketing_id_array);
		$remarketing_products_id='['.$remarketing_ids.']';

	}

  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
