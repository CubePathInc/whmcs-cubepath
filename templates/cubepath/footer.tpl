{* Same as in header.tpl: header and footer are rendered separately. *}
{$cpWide = $inShoppingCart || in_array($templatefile, ['homepage', 'clientareahome', 'clientareaproducts', 'login', 'clientregister', 'password-reset-container'])}
{$cpHasSidebar = !$cpWide && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}
                    </div>
                    {if $cpHasSidebar && $secondarySidebar->hasChildren()}
                        <div class="d-lg-none sidebar sidebar-secondary col-12">
                            {include file="$template/includes/sidebar.tpl" sidebar=$secondarySidebar}
                        </div>
                    {/if}
                </div>
            </div>
        </main>

        <footer class="cp-footer">
            <span>{lang key="copyrightFooterNotice" year=$date_year company=$companyname}</span>
            <nav>
                <a href="{$WEB_ROOT}/contact.php">{lang key='contactus'}</a>
                {if $acceptTOS}
                    <a href="{$tosURL}" target="_blank">{lang key='ordertos'}</a>
                {/if}
                {if $languagechangeenabled && count($locales) > 1}
                    <a href="#" data-toggle="modal" data-target="#modalChooseLanguage">{$activeLocale.localisedName}{if !$loggedin && $currencies} / {$activeCurrency.code}{/if}</a>
                {/if}
            </nav>
        </footer>
    </div>

    <div id="fullpage-overlay" class="w-hidden">
        <div class="outer-wrapper">
            <div class="inner-wrapper">
                <img src="{$WEB_ROOT}/assets/img/overlay-spinner.svg" alt="">
                <br>
                <span class="msg"></span>
            </div>
        </div>
    </div>

    <div class="modal system-modal fade" id="modalAjax" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">{lang key='close'}</span>
                    </button>
                </div>
                <div class="modal-body">
                    {lang key='loading'}
                </div>
                <div class="modal-footer">
                    <div class="float-left loader">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        {lang key='loading'}
                    </div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        {lang key='close'}
                    </button>
                    <button type="button" class="btn btn-primary modal-submit">
                        {lang key='submit'}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form method="get" action="{$currentpagelinkback}">
        <div class="modal modal-localisation" id="modalChooseLanguage" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>

                        {if $languagechangeenabled && count($locales) > 1}
                            <h5 class="h5 pt-4 pb-3">{lang key='chooselanguage'}</h5>
                            <div class="row item-selector">
                                <input type="hidden" name="language" data-current="{$language}" value="{$language}" />
                                {foreach $locales as $locale}
                                    <div class="col-6 col-md-4">
                                        <a href="#" class="item{if $language == $locale.language} active{/if}" data-value="{$locale.language}">
                                            {$locale.localisedName}
                                        </a>
                                    </div>
                                {/foreach}
                            </div>
                        {/if}
                        {if !$loggedin && $currencies}
                            <p class="h5 pt-4 pb-3">{lang key='choosecurrency'}</p>
                            <div class="row item-selector">
                                <input type="hidden" name="currency" data-current="{$activeCurrency.id}" value="">
                                {foreach $currencies as $selectCurrency}
                                    <div class="col-6 col-md-4">
                                        <a href="#" class="item{if $activeCurrency.id == $selectCurrency.id} active{/if}" data-value="{$selectCurrency.id}">
                                            {$selectCurrency.prefix} {$selectCurrency.code}
                                        </a>
                                    </div>
                                {/foreach}
                            </div>
                        {/if}
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">{lang key='apply'}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {include file="$template/includes/generate-password.tpl"}

    <script src="{$WEB_ROOT}/templates/{$template}/js/cubepath.js?v={$versionHash}"></script>

    {$footeroutput}

</body>
</html>
