{include file="$template/includes/flashmessage.tpl"}

<div class="cp-page-head">
    <div>
        <h1>{if $cp}{$cp.t.welcome|replace:'%s':$clientsdetails.firstname}{else}{lang key='clientareatitle'}{/if}</h1>
        {if $cp}<p>{$cp.t.welcomeSub}</p>{/if}
    </div>
    <a class="btn btn-primary" href="{$WEB_ROOT}/cart.php">
        <i class="fas fa-plus"></i>
        {if $cp}{$cp.t.deploy}{else}{lang key='navservicesorder'}{/if}
    </a>
</div>

<div class="cp-grid cp-grid-stats">
    <a class="cp-card cp-stat" href="{$WEB_ROOT}/clientarea.php?action=services">
        <span class="cp-stat-label"><i class="fas fa-server"></i> {if $cp}{$cp.t.activeServers}{else}{lang key='navservices'}{/if}</span>
        <span class="cp-stat-value">{$clientsstats.productsnumactive}</span>
    </a>
    <a class="cp-card cp-stat" href="{$WEB_ROOT}/clientarea.php?action=invoices">
        <span class="cp-stat-label"><i class="fas fa-file-invoice-dollar"></i> {if $cp}{$cp.t.unpaid}{else}{lang key='navinvoices'}{/if}</span>
        <span class="cp-stat-value{if $clientsstats.numunpaidinvoices > 0} text-danger{/if}">{$clientsstats.numunpaidinvoices}</span>
    </a>
    <a class="cp-card cp-stat" href="{$WEB_ROOT}/supporttickets.php">
        <span class="cp-stat-label"><i class="fas fa-life-ring"></i> {if $cp}{$cp.t.openTickets}{else}{lang key='navtickets'}{/if}</span>
        <span class="cp-stat-value">{$clientsstats.numactivetickets}</span>
    </a>
    <a class="cp-card cp-stat" href="{$WEB_ROOT}/clientarea.php?action=addfunds">
        <span class="cp-stat-label"><i class="fas fa-wallet"></i> {if $cp}{$cp.t.credit}{else}{lang key='availcreditbal'}{/if}</span>
        <span class="cp-stat-value">{$clientsstats.creditbalance}</span>
    </a>
</div>

{foreach $addons_html as $addon_html}
    <div class="mb-4">{$addon_html}</div>
{/foreach}

{if $captchaError}
    <div class="alert alert-danger">{$captchaError}</div>
{/if}

{if $cp}
    <div class="cp-home-layout">
        <section class="cp-card">
            <header class="cp-card-head">
                <h2>{$cp.t.servers}</h2>
                {if $cp.services}<a href="{$WEB_ROOT}/clientarea.php?action=services">{$cp.t.viewAll}</a>{/if}
            </header>
            {if $cp.services}
                {include file="$template/includes/cp-servers.tpl" servers=$cp.services}
            {else}
                <div class="cp-empty">
                    <i class="fas fa-server"></i>
                    <strong>{$cp.t.noServers}</strong>
                    <span>{$cp.t.noServersSub}</span>
                    <a class="btn btn-primary" href="{$WEB_ROOT}/cart.php"><i class="fas fa-plus"></i> {$cp.t.deploy}</a>
                </div>
            {/if}
        </section>

        <section class="cp-card">
            <header class="cp-card-head">
                <h2>{$cp.t.unpaid}</h2>
                <a href="{$WEB_ROOT}/clientarea.php?action=invoices">{$cp.t.viewAll}</a>
            </header>
            {if $cp.invoices}
                <ul class="cp-list">
                    {foreach $cp.invoices as $invoice}
                        <li>
                            <div>
                                <strong>#{$invoice.number}</strong>
                                <small class="{if $invoice.overdue}text-danger{else}text-muted{/if}">
                                    {if $invoice.overdue}{$cp.t.overdue}{else}{$cp.t.due|replace:'%s':$invoice.due}{/if}
                                </small>
                            </div>
                            <span class="cp-list-amount">{$invoice.total}</span>
                            <a class="btn btn-default btn-sm" href="{$WEB_ROOT}/viewinvoice.php?id={$invoice.id}">{$cp.t.payNow}</a>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="cp-empty cp-empty-sm">
                    <i class="fas fa-check-circle"></i>
                    <span>{$cp.t.allPaid}</span>
                </div>
            {/if}
        </section>
    </div>
{else}
    {* Without the addon data, fall back to the panels other modules add. *}
    {foreach $panels as $item}
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title m-0">{$item->getLabel()}</h3></div>
            {if $item->hasBodyHtml()}<div class="card-body">{$item->getBodyHtml()}</div>{/if}
            {if $item->hasChildren()}
                <div class="list-group list-group-flush">
                    {foreach $item->getChildren() as $childItem}
                        <a href="{$childItem->getUri()}" class="list-group-item list-group-item-action">{$childItem->getLabel()}</a>
                    {/foreach}
                </div>
            {/if}
        </div>
    {/foreach}
{/if}
