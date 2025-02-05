<?php
session_start(); // Required to initialize session

require 'db.php';

// Check if user is logged in
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); // Redirect to login page
    exit;
}

// Pagination setup
$limit = 20; // Number of accounts per page
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

// Fetch accounts with pagination and filter by status
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'New';
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE status = :status ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total accounts count for the selected status
$totalAccounts = $pdo->prepare("SELECT COUNT(*) AS total FROM accounts WHERE status = :status");
$totalAccounts->bindValue(':status', $statusFilter, PDO::PARAM_STR);
$totalAccounts->execute();
$totalAccounts = $totalAccounts->fetchColumn();

// Define all statuses explicitly
$allStatuses = ['New', 'Verify', 'Disable', 'Error', 'Running', 'Good', 'Sold'];

// Fetch counts of accounts for each status
$statusCounts = $pdo->query("SELECT status, COUNT(*) AS count FROM accounts GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .dashboard-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 2rem;
            font-weight: bold;
        }
        .dashboard-title i {
            color: #007bff;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1 class="dashboard-title mb-4">
        <i class="bi bi-speedometer2"></i> Account Dashboard
    </h1>

    <!-- Statistics -->
    <div class="row g-4">
        <!-- Total Accounts -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <div class="icon-circle bg-primary text-white mb-3 d-inline-flex justify-content-center align-items-center">
                        <i class="bi bi-people fs-2"></i>
                    </div>
                    <h5 class="card-title">Total Accounts</h5>
                    <p class="card-text fs-4 text-primary"><?= $totalAccounts ?></p>
                </div>
            </div>
        </div>

        <!-- Status cards -->
        <?php foreach ($allStatuses as $status): ?>
            <?php
            // Define color and icon for each status
            $statusConfig = [
                'New' => ['color' => 'bg-warning', 'icon' => 'bi-plus-circle'],
                'Verify' => ['color' => 'bg-info', 'icon' => 'bi-shield-check'],
                'Disable' => ['color' => 'bg-danger', 'icon' => 'bi-x-circle'],
                'Error' => ['color' => 'bg-danger', 'icon' => 'bi-exclamation-circle'],
                'Running' => ['color' => 'bg-primary', 'icon' => 'bi-play-circle'],
                'Good' => ['color' => 'bg-success', 'icon' => 'bi-check-circle'],
                'Sold' => ['color' => 'bg-secondary', 'icon' => 'bi-cart-check'],
            ];

            $color = $statusConfig[$status]['color'] ?? 'bg-secondary';
            $icon = $statusConfig[$status]['icon'] ?? 'bi-question-circle';
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <div class="icon-circle <?= $color ?> text-white mb-3 d-inline-flex justify-content-center align-items-center">
                            <i class="bi <?= $icon ?> fs-2"></i>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($status) ?></h5>
                        <p class="card-text fs-4 <?= $color === 'bg-secondary' ? 'text-muted' : 'text-dark' ?>">
                            <?= $statusCounts[$status] ?? 0 ?> accounts
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Form -->
    <form method="GET" class="mt-4">
        <div class="d-flex align-items-center gap-2">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($allStatuses as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $status === $statusFilter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!-- Bulk Action Form -->
    <form id="bulkActionForm" method="POST" action="bulk_action.php">
        <div class="action-buttons d-flex justify-content-between align-items-center mt-4">
            <div class="form-group d-flex">
                <select name="action" class="form-select me-2" required>
                    <option value="">Select Action</option>
                    <option value="update_status">Update Status</option>
                    <option value="delete">Delete</option>
                </select>
                <select name="new_status" class="form-select">
                    <option value="">Select New Status</option>
                    <?php foreach ($allStatuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>

        <!-- Account Table -->
        <div class="table-responsive mt-4">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Country</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><input type="checkbox" name="selectedAccounts[]" value="<?= $account['id'] ?>"></td>
                        <td><?= htmlspecialchars($account['id']) ?></td>
                        <td><?= htmlspecialchars($account['username']) ?></td>
                        <td><?= htmlspecialchars($account['email']) ?></td>
                        <td><?= htmlspecialchars($account['status']) ?></td>
                        <td><?= htmlspecialchars($account['country']) ?></td>
                        <td>
                            <a href="edit_account.php?id=<?= $account['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="delete_account.php?id=<?= $account['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= ceil($totalAccounts / $limit); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&status=<?= htmlspecialchars($statusFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </form>
</div>

<script>
    document.getElementById('selectAll').addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('input[name="selectedAccounts[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
