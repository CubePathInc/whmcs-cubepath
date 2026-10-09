{* New ticket form. Field names and ids are the ones Twenty-One's scripts and submitticket.php expect. *}
<link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/css/support.css?v={$versionHash}">

<div class="cp-page-head">
    <div>
        <h1>{lang key="createNewSupportRequest"}</h1>
        {if $cp}<p>{$cp.t.newTicketSub}</p>{/if}
    </div>
</div>

<form method="post" action="{$smarty.server.PHP_SELF}?step=3" enctype="multipart/form-data" role="form">
    <section class="cp-card cp-ticket-form">
        {if $errormessage}
            {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
        {/if}

        {if !$loggedin}
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="inputName">{lang key='supportticketsclientname'}</label>
                    <input type="text" name="name" id="inputName" value="{$name}" class="form-control" />
                </div>
                <div class="form-group col-md-6">
                    <label for="inputEmail">{lang key='supportticketsclientemail'}</label>
                    <input type="email" name="email" id="inputEmail" value="{$email}" class="form-control" />
                </div>
            </div>
        {/if}

        <div class="form-group">
            <label for="inputSubject">{lang key='supportticketsticketsubject'}</label>
            <input type="text" name="subject" id="inputSubject" value="{$subject}" class="form-control" />
        </div>

        <div class="row">
            <div class="form-group {if $relatedservices}col-md-4{else}col-md-8{/if}">
                <label for="inputDepartment">{lang key='supportticketsdepartment'}</label>
                <select name="deptid" id="inputDepartment" class="form-control" onchange="refreshCustomFields(this)">
                    {foreach $departments as $department}
                        <option value="{$department.id}"{if $department.id eq $deptid} selected="selected"{/if}>{$department.name}</option>
                    {/foreach}
                </select>
            </div>
            {if $relatedservices}
                <div class="form-group col-md-5">
                    <label for="inputRelatedService">{if $cp}{$cp.t.service}{else}{lang key='relatedservice'}{/if}</label>
                    <select name="relatedservice" id="inputRelatedService" class="form-control">
                        <option value="">{lang key='none'}</option>
                        {foreach $relatedservices as $relatedservice}
                            <option value="{$relatedservice.id}"{if $relatedservice.id eq $selectedservice} selected="selected"{/if}>{$relatedservice.name} ({$relatedservice.status})</option>
                        {/foreach}
                    </select>
                </div>
            {/if}
            <div class="form-group {if $relatedservices}col-md-3{else}col-md-4{/if}">
                <label for="inputPriority">{lang key='supportticketspriority'}</label>
                <select name="urgency" id="inputPriority" class="form-control">
                    <option value="High"{if $urgency eq "High"} selected="selected"{/if}>{lang key='supportticketsticketurgencyhigh'}</option>
                    <option value="Medium"{if $urgency eq "Medium" || !$urgency} selected="selected"{/if}>{lang key='supportticketsticketurgencymedium'}</option>
                    <option value="Low"{if $urgency eq "Low"} selected="selected"{/if}>{lang key='supportticketsticketurgencylow'}</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="inputMessage">{lang key='contactmessage'}</label>
            <textarea name="message" id="inputMessage" rows="10" class="form-control markdown-editor" data-auto-save-name="client_ticket_open">{$message}</textarea>
        </div>

        {include file="$template/includes/cp-ticket-attachments.tpl"}

        <div id="customFieldsContainer">
            {include file="$template/supportticketsubmit-customfields.tpl"}
        </div>

        <div id="autoAnswerSuggestions" class="w-hidden"></div>

        <div class="text-center">
            {include file="$template/includes/captcha.tpl"}
        </div>

        <div class="cp-form-actions">
            <a href="{$WEB_ROOT}/supporttickets.php" class="btn btn-default">{lang key='cancel'}</a>
            <button type="submit" id="openTicketSubmit" class="btn btn-primary disable-on-click{$captcha->getButtonClass($captchaForm)}">
                {lang key='supportticketsticketsubmit'}
            </button>
        </div>
    </section>
</form>

{if $kbsuggestions}
    <script>
        jQuery(document).ready(function() {
            getTicketSuggestions();
        });
    </script>
{/if}
