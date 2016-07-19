<?php foreach (["success", "info", "warning", "danger"] as $alertType): ?>
    <?php if ($message = Router::Flash($alertType)): ?>
        <div class="alert alert-<?php echo $alertType ?>" role="alert"><?php echo $message ?></div>
    <?php endif ?>
<?php endforeach ?>
