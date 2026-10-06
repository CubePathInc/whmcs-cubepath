<?php
/**
 * @var string|null $apiError
 * @var bool        $tokenSet
 * @var object|null $server
 * @var string      $projectId
 * @var string|null $project
 * @var int         $planCount
 * @var int         $locations
 * @var int         $templates
 * @var int         $products
 */
include __DIR__ . '/api_error.php';
?>
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?= $e($t('connection')) ?></h3></div>
            <table class="table">
                <tr>
                    <th><?= $e($t('apiStatus')) ?></th>
                    <td>
                        <?php if (!$apiError): ?>
                            <span class="label label-success"><?= $e($t('connected')) ?></span>
                        <?php else: ?>
                            <span class="label label-danger"><?= $e($t('disconnected')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?= $e($t('apiToken')) ?></th>
                    <td>
                        <?php if ($server): ?>
                            <a href="configservers.php?action=manage&amp;id=<?= (int)$server->id ?>"><?= $e($server->name) ?></a>
                        <?php elseif ($tokenSet): ?>
                            <?= $e($t('legacyToken')) ?>
                        <?php else: ?>
                            <a href="configservers.php" class="text-danger"><?= $e($t('notConfigured')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?= $e($t('defaultProjectId')) ?></th>
                    <td>
                        <?php if ($projectId !== ''): ?>
                            <?= $e($project !== null ? $project : $projectId) ?>
                        <?php else: ?>
                            <span class="text-danger"><?= $e($t('notConfigured')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?= $e($t('catalog')) ?></h3></div>
            <table class="table">
                <tr><th><?= $e($t('page_creator')) ?></th><td><a href="<?= $e($url('creator')) ?>"><?= (int)$planCount ?> <?= $e($t('plans')) ?></a></td></tr>
                <tr><th><?= $e($t('page_locations')) ?></th><td><a href="<?= $e($url('locations')) ?>"><?= (int)$locations ?></a></td></tr>
                <tr><th><?= $e($t('page_templates')) ?></th><td><a href="<?= $e($url('templates')) ?>"><?= (int)$templates ?></a></td></tr>
                <tr><th><?= $e($t('page_products')) ?></th><td><a href="<?= $e($url('products')) ?>"><?= (int)$products ?></a></td></tr>
            </table>
        </div>
    </div>
</div>
