<?php /** @var string|null $apiError */ ?>
<?php if ($apiError): ?>
    <div class="alert alert-danger">
        <strong><?= $e($t('apiUnavailable')) ?></strong> <?= $e($apiError) ?>
        <a href="configaddonmods.php#cubepath"><?= $e($t('checkConfiguration')) ?></a>
    </div>
<?php endif; ?>
