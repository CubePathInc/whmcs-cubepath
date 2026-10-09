{* A ticket: its details, the conversation oldest first and the reply form. *}
<link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/css/support.css?v={$versionHash}">

{if $invalidTicketId}
    {include file="$template/includes/alert.tpl" type="danger" title="{lang key='thereisaproblem'}" msg="{lang key='supportticketinvalid'}" textcenter=true}
{else}
    <div class="cp-page-head">
        <div class="cp-ticket-title">
            <a class="cp-back" href="{$WEB_ROOT}/supporttickets.php"><i class="fas fa-arrow-left"></i> {if $cp}{$cp.t.backToTickets}{else}{lang key='supportticketspagetitle'}{/if}</a>
            <h1>{$subject|escape}</h1>
            <p>#{$tid} · {$department}{if $cp} · {$cp.t.opened|replace:'%s':$date}{/if}</p>
        </div>
        <div class="cp-ticket-actions">
            <a class="btn btn-default" href="#ticketReplyContainer" onclick="smoothScroll('#ticketReplyContainer'); return false;">
                <i class="fas fa-reply"></i> {lang key='supportticketsreply'}
            </a>
            {if $showCloseButton && !$closedticket}
                <a class="btn btn-default" id="closeTicket" href="?tid={$tid}&amp;c={$c}&amp;closeticket=true&amp;token={$token}">
                    <i class="fas fa-check"></i> {lang key='supportticketsclose'}
                </a>
            {/if}
        </div>
    </div>

    {if $errormessage}
        {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
    {/if}

    <div class="cp-ticket-meta cp-card">
        <div>
            <span>{lang key='supportticketsstatus'}</span>
            <span class="label status status-{if $cp.ticket.statusClass}{$cp.ticket.statusClass}{else}custom{/if}">{$status|strip_tags}</span>
        </div>
        <div>
            <span>{lang key='supportticketspriority'}</span>
            <strong>{$urgency}</strong>
        </div>
        <div>
            <span>{if $cp}{$cp.t.service}{else}{lang key='relatedservice'}{/if}</span>
            {if $cp.ticket.service}
                <a href="{$WEB_ROOT}/clientarea.php?action=productdetails&amp;id={$cp.ticket.service.id}"><i class="fas fa-server"></i> {$cp.ticket.service.label|escape}</a>
            {else}
                <strong>-</strong>
            {/if}
        </div>
        <div>
            <span>{lang key='supportticketsticketlastupdated'}</span>
            {foreach $ascreplies as $reply}{if $reply@last}<strong>{$reply.date}</strong>{/if}{/foreach}
        </div>
    </div>

    <div class="cp-thread">
        {foreach $ascreplies as $reply}
            <article class="cp-message{if $reply.admin} staff{/if}">
                <span class="cp-avatar">{if $reply.admin}<i class="fas fa-headset"></i>{else}{$reply.requestor.name|truncate:1:""|upper}{/if}</span>
                <div class="cp-message-body">
                    <header>
                        <strong>{$reply.requestor.name}</strong>
                        <span class="cp-requestor">{if $cp}{if $reply.admin}{$cp.t.staff}{else}{$cp.t.you}{/if}{else}{lang key='support.requestor.'|cat:$reply.requestor.type_normalised}{/if}</span>
                        <time>{$reply.date}</time>
                    </header>
                    <div class="cp-message-text markdown-content">
                        {$reply.message}
                    </div>
                    {if $reply.attachments}
                        <div class="cp-attachments">
                            {foreach $reply.attachments as $num => $attachment}
                                {if $reply.attachments_removed}
                                    <span class="cp-attachment removed"><i class="far fa-file"></i> {$attachment}</span>
                                {else}
                                    <a class="cp-attachment" href="dl.php?type={if $reply.id}ar&amp;id={$reply.id}{else}a&amp;id={$id}{/if}&amp;i={$num}"><i class="far fa-file"></i> {$attachment}</a>
                                {/if}
                            {/foreach}
                            {if $reply.attachments_removed}<small class="text-muted">{lang key='support.attachmentsRemoved'}</small>{/if}
                        </div>
                    {/if}
                    {if $reply.id && $reply.admin && $ratingenabled}
                        <div class="clearfix cp-rating">
                            {if $reply.rating}
                                <div class="rating-done">
                                    {for $rating=1 to 5}
                                        <span class="star{if (5 - $reply.rating) < $rating} active{/if}"></span>
                                    {/for}
                                    <div class="rated">{lang key='ticketreatinggiven'}</div>
                                </div>
                            {else}
                                <div class="rating" ticketid="{$tid}" ticketkey="{$c}" ticketreplyid="{$reply.id}">
                                    <span class="star" rate="5"></span>
                                    <span class="star" rate="4"></span>
                                    <span class="star" rate="3"></span>
                                    <span class="star" rate="2"></span>
                                    <span class="star" rate="1"></span>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            </article>
        {/foreach}
    </div>

    <section class="cp-card cp-ticket-form d-print-none" id="ticketReplyContainer">
        <h2>{lang key='supportticketsreply'}</h2>
        {if $closedticket}
            <p class="text-muted">{lang key='supportticketclosedmsg'}</p>
        {/if}

        <form method="post" action="{$smarty.server.PHP_SELF}?tid={$tid}&amp;c={$c}&amp;postreply=true" enctype="multipart/form-data" role="form" id="frmReply">
            {if !$loggedin}
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="inputName">{lang key='supportticketsclientname'}</label>
                        <input class="form-control" type="text" name="replyname" id="inputName" value="{$replyname}">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="inputEmail">{lang key='supportticketsclientemail'}</label>
                        <input class="form-control" type="text" name="replyemail" id="inputEmail" value="{$replyemail}">
                    </div>
                </div>
            {/if}

            <div class="form-group">
                <label for="inputMessage" class="sr-only">{lang key='contactmessage'}</label>
                <textarea name="replymessage" id="inputMessage" rows="8" class="form-control markdown-editor" data-auto-save-name="ctr{$tid}">{$replymessage}</textarea>
            </div>

            {include file="$template/includes/cp-ticket-attachments.tpl"}

            <div class="cp-form-actions">
                <button class="btn btn-primary" type="submit" name="save" value="1">
                    <i class="fas fa-paper-plane"></i> {lang key='supportticketsticketsubmit'}
                </button>
            </div>
        </form>
    </section>
{/if}
