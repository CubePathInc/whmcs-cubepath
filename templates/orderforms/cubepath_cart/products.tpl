{include file="orderforms/standard_cart/common.tpl"}

{*
 * VPS plans of a product group as cards. The resources are the
 * "Name: value" lines of the product description, which WHMCS turns into
 * $product.features (vCPU, RAM, Disk, Transfer for CubePath products).
 *}
<div id="order-standard_cart" class="cp-store">
    <div class="cp-store-head">
        <h1>{if $productGroup.headline}{$productGroup.headline}{else}{$productGroup.name}{/if}</h1>
        {if $productGroup.tagline}<p>{$productGroup.tagline}</p>{/if}
    </div>

    {if $cp.groups && count($cp.groups) > 1}
        <nav class="cp-store-tabs">
            {foreach $cp.groups as $group}
                <a href="{$WEB_ROOT}/{$group.url}" class="{if $group.id == $productGroup.id}active{/if}">{$group.name}</a>
            {/foreach}
        </nav>
    {elseif !$cp}
        {include file="orderforms/standard_cart/sidebar-categories-collapsed.tpl"}
    {/if}

    {if $errormessage}
        <div class="alert alert-danger">{$errormessage}</div>
    {elseif !$productGroup}
        <div class="alert alert-info">{lang key='orderForm.selectCategory'}</div>
    {/if}

    <div class="cp-plans" id="products">
        {foreach $products as $product}
            {$idPrefix = ($product.bid) ? ("bundle"|cat:$product.bid) : ("product"|cat:$product.pid)}
            <div class="cp-plan" id="{$idPrefix}">
                <div class="cp-plan-head">
                    <span class="cp-plan-name" id="{$idPrefix}-name">{$product.name}</span>
                    {if $product.stockControlEnabled}
                        <span class="cp-plan-stock">{$product.qty} {$LANG.orderavailable}</span>
                    {/if}
                </div>

                <div class="cp-plan-price" id="{$idPrefix}-price">
                    {if $product.bid}
                        <small>{$LANG.bundledeal}</small>
                        {if $product.displayprice}<strong>{$product.displayprice}</strong>{/if}
                    {else}
                        {if $product.pricing.hasconfigoptions}<small>{$LANG.startingfrom}</small>{/if}
                        <strong>{$product.pricing.minprice.price}</strong>
                        <small>
                            {if $product.pricing.minprice.cycle eq "monthly"}{$LANG.orderpaymenttermmonthly}
                            {elseif $product.pricing.minprice.cycle eq "quarterly"}{$LANG.orderpaymenttermquarterly}
                            {elseif $product.pricing.minprice.cycle eq "semiannually"}{$LANG.orderpaymenttermsemiannually}
                            {elseif $product.pricing.minprice.cycle eq "annually"}{$LANG.orderpaymenttermannually}
                            {elseif $product.pricing.minprice.cycle eq "biennially"}{$LANG.orderpaymenttermbiennially}
                            {elseif $product.pricing.minprice.cycle eq "triennially"}{$LANG.orderpaymenttermtriennially}
                            {/if}
                        </small>
                        {if $product.pricing.minprice.setupFee}
                            <small class="cp-plan-setup">{$product.pricing.minprice.setupFee->toPrefixed()} {$LANG.ordersetupfee}</small>
                        {/if}
                    {/if}
                </div>

                {if $product.features}
                    <ul class="cp-plan-specs">
                        {foreach $product.features as $feature => $value}
                            <li id="{$idPrefix}-feature{$value@iteration}">
                                {* Smarty has no "contains": a name contains a word when removing it changes the name. *}
                                {$f = $feature|lower}
                                <i class="fas fa-fw {if $f|replace:'cpu':'' != $f}fa-microchip{elseif $f|replace:'ram':'' != $f || $f|replace:'mem':'' != $f}fa-memory{elseif $f|replace:'disk':'' != $f || $f|replace:'disco':'' != $f || $f|replace:'storage':'' != $f}fa-hdd{elseif $f|replace:'transf':'' != $f || $f|replace:'bandwidth':'' != $f}fa-exchange-alt{else}fa-check{/if}"></i>
                                <span>{$feature}</span>
                                <strong>{$value}</strong>
                            </li>
                        {/foreach}
                    </ul>
                {/if}
                {if $product.featuresdesc}
                    <p class="cp-plan-desc" id="{$idPrefix}-description">{$product.featuresdesc}</p>
                {/if}

                <a href="{$product.productUrl}" class="btn btn-primary btn-block btn-order-now" id="{$idPrefix}-order-button"{if $product.hasRecommendations} data-has-recommendations="1"{/if}>
                    {$LANG.ordernowbutton}
                </a>
            </div>
        {/foreach}
    </div>
</div>

{include file="orderforms/standard_cart/recommendations-modal.tpl"}
