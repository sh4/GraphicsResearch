<?php
use GraphicsResearch\Form;
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Login - Admin</title>
    <link rel="stylesheet" type="text/css" href="<?php echo Router::Path() ?>/css/bootstrap.css">
</head>
<body>

<div class="container">

<h1>Admin Login</h1>

<?php include "_flash_alert.php" ?>

<form method="post" action="<?php echo Router::Path("admin") ?>">
    <?php Form::enableCSRF() ?>
    <label for="login-password" class="">Password:</label>
    <input type="password" id="login-password" class="form-control" name="password">
    <input type="submit" class="form-control">
</form>

</div>

</body>
</html>
