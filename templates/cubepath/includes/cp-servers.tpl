{* The client's VPS: hostname, IP, location and status, each row opens its management panel. *}
<div class="table-responsive">
    <table class="table cp-table">
        <thead>
            <tr>
                <th>{$cp.t.hostname}</th>
                <th>{$cp.t.ip}</th>
                <th>{$cp.t.location}</th>
                <th>{$cp.t.status}</th>
                <th class="d-none d-md-table-cell">{$cp.t.renews}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            {foreach $servers as $server}
                <tr onclick="clickableSafeRedirect(event, 'clientarea.php?action=productdetails&amp;id={$server.id}', false)">
                    <td>
                        <div class="cp-server">
                            <span class="cp-server-icon"><i class="fas fa-server"></i></span>
                            <div>
                                <strong>{if $server.hostname}{$server.hostname}{else}{$server.product}{/if}</strong>
                                <small>{$server.product}</small>
                            </div>
                        </div>
                    </td>
                    <td><code class="cp-mono">{if $server.ip}{$server.ip}{else}-{/if}</code></td>
                    <td>
                        {if $server.location}
                            <span class="cp-location">
                                {if $server.flag}<img src="{$cp.flags}{$server.flag}.svg" alt="">{/if}
                                {$server.location}
                            </span>
                        {else}-{/if}
                    </td>
                    <td><span class="label status status-{$server.status}">{lang key="clientarea"|cat:$server.status}</span></td>
                    <td class="d-none d-md-table-cell">
                        {if $server.nextDue}{$server.nextDue}<br><small class="text-muted">{$server.amount}</small>{else}-{/if}
                    </td>
                    <td class="text-right">
                        <a class="btn btn-default btn-sm" href="{$WEB_ROOT}/clientarea.php?action=productdetails&amp;id={$server.id}">{$cp.t.manage}</a>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
