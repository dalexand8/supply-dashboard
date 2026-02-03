<?php
// dashboard.php
require 'includes/auth.php';  // Auth + session/db + dotenv

$current_page = basename(__FILE__);

// Locations + colors from .env
$locations = array_filter(array_map('trim', explode(',', $_ENV['OFFICE_LOCATIONS'] ?? '')));

$color_json = $_ENV['OFFICE_COLORS'] ?? '{}';
$location_colors = json_decode($color_json, true);

if (!is_array($location_colors)) {
    $location_colors = [];
}

// Load requests
try {
    $requests_by_location = [];
    foreach ($locations as $loc) {
        $stmt = $pdo->prepare("
            SELECT r.id, r.item_name, r.quantity, r.user_id, r.status, r.created_at, u.username, iv.name as variant_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN item_variants iv ON r.variant_id = iv.id
            WHERE r.location = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$loc]);
        $requests_by_location[$loc] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Dynamic stats
$total_requests = 0;
$status_counts = [];

foreach ($requests_by_location as $loc_requests) {
    foreach ($loc_requests ?? [] as $req) {
        $total_requests++;
        $status = $req['status'] ?? 'Unknown';
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
}

include 'includes/header.php';
?>

<div class="container py-4"> <!-- Narrower centered -->
    <div class="text-center mb-4">
        <h1 class="h2 mb-3">Supply Requests Dashboard</h1>
        <div class="fs-5 mb-3">
            <span class="badge bg-secondary fs-5"><?php echo $total_requests; ?> Total</span>
        </div>
        <div class="d-flex justify-content-center flex-wrap gap-3">
            <?php 
            $priority_order = ['Pending', 'Ordered', 'Backordered', 'Approved', 'Acknowledged', 'Fulfilled'];
            $other_statuses = array_diff_key($status_counts, array_flip($priority_order));
            ksort($other_statuses);

            $ordered_counts = [];
            foreach ($priority_order as $status) {
                if (isset($status_counts[$status]) && $status_counts[$status] > 0) {
                    $ordered_counts[$status] = $status_counts[$status];
                }
            }
            $ordered_counts = array_merge($ordered_counts, $other_statuses);

            foreach ($ordered_counts as $status => $count): 
                if ($count > 0):
                    $class = getStatusClass($status);
            ?>
                <span class="badge <?php echo $class; ?> fs-5"><?php echo $count; ?> <?php echo $status; ?></span>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="accordion" id="officesAccordion">
        <?php foreach ($locations as $loc): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>" aria-expanded="false" aria-controls="collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>" style="background-color: <?php echo $location_colors[$loc] ?? '#6c757d'; ?>;">
                        <?php echo $loc; ?>
                    </button>
                </h2>
                <div id="collapse<?php echo htmlspecialchars(str_replace(' ', '', $loc)); ?>" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($requests_by_location[$loc] ?? [] as $req): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($req['item_name']); ?>
                                            <?php if (!empty($req['variant_name'])): ?>
                                                (<?php echo htmlspecialchars($req['variant_name']); ?>)
                                            <?php endif; ?>
                                            <span class="text-muted fw-normal ms-2">Qty: <?php echo $req['quantity']; ?></span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <?php if ($_SESSION['is_admin'] || ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending')): ?>
                                                <a href="edit.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['is_admin'] || ($_SESSION['user_id'] == $req['user_id'] && $req['status'] == 'Pending')): ?>
                                                <button type="button" class="btn btn-sm bg-secondary delete-request-btn" data-id="<?php echo $req['id']; ?>" data-admin="<?php echo $_SESSION['is_admin'] ? '1' : '0'; ?>">
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <?php if ($req['created_at']): ?>
                                            Submitted: <?php echo date('n/j/Y', strtotime($req['created_at'])); ?>
                                        <?php endif; ?>
                                        by <?php echo htmlspecialchars($req['username']); ?>
                                    </div>
                                    <div>
                                        Status: <span class="badge bg-<?php echo getStatusClass($req['status']); ?>">
                                            <?php echo $req['status']; ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($requests_by_location[$loc])): ?>
                                <li class="list-group-item">No requests yet</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.querySelectorAll('.delete-request-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this request?')) return;

            const requestId = this.dataset.id;
            const isAdmin = this.dataset.admin === '1';
            const url = isAdmin ? 'delete_request.php' : 'delete_my_request.php';

            fetch(url + '?id=' + requestId, {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Silent success: remove row + update stats
                    this.closest('li').remove();

                    // Update total
                    const totalBadge = document.querySelector('.badge.bg-secondary');
                    if (totalBadge) {
                        let total = parseInt(totalBadge.textContent);
                        totalBadge.textContent = --total + ' Total';
                    }

                    // Update status badge
                    const status = data.status || 'Pending';
                    const statusBadges = document.querySelectorAll('.badge');
                    statusBadges.forEach(badge => {
                        if (badge.textContent.includes(status)) {
                            let count = parseInt(badge.textContent.split(' ')[0]);
                            if (count > 1) {
                                badge.textContent = --count + ' ' + status;
                            } else {
                                badge.remove();
                            }
                        }
                    });
                    // No success alert
                } else {
                    alert('Error: ' + (data.error || 'Unknown'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Delete failed');
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>

<?php
function getStatusClass($status) {
    switch ($status) {
        case 'Pending': return 'secondary';
        case 'Acknowledged': return 'info';
        case 'Ordered': return 'primary';
        case 'Fulfilled': return 'success';
        case 'Backordered': return 'danger';
        case 'Unavailable': return 'dark';
        default: return 'secondary';
    }
}
?>