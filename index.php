<?php include "config/db.php";?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0; url=<?php echo SITE_PATH?>form.php">
    <title>Redirecting...</title>
    <script>
    window.location.replace("<?php echo SITE_PATH?>form.php");
</script>

</head>
<body>
    <p>If you are not redirected automatically, <a href="<?php echo SITE_PATH?>form.php">click here</a>.</p>
</body>
</html>
