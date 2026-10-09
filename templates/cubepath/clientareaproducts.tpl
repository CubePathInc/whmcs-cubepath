{* My VPS: the CubePath services with their IP and location; other products, if any, below. *}
<div class="cp-page-head">
    <div>
        <h1>{if $cp}{$cp.t.servers}{else}{lang key='clientareaproducts'}{/if}</h1>
    </div>
    <a class="btn btn-primary" href="{$WEB_ROOT}/cart.php">
        <i class="fas fa-plus"></i>
        {if $cp}{$cp.t.deploy}{else}{lang key='navservicesorder'}{/if}
    </a>
</div>

{if $cp}
    <section class="cp-card">
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
{else}
    <section class="cp-card">
        <div class="table-responsive">
            <table class="table cp-table">
                <thead>
                    <tr>
                        <th>{lang key='orderproduct'}</th>
                        <th>{lang key='clientareaaddonpricing'}</th>
                        <th>{lang key='clientareahostingnextduedate'}</th>
                        <th>{lang key='clientareastatus'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $services as $service}
                        <tr onclick="clickableSafeRedirect(event, 'clientarea.php?action=productdetails&amp;id={$service.id}', false)">
                            <td><strong>{$service.product}</strong>{if $service.domain}<br><small class="text-muted">{$service.domain}</small>{/if}</td>
                            <td>{$service.amount} <small class="text-muted">{$service.billingcycle}</small></td>
                            <td>{$service.nextduedate}</td>
                            <td><span class="label status status-{$service.status|strtolower}">{$service.statustext}</span></td>
                        </tr>
                    {foreachelse}
                        <tr><td colspan="4" class="text-center text-muted">{lang key='norecordsfound'}</td></tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </section>
{/if}
