<?php
// dashboard.php
require 'includes/auth.php';  // Auth + session/db

require_once 'vendor/autoload.php';  // Dotenv

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$current_page = basename(__FILE__);

$locations = array_filter(array_map('trim', explode(',', $_ENV['OFFICE_LOCATIONS'] ?? '')));

$color_json = $_ENV['OFFICE_COLORS'] ?? '{}';
$location_colors = json_decode($color_json, true);

if (!is_array($location_colors)) {
    $location_colors = [];
}

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
        $status = $req['status'] ?? 'Pending';
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
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
  <li class="list-group-item request-item py-2 py-sm-3"
    data-request-id="<?= $req['id'] ?>" 
    data-current-status="<?= htmlspecialchars($req['status'] ?? 'Pending') ?>">
    
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="flex-grow-1">
            <strong><?= htmlspecialchars($req['item_name']) ?></strong>
            <?php if (!empty($req['variant_name'])): ?>
                <small class="text-muted">(<?= htmlspecialchars($req['variant_name']) ?>)</small>
            <?php endif; ?>
            <span class="badge status-badge ms-2 <?= getStatusClass($req['status'] ?? 'Pending') ?>">
                <?= htmlspecialchars($req['status'] ?? 'Pending') ?>
            </span>
            <div class="small text-muted mt-1">
                Requested by <?= htmlspecialchars($req['username']) ?> 
                • Qty: <?= $req['quantity'] ?>
                • <?= date('M d, Y g:ia', strtotime($req['created_at'])) ?>
            </div>
        </div>

        <?php if (!empty($_SESSION['is_admin'])): ?>
        <div class="d-flex align-items-center gap-2">
            <!-- Status selector -->
            <select class="form-select form-select-sm status-select" 
                    data-request-id="<?= $req['id'] ?>">
                <option value="Pending"    <?= ($req['status'] ?? '') === 'Pending'    ? 'selected' : '' ?>>Pending</option>
                <option value="Approved"   <?= ($req['status'] ?? '') === 'Approved'   ? 'selected' : '' ?>>Approved</option>
                <option value="Acknowledged" <?= ($req['status'] ?? '') === 'Acknowledged'     ? 'selected' : '' ?>>Acknowledged</option>
                <option value="Denied"     <?= ($req['status'] ?? '') === 'Denied'     ? 'selected' : '' ?>>Denied</option>
                <option value="Fulfilled"  <?= ($req['status'] ?? '') === 'Fulfilled'  ? 'selected' : '' ?>>Fulfilled</option>
                <option value="Ordered"    <?= ($req['status'] ?? '') === 'Ordered'    ? 'selected' : '' ?>>Ordered</option>
                
                <!-- Add more if needed: Backordered, Acknowledged, etc. -->
            </select>

            <!-- Delete button -->
            <button class="btn btn-sm btn-outline-danger delete-request-btn"
                    data-request-id="<?= $req['id'] ?>"
                    title="Delete this request">
                <i class="bi bi-trash"></i> <!-- Bootstrap Icons – make sure they're loaded -->
                <!-- or just text: Delete -->
            </button>
        </div>
        <?php endif; ?>
    </div>
</li>
<?php endforeach; ?>

<?php if (empty($requests_by_location[$loc])): ?>
    <li class="list-group-item request-item py-2 py-sm-3">No requests yet</li>
<?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Status class helper (make sure this matches your PHP function)
function getStatusClass(status) {
    const map = {
        'Pending':      'bg-warning text-dark',
        'Approved':     'bg-info text-white',
        'Acknowledged': 'bg-info text-white',
        'Ordered':      'bg-primary text-white',
        'Fulfilled':    'bg-success text-white',
        'Backordered':  'bg-danger text-white',
        'Denied':       'bg-danger text-white',
        'Unavailable':  'bg-dark text-white'
    };
    return map[status.trim()] || 'bg-secondary text-white';
}

