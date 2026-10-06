<?php
/**
 * @var string      $page
 * @var string      $content
 * @var array|null  $flash
 * @var array       $pages
 */
?>
<ul class="nav nav-tabs" style="margin-bottom: 20px;">
    <?php foreach ($pages as $key => $label): ?>
        <li<?= $key === $page ? ' class="active"' : '' ?>>
            <a href="<?= $e($url($key)) ?>"><?= $e($t('page_' . $key)) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($flash): ?>
    <div class="alert alert-<?= $e($flash['type']) ?>">
        <?= $flash['html'] ? $flash['message'] : $e($flash['message']) ?>
    </div>
<?php endif; ?>

<?= $content ?>
