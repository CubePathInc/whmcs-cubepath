<?php
/**
 * @var string|null $apiError
 * @var string      $projectId
 * @var array       $plans
 * @var string[]    $existing
 * @var array       $groups      id => name
 * @var \Illuminate\Support\Collection $currencies
 */
include __DIR__ . '/api_error.php';
?>
<?php if ($projectId === ''): ?>
    <div class="alert alert-warning"><?= $e($t('projectIdMissing')) ?></div>
<?php endif; ?>

<p><?= $e($t('creatorIntro')) ?></p>

<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title"><?= $e($t('createSingle')) ?></h3></div>
    <div class="panel-body">
        <form method="post" action="<?= $e($url('creator')) ?>" class="form-horizontal">
            <?= $token ?>
            <input type="hidden" name="action" value="createProduct">

            <div class="form-group">
                <label class="col-sm-2 control-label" for="cp-plan"><?= $e($t('plan')) ?></label>
                <div class="col-sm-10">
                    <select name="plan" id="cp-plan" class="form-control" required>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= $e($plan['plan_name']) ?>" data-price="<?= $e(number_format($plan['price_per_month'], 2, '.', '')) ?>">
                                <?= $e(\CubePath\WHMCS\Addon\Catalog::describePlan($plan)) ?>
                                (<?= $e(sprintf('$%.2f/mo', $plan['price_per_month'])) ?>)
                                <?= $plan['available'] ? '' : $e(' [' . $t('outOfStock') . ']') ?>
                                <?= in_array($plan['plan_name'], $existing, true) ? $e(' [' . $t('alreadyExists') . ']') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="cp-name"><?= $e($t('productName')) ?></label>
                <div class="col-sm-10">
                    <input type="text" name="name" id="cp-name" class="form-control" placeholder="<?= $e($t('productNamePlaceholder')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="cp-gid"><?= $e($t('productGroup')) ?></label>
                <div class="col-sm-10">
                    <select name="gid" id="cp-gid" class="form-control cp-group" required>
                        <?php foreach ($groups as $id => $name): ?>
                            <option value="<?= (int)$id ?>"><?= $e($name) ?></option>
                        <?php endforeach; ?>
                        <option value="new"><?= $e($t('newGroupOption')) ?></option>
                    </select>
                    <input type="text" name="new_group" class="form-control cp-new-group" style="margin-top: 5px;"
                           placeholder="<?= $e($t('newGroupName')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label"><?= $e($t('paymentType')) ?></label>
                <div class="col-sm-10">
                    <?php foreach (array('recurring', 'onetime', 'free') as $i => $type): ?>
                        <label class="radio-inline">
                            <input type="radio" name="paytype" value="<?= $type ?>"<?= $i === 0 ? ' checked' : '' ?>> <?= $e($t('paytype_' . $type)) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" id="cp-pricing">
                <label class="col-sm-2 control-label"><?= $e($t('monthlyPrice')) ?></label>
                <div class="col-sm-10">
                    <?php foreach ($currencies as $currency): ?>
                        <div class="input-group" style="max-width: 220px; margin-bottom: 5px;">
                            <span class="input-group-addon"><?= $e($currency->code) ?></span>
                            <input type="text" name="price[<?= (int)$currency->id ?>]" class="form-control cp-price"
                                   data-currency="<?= $e($currency->code) ?>" value="0.00" inputmode="decimal">
                        </div>
                    <?php endforeach; ?>
                    <p class="help-block"><?= $e($t('priceHelp')) ?></p>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" class="btn btn-primary"<?= $plans ? '' : ' disabled' ?>><?= $e($t('createProduct')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title"><?= $e($t('createAll')) ?></h3></div>
    <div class="panel-body">
        <p><?= $e($t('createAllIntro')) ?></p>
        <form method="post" action="<?= $e($url('creator')) ?>" class="form-inline"
              onsubmit="return confirm(<?= $e(json_encode($t('createAllConfirm'))) ?>);">
            <?= $token ?>
            <input type="hidden" name="action" value="createAllProducts">
            <div class="form-group">
                <label for="cp-all-gid"><?= $e($t('productGroup')) ?></label>
                <select name="gid" id="cp-all-gid" class="form-control cp-group" required>
                    <?php foreach ($groups as $id => $name): ?>
                        <option value="<?= (int)$id ?>"><?= $e($name) ?></option>
                    <?php endforeach; ?>
                    <option value="new"><?= $e($t('newGroupOption')) ?></option>
                </select>
                <input type="text" name="new_group" class="form-control cp-new-group" placeholder="<?= $e($t('newGroupName')) ?>">
            </div>
            <div class="form-group">
                <label for="cp-markup"><?= $e($t('markup')) ?></label>
                <div class="input-group" style="width: 120px;">
                    <input type="text" name="markup" id="cp-markup" class="form-control" value="0" inputmode="decimal">
                    <span class="input-group-addon">%</span>
                </div>
            </div>
            <button type="submit" class="btn btn-default"<?= $plans ? '' : ' disabled' ?>><?= $e($t('createAllButton')) ?></button>
        </form>
    </div>
</div>

<script>
jQuery(function ($) {
    function prefillUsd() {
        var price = $('#cp-plan option:selected').data('price');
        $('.cp-price[data-currency="USD"]').val(price);
    }
    function togglePricing() {
        $('#cp-pricing').toggle($('input[name="paytype"]:checked').val() !== 'free');
    }
    function toggleNewGroup() {
        $('.cp-group').each(function () {
            var isNew = $(this).val() === 'new';
            $(this).siblings('.cp-new-group').toggle(isNew).prop('required', isNew);
        });
    }
    $('#cp-plan').on('change', prefillUsd);
    $('.cp-group').on('change', toggleNewGroup);
    $('input[name="paytype"]').on('change', togglePricing);
    prefillUsd();
    togglePricing();
    toggleNewGroup();
});
</script>