// Update all status badges that have a specific status
function updateAllBadges(oldStatus, newStatus) {
    document.querySelectorAll('.status-badge').forEach(badge => {
        if (badge.textContent.trim() === oldStatus) {
            badge.textContent = newStatus;
            badge.className = `badge status-badge ms-2 ${getStatusClass(newStatus)}`;
        }
    });
}

// Rebuild top summary badges + total
function updateTopCounters(newCounts, newTotal) {
    const container = document.querySelector('.d-flex.justify-content-center.flex-wrap.gap-3');
    if (!container) return;

    // Update total
    if (newTotal !== undefined) {
        const totalBadge = container.querySelector('.bg-secondary');
        if (totalBadge) {
            totalBadge.textContent = `${newTotal} Total`;
        }
    }

    // Remove old status badges (keep total)
    container.querySelectorAll('.badge:not(.bg-secondary)').forEach(b => b.remove());

    // Priority order - same as PHP
    const priority = ['Pending', 'Ordered', 'Backordered', 'Approved', 'Acknowledged', 'Fulfilled'];

    priority.forEach(status => {
        const count = newCounts[status] || 0;
        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5`;
            badge.textContent = `${count} ${status}`;
            container.appendChild(badge);
        }
    });

    // Other statuses
    Object.keys(newCounts).forEach(status => {
        if (!priority.includes(status) && newCounts[status] > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5`;
            badge.textContent = `${newCounts[status]} ${status}`;
            container.appendChild(badge);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const requestId   = this.dataset.requestId;
            const newStatus   = this.value;
            const row         = this.closest('.request-item');
            const badge       = row?.querySelector('.status-badge');

            if (!row || !badge) return;

            // Remember old status before change
            const oldStatus = row.dataset.currentStatus || badge.textContent.trim();

            this.disabled = true;

            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `request_id=${encodeURIComponent(requestId)}&status=${encodeURIComponent(newStatus)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the changed row's badge
                    badge.textContent = newStatus;
                    badge.className = `badge status-badge ms-2 ${getStatusClass(newStatus)}`;

                    // Update ALL other badges that had the old status
                    updateAllBadges(oldStatus, newStatus);

                    // Update top counters
                    if (data.counts && Object.keys(data.counts).length > 0) {
                        updateTopCounters(data.counts, data.total);
                    }

                    // Brief success flash
                    row.style.backgroundColor = 'rgba(40, 167, 69, 0.12)';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 1200);

                    // Update data attribute for next time
                    row.dataset.currentStatus = newStatus;
                } else {
                    alert('Update failed: ' + (data.error || 'Unknown error'));
                    this.value = oldStatus; // revert
                }
            })
            .catch(err => {
                console.error(err);
                alert('Communication error');
                this.value = oldStatus;
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
});
// Delete request handler
document.querySelectorAll('.delete-request-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const requestId = this.dataset.requestId;
        const row = this.closest('.request-item');

        if (!confirm('Are you sure you want to delete this request?\nThis action cannot be undone.')) {
            return;
        }

        // Visual feedback
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('delete_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${encodeURIComponent(requestId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove row from DOM
                row.remove();

                // Update top counters
                if (data.counts && Object.keys(data.counts).length > 0) {
                    updateTopCounters(data.counts, data.total);
                }

                // Optional small success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                alertDiv.innerHTML = `
                    Request deleted successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container').prepend(alertDiv);
                setTimeout(() => alertDiv.remove(), 5000);
            } else {
                alert('Delete failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error while deleting');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-trash"></i>';
        });
    });
});
</script>


<?php
function getStatusClass($status) {
    $status = trim($status ?: 'Pending');
    
    return match($status) {
        'Pending'      => 'bg-warning text-dark',
        'Approved'     => 'bg-info text-white',
        'Acknowledged' => 'bg-info text-white',
        'Ordered'      => 'bg-primary text-white',
        'Fulfilled'    => 'bg-success text-white',
        'Backordered'  => 'bg-danger text-white',
        'Denied'       => 'bg-danger text-white',
        'Unavailable'  => 'bg-dark text-white',
        default        => 'bg-secondary text-white',
    };
}
?>