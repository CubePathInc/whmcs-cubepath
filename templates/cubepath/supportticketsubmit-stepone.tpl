{* New ticket, first step: the department. The related service, if any, is carried to the next step. *}
<link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/css/support.css?v={$versionHash}">

<div class="cp-page-head">
    <div>
        <h1>{lang key="createNewSupportRequest"}</h1>
        <p>{if $cp}{$cp.t.departmentSub}{else}{lang key='supportticketsheader'}{/if}</p>
    </div>
</div>

{$cpRelated = ''}
{if $smarty.get.relatedservice}{$cpRelated = "&amp;relatedservice="|cat:($smarty.get.relatedservice|escape:'url')}{/if}

<div class="cp-grid cp-departments">
    {foreach $departments as $department}
        <a class="cp-card cp-department" href="{$smarty.server.PHP_SELF}?step=2&amp;deptid={$department.id}{$cpRelated}">
            <span class="cp-department-icon"><i class="fas fa-envelope"></i></span>
            <span>
                <strong>{$department.name}</strong>
                {if $department.description}<small>{$department.description}</small>{/if}
            </span>
            <i class="fas fa-chevron-right"></i>
        </a>
    {foreachelse}
        {include file="$template/includes/alert.tpl" type="info" msg="{lang key='nosupportdepartments'}" textcenter=true}
    {/foreach}
</div>
