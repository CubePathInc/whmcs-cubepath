{* Support tickets: status filters and the client's tickets, each row opens the conversation. *}
<link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/css/support.css?v={$versionHash}">

<div class="cp-page-head">
    <div>
        <h1>{if $cp}{$cp.t.support}{else}{lang key='supportticketspagetitle'}{/if}</h1>
        {if $cp}<p>{$cp.t.ticketsSub}</p>{/if}
    </div>
    <a class="btn btn-primary" href="{$WEB_ROOT}/submitticket.php">
        <i class="fas fa-plus"></i>
        {if $cp}{$cp.t.newTicket}{else}{lang key='opennewticket'}{/if}
    </a>
</div>

{if $tickets}
    {$cpCounts = ['open' => 0, 'answered' => 0, 'customer-reply' => 0, 'closed' => 0]}
    {foreach $tickets as $ticket}
        {if isset($cpCounts[$ticket.statusClass])}{$cpCounts[$ticket.statusClass] = $cpCounts[$ticket.statusClass] + 1}{/if}
    {/foreach}

    <div class="cp-filters" role="tablist">
        <button type="button" class="cp-filter active" data-cp-filter="">
            {if $cp}{$cp.t.all}{else}All{/if} <span>{count($tickets)}</span>
        </button>
        <button type="button" class="cp-filter" data-cp-filter="open">{lang key='supportticketsstatusopen'} <span>{$cpCounts.open}</span></button>
        <button type="button" class="cp-filter" data-cp-filter="answered">{lang key='supportticketsstatusanswered'} <span>{$cpCounts.answered}</span></button>
        <button type="button" class="cp-filter" data-cp-filter="customer-reply">{lang key='supportticketsstatuscustomerreply'} <span>{$cpCounts['customer-reply']}</span></button>
        <button type="button" class="cp-filter" data-cp-filter="closed">{lang key='supportticketsstatusclosed'} <span>{$cpCounts.closed}</span></button>
    </div>

    <section class="cp-card">
        <div class="table-responsive">
            <table class="table cp-table cp-tickets">
                <thead>
                    <tr>
                        <th>{lang key='supportticketssubject'}</th>
                        <th class="d-none d-md-table-cell">{if $cp}{$cp.t.service}{else}{lang key='relatedservice'}{/if}</th>
                        <th>{lang key='supportticketsstatus'}</th>
                        <th class="d-none d-sm-table-cell">{lang key='supportticketsticketlastupdated'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $tickets as $ticket}
                        <tr data-cp-status="{$ticket.statusClass}" onclick="clickableSafeRedirect(event, 'viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}', false)">
                            <td>
                                <a class="cp-ticket-subject{if $ticket.unread} unread{/if}" href="{$WEB_ROOT}/viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}">{$ticket.subject|escape}</a>
                                <small>#{$ticket.tid} · {$ticket.department}</small>
                            </td>
                            <td class="d-none d-md-table-cell">
                                {if $cp.ticketServices[$ticket.id]}
                                    <span class="cp-ticket-service"><i class="fas fa-server"></i> {$cp.ticketServices[$ticket.id].label|escape}</span>
                                {else}-{/if}
                            </td>
                            <td>
                                <span class="label status {if is_null($ticket.statusColor)}status-{$ticket.statusClass}"{else}status-custom" style="background-color:{$ticket.statusColor}"{/if}>{$ticket.status|strip_tags}</span>
                            </td>
                            <td class="d-none d-sm-table-cell text-nowrap">{$ticket.lastreply}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="cp-empty cp-empty-sm d-none" data-cp-filter-empty>
            <span>{lang key='norecordsfound'}</span>
        </div>
    </section>

    <script>
        jQuery(function ($) {
            $('[data-cp-filter]').on('click', function () {
                var status = $(this).data('cp-filter');
                $('[data-cp-filter]').removeClass('active');
                $(this).addClass('active');
                var rows = $('.cp-tickets tbody tr');
                rows.each(function () {
                    $(this).toggle(!status || $(this).data('cp-status') === status);
                });
                $('[data-cp-filter-empty]').toggleClass('d-none', rows.filter(':visible').length > 0);
            });
        });
    </script>
{else}
    <section class="cp-card">
        <div class="cp-empty">
            <i class="fas fa-life-ring"></i>
            <strong>{if $cp}{$cp.t.noTickets}{else}{lang key='norecordsfound'}{/if}</strong>
            {if $cp}<span>{$cp.t.noTicketsSub}</span>{/if}
            <a class="btn btn-primary" href="{$WEB_ROOT}/submitticket.php"><i class="fas fa-plus"></i> {if $cp}{$cp.t.newTicket}{else}{lang key='opennewticket'}{/if}</a>
        </div>
    </section>
{/if}
