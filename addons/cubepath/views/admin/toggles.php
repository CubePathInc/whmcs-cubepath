<?php
/**
 * Enable/disable list shared by the Locations and Templates pages.
 *
 * @var string|null $apiError
 * @var string      $kind      locations|templates
 * @var string      $intro
 * @var array       $items     API value => label
 * @var string[]    $disabled
 */
include __DIR__ . '/api_error.php';
?>
<p><?= $e($intro) ?></p>

<?php if ($items): ?>
    <form method="post" action="<?= $e($url($kind)) ?>">
        <?= $token ?>
        <input type="hidden" name="action" value="<?= $kind === 'locations' ? 'saveLocations' : 'saveTemplates' ?>">

        <table class="table table-striped" style="max-width: 700px;">
            <thead>
                <tr>
                    <th style="width: 80px;"><?= $e($t('enabled')) ?></th>
                    <th><?= $e($t('name')) ?></th>
                    <th><?= $e($t('apiValue')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $value => $label): ?>
                    <?php $id = 'cp-' . $kind . '-' . md5($value); ?>
                    <tr>
                        <td>
                            <input type="hidden" name="all[]" value="<?= $e($value) ?>">
                            <input type="checkbox" name="enabled[]" id="<?= $id ?>" value="<?= $e($value) ?>"
                                <?= in_array((string)$value, $disabled, true) ? '' : 'checked' ?>>
                        </td>
                        <td><label for="<?= $id ?>" style="font-weight: normal;"><?= $e($label) ?></label></td>
                        <td><code><?= $e($value) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-primary"><?= $e($t('save')) ?></button>
    </form>
<?php endif; ?>
