<!doctype html>
<html lang="{if $activeLocale.languageCode}{$activeLocale.languageCode}{else}en{/if}">
<head>
    <meta charset="{$charset}" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{if $kbarticle.title}{$kbarticle.title} - {/if}{$pagetitle} - {$companyname}</title>
    {include file="$template/includes/head.tpl"}
    {$headoutput}
</head>
{* Pages that bring their own layout and do not get the Twenty-One sidebar column. *}
{$cpWide = $inShoppingCart || in_array($templatefile, ['homepage', 'clientareahome', 'clientareaproducts', 'login', 'clientregister', 'password-reset-container'])}
{$cpHasSidebar = !$cpWide && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}
<body class="cp-body cp-page-{$templatefile}{if $loggedin} cp-logged-in{/if}{if $cpHasSidebar} cp-with-sidebar{/if}" data-phone-cc-input="{$phoneNumberInputStyle}">
    {if $captcha}{$captcha->getMarkup()}{/if}
    {$headeroutput}

    <div class="cp-backdrop" data-cp-menu-close></div>

    <aside class="cp-sidebar" id="cpSidebar" aria-label="{if $cp}{$cp.t.menu}{else}Menu{/if}">
        <div class="cp-brand">
            <a href="{$WEB_ROOT}/{if $loggedin}clientarea.php{else}index.php{/if}">
                {if $assetLogoPath}
                    <img src="{$assetLogoPath}" alt="{$companyname}">
                {else}
                    <span>{$companyname}</span>
                {/if}
            </a>
            <button type="button" class="cp-icon-btn cp-only-mobile" data-cp-menu-close aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="cp-nav">
            {foreach $primaryNavbar as $item}
                <div class="cp-nav-item{if $item->hasChildren()} has-children{/if}" menuItemName="{$item->getName()}">
                    <a class="cp-nav-link" href="{if $item->getUri()}{$item->getUri()}{else}#{/if}"{if $item->getAttribute('target')} target="{$item->getAttribute('target')}"{/if}>
                        {if $item->hasIcon()}<i class="{$item->getIcon()} fa-fw"></i>{else}<i class="fas fa-circle fa-fw cp-dot"></i>{/if}
                        <span>{$item->getLabel()}</span>
                        {if $item->hasBadge()}<span class="cp-nav-badge">{$item->getBadge()}</span>{/if}
                    </a>
                    {if $item->hasChildren()}
                        <div class="cp-nav-sub">
                            {foreach $item->getChildren() as $childItem}
                                {if !$childItem->getClass() || !in_array($childItem->getClass(), ['dropdown-divider', 'nav-divider'])}
                                    <a class="cp-nav-sublink" href="{$childItem->getUri()}"{if $childItem->getAttribute('target')} target="{$childItem->getAttribute('target')}"{/if}>
                                        {$childItem->getLabel()}
                                        {if $childItem->hasBadge()}<span class="cp-nav-badge">{$childItem->getBadge()}</span>{/if}
                                    </a>
                                {/if}
                            {/foreach}
                        </div>
                    {/if}
                </div>
            {/foreach}
        </nav>

        <div class="cp-sidebar-foot">
            {if $loggedin}
                <div class="dropdown dropup">
                    <button type="button" class="cp-user" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="cp-avatar">{$clientsdetails.firstname|truncate:1:""}{$clientsdetails.lastname|truncate:1:""}</span>
                        <span class="cp-user-text">
                            <strong>{if $client.companyname}{$client.companyname}{else}{$client.fullName}{/if}</strong>
                            <small>{$clientsdetails.email}</small>
                        </span>
                        <i class="fas fa-chevron-up"></i>
                    </button>
                    <div class="dropdown-menu">
                        {foreach $secondaryNavbar as $item}
                            {if $item->hasChildren()}
                                {foreach $item->getChildren() as $childItem}
                                    {if $childItem->getClass() && in_array($childItem->getClass(), ['dropdown-divider', 'nav-divider'])}
                                        <div class="dropdown-divider"></div>
                                    {else}
                                        <a class="dropdown-item" href="{$childItem->getUri()}">{$childItem->getLabel()}</a>
                                    {/if}
                                {/foreach}
                            {/if}
                        {/foreach}
                    </div>
                </div>
            {else}
                <a class="btn btn-primary btn-block" href="{$WEB_ROOT}/login.php">{lang key='login'}</a>
                {if $condlinks.allowClientRegistration}
                    <a class="btn btn-default btn-block" href="{$WEB_ROOT}/register.php">{lang key='register'}</a>
                {/if}
            {/if}
        </div>
    </aside>

    <div class="cp-main">
        <header class="cp-topbar">
            <button type="button" class="cp-icon-btn cp-only-mobile" data-cp-menu-open aria-label="{if $cp}{$cp.t.menu}{else}Menu{/if}">
                <i class="fas fa-bars"></i>
            </button>

            <nav class="cp-breadcrumb" aria-label="breadcrumb">
                {include file="$template/includes/breadcrumb.tpl"}
            </nav>

            <div class="cp-topbar-actions">
                {if $loggedin}
                    <button type="button" class="cp-icon-btn" data-toggle="popover" id="accountNotifications" data-placement="bottom" aria-label="{lang key='notifications'}">
                        <i class="far fa-bell"></i>
                        {if count($clientAlerts) > 0}<span class="cp-dot-badge">{count($clientAlerts)}</span>{/if}
                    </button>
                    <div id="accountNotificationsContent" class="w-hidden">
                        <ul class="client-alerts">
                            {foreach $clientAlerts as $alert}
                                <li>
                                    <a href="{$alert->getLink()}">
                                        <i class="fas fa-fw fa-{if $alert->getSeverity() == 'danger'}exclamation-circle{elseif $alert->getSeverity() == 'warning'}exclamation-triangle{elseif $alert->getSeverity() == 'info'}info-circle{else}check-circle{/if}"></i>
                                        <div class="message">{$alert->getMessage()}</div>
                                    </a>
                                </li>
                            {foreachelse}
                                <li class="none">{lang key='notificationsnone'}</li>
                            {/foreach}
                        </ul>
                    </div>
                {/if}

                {if ($languagechangeenabled && count($locales) > 1) || (!$loggedin && $currencies)}
                    <button type="button" class="cp-icon-btn" data-toggle="modal" data-target="#modalChooseLanguage" aria-label="{lang key='chooselanguage'}">
                        <i class="fas fa-globe"></i>
                    </button>
                {/if}

                <a class="cp-icon-btn" href="{$WEB_ROOT}/cart.php?a=view" aria-label="{lang key='carttitle'}">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="cartItemCount" class="cp-dot-badge{if !$cartitemcount} d-none{/if}">{$cartitemcount}</span>
                </a>
            </div>
        </header>

        {include file="$template/includes/network-issues-notifications.tpl"}

        <main id="main-body" class="cp-content">
            {* "container" too: Twenty-One's script collapses the sidebar cards when it finds no .container. *}
            <div class="cp-container container">
                {include file="$template/includes/validateuser.tpl"}
                {include file="$template/includes/verifyemail.tpl"}

                <div class="{if $cpHasSidebar}row{/if}">
                    {if $cpHasSidebar}
                        <div class="col-lg-4 col-xl-3 cp-page-sidebar">
                            <div class="sidebar">
                                {include file="$template/includes/sidebar.tpl" sidebar=$primarySidebar}
                            </div>
                            {if $secondarySidebar->hasChildren()}
                                <div class="d-none d-lg-block sidebar">
                                    {include file="$template/includes/sidebar.tpl" sidebar=$secondarySidebar}
                                </div>
                            {/if}
                        </div>
                    {/if}
                    <div class="{if $cpHasSidebar}col-lg-8 col-xl-9{/if} primary-content">
