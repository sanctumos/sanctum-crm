<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - FreeOpsDAO CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">Error</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Something went wrong</h5>
                        <p class="card-text">
                            <?php echo isset($error_message) ? htmlspecialchars($error_message) : 'An unexpected error occurred.'; ?>
                        </p>
                        <a href="index.php" class="btn btn-primary">Go Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 