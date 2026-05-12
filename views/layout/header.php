<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Ambassador Club Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>SAC Management System</h1>
            <nav>
                <a href="?action=list_clubs">Clubs</a>
                <!-- Future links for Events and Tasks -->
            </nav>
        </div>
    </header>
    <main class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
