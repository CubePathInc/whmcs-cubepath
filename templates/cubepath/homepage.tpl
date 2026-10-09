{* Public homepage: what is on sale (the VPS groups) and why. *}
{if $cp}
    <section class="cp-hero">
        <h1>{$cp.t.heroTitle}</h1>
        <p>{$cp.t.heroSub}</p>
        <div class="cp-hero-actions">
            <a class="btn btn-primary btn-lg" href="#plans">{$cp.t.heroCta}</a>
            {if !$loggedin}
                <a class="btn btn-default btn-lg" href="{$WEB_ROOT}/login.php">{$cp.t.login}</a>
            {/if}
        </div>
    </section>

    {if $cp.groups}
        <section id="plans" class="cp-section">
            <h2 class="cp-section-title">{$cp.t.choosePlan}</h2>
            <div class="cp-grid cp-grid-groups">
                {foreach $cp.groups as $group}
                    <a class="cp-card cp-group-card" href="{$WEB_ROOT}/{$group.url}">
                        <span class="cp-group-icon"><i class="fas fa-server"></i></span>
                        <span class="cp-group-name">{$group.name}</span>
                        {if $group.tagline}<span class="cp-group-tagline">{$group.tagline}</span>{/if}
                        <span class="cp-group-meta">{$cp.t.plansCount|replace:'%d':$group.count}</span>
                        {if $group.from}
                            <span class="cp-group-price">
                                <small>{$cp.t.from}</small>
                                <strong>{$group.from}</strong><small>{$cp.t.perMonth}</small>
                            </span>
                        {/if}
                        <span class="btn btn-default btn-block">{$cp.t.heroCta} <i class="fas fa-arrow-right"></i></span>
                    </a>
                {/foreach}
            </div>
        </section>
    {/if}

    <section class="cp-section">
        <div class="cp-grid cp-grid-features">
            <div class="cp-feature">
                <i class="fas fa-hdd"></i>
                <strong>{$cp.t.featureNvme}</strong>
                <span>{$cp.t.featureNvmeSub}</span>
            </div>
            <div class="cp-feature">
                <i class="fas fa-shield-alt"></i>
                <strong>{$cp.t.featureDdos}</strong>
                <span>{$cp.t.featureDdosSub}</span>
            </div>
            <div class="cp-feature">
                <i class="fas fa-bolt"></i>
                <strong>{$cp.t.featureDeploy}</strong>
                <span>{$cp.t.featureDeploySub}</span>
            </div>
            <div class="cp-feature">
                <i class="fas fa-history"></i>
                <strong>{$cp.t.featureBackups}</strong>
                <span>{$cp.t.featureBackupsSub}</span>
            </div>
        </div>
    </section>
{else}
    {* The CubePath client area is turned off in the addon: show the store groups as Twenty-One does. *}
    <div class="cp-grid cp-grid-groups">
        {foreach $productGroups as $productGroup}
            <a class="cp-card cp-group-card" href="{$productGroup->getRoutePath()}">
                <span class="cp-group-name">{$productGroup->name}</span>
                {if $productGroup->tagline}<span class="cp-group-tagline">{$productGroup->tagline}</span>{/if}
                <span class="btn btn-default btn-block">{lang key='browseProducts'}</span>
            </a>
        {/foreach}
    </div>
{/if}
