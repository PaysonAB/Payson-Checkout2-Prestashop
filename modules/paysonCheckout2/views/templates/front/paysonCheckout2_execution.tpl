{capture name=path}
	<a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='paysonCheckout2'}">{l s='Checkout' mod='paysonCheckout2'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='paysonCheckout2 payment' mod='paysonCheckout2'}
{/capture}

<iframe id='checkoutIframe' style="height:{Tools::getValue('height')}{Tools::getValue('height_type')}; width:{Tools::getValue('width')}{Tools::getValue('width_type')};"  scrolling="no" name='checkoutIframe' src={Tools::getValue('snippetUrl')}>
</iframe>