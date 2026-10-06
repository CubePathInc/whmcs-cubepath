<?php /** @var \Illuminate\Support\Collection $products */ ?>
<p><?= $e($t('productsIntro')) ?></p>

<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th><?= $e($t('productName')) ?></th>
            <th><?= $e($t('productGroup')) ?></th>
            <th><?= $e($t('plan')) ?></th>
            <th><?= $e($t('paymentType')) ?></th>
            <th><?= $e($t('services')) ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?= (int)$product->id ?></td>
                <td>
                    <?= $e($product->name) ?>
                    <?php if ($product->hidden): ?><span class="label label-default"><?= $e($t('hidden')) ?></span><?php endif; ?>
                    <?php if ($product->retired): ?><span class="label label-warning"><?= $e($t('retired')) ?></span><?php endif; ?>
                </td>
                <td><?= $e($product->group_name) ?></td>
                <td><code><?= $e($product->plan_name) ?></code></td>
                <td><?= $e($t('paytype_' . $product->paytype)) ?></td>
                <td><?= (int)$product->services ?></td>
                <td class="text-right">
                    <a class="btn btn-xs btn-default" href="configproducts.php?action=edit&amp;id=<?= (int)$product->id ?>"><?= $e($t('edit')) ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!count($products)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <?= $e($t('noProducts')) ?> <a href="<?= $e($url('creator')) ?>"><?= $e($t('page_creator')) ?></a>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if (count($products)): ?>
    <form method="post" action="<?= $e($url('products')) ?>">
        <?= $token ?>
        <input type="hidden" name="action" value="syncProducts">
        <button type="submit" class="btn btn-default"><?= $e($t('syncProducts')) ?></button>
        <span class="help-block" style="display: inline; margin-left: 10px;"><?= $e($t('syncProductsHelp')) ?></span>
    </form>
<?php endif; ?>
