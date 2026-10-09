<link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/css/support.css?v={$versionHash}">

<section class="cp-card">
    <div class="cp-empty">
        <i class="fas fa-check-circle cp-ticket-ok"></i>
        <strong>{lang key='supportticketsticketcreated'} <a id="ticket-number" href="{$WEB_ROOT}/viewticket.php?tid={$tid}&amp;c={$c}">#{$tid}</a></strong>
        <span>{lang key='supportticketsticketcreateddesc'}</span>
        <a class="btn btn-primary" href="{$WEB_ROOT}/viewticket.php?tid={$tid}&amp;c={$c}">
            {lang key='continue'} <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>
