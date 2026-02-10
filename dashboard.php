<?php
// dashboard.php
require 'includes/auth.php';  // Auth + session/db

require_once 'vendor/autoload.php';  // Dotenv

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$current_page = basename(__FILE__);

$locations = array_filter(array_map('trim', explode(',', $_ENV['OFFICE_LOCATIONS'] ?? '')));

$color_json = $_ENV['OFFICE_COLORS'] ?? '{}';
$location_colors = json_decode($color_json, true) ?: [];

try {
    $requests_by_location = [];
    foreach ($locations as $loc) {
        $stmt = $pdo->prepare("
            SELECT r.id, r.item_name, r.quantity, r.user_id, r.status, r.created_at, u.username, iv.name as variant_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN item_variants iv ON r.variant_id = iv.id
            WHERE r.location = ?
            ORDER BY r.created_at DESC
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
    foreach ($loc_requests as $req) {
        $total_requests++;
        $status = $req['status'] ?? 'Pending';
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-5">
        <h1 class="h2 mb-3">Supply Requests Dashboard</h1>

        <!-- Centered total and badges section -->
        <div class="d-flex flex-column align-items-center gap-3 mb-4">
            <!-- Total count - prominent and centered -->
            <span class="badge bg-secondary fs-4 px-5 py-3" id="total-count">
                <?= $total_requests ?> Total
            </span>

            <!-- Status badges - centered below total -->
            <div class="d-flex justify-content-center flex-wrap gap-3" id="status-badges-container">
                <?php
                $priority_order = ['Pending', 'Ordered', 'Backordered', 'Approved', 'Acknowledged', 'Fulfilled'];
                $ordered_counts = [];
                foreach ($priority_order as $status) {
                    if (!empty($status_counts[$status])) {
                        $ordered_counts[$status] = $status_counts[$status];
                    }
                }
                foreach ($status_counts as $status => $count) {
                    if (!in_array($status, $priority_order) && $count > 0) {
                        $ordered_counts[$status] = $count;
                    }
                }

                foreach ($ordered_counts as $status => $count):
                    $class = getStatusClass($status);
                ?>
                    <span class="badge <?= $class ?> fs-5 px-4 py-2 status-badge">
                        <?= $count ?> <?= htmlspecialchars($status) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Floating bulk delete button (admin only) -->
    <?php if (!empty($_SESSION['is_admin'])): ?>
    <div class="position-fixed bottom-0 end-0 m-4" style="z-index: 1050; display: none;" id="bulk-actions">
        <button class="btn btn-danger shadow-lg px-4 py-2 rounded-pill" id="bulk-delete-btn">
            <i class="bi bi-trash me-2"></i> Delete <span id="selected-count">0</span> selected
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($locations)): ?>
        <div class="alert alert-warning">No locations defined in .env</div>
    <?php elseif (empty($requests_by_location)): ?>
        <div class="alert alert-info">No requests found</div>
    <?php else: ?>
        <div class="accordion" id="officesAccordion">
            <?php 
            foreach ($locations as $loc): 
                $loc_requests = $requests_by_location[$loc] ?? [];
            ?>
                <div class="accordion-item border-0 shadow-sm mb-3 rounded overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-bold text-white" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapse<?= htmlspecialchars(str_replace(' ', '', $loc)) ?>" 
                                aria-expanded="<?= $first ? 'true' : 'false' ?>"
                                style="background-color: <?= $location_colors[$loc] ?? '#343a40' ?>;">
                            <?= htmlspecialchars($loc) ?>
                        </button>
                    </h2>

                    <div id="collapse<?= htmlspecialchars(str_replace(' ', '', $loc)) ?>" 
                         class="accordion-collapse <?= $first ? 'show' : 'collapse' ?>">
                        <div class="accordion-body p-0 bg-dark">
                            <?php if (!empty($_SESSION['is_admin']) && !empty($loc_requests)): ?>
                                <div class="p-3 border-bottom border-secondary d-flex justify-content-end">
                                    <div class="form-check" onclick="event.stopPropagation()">
                                        <input class="form-check-input select-all-location" 
                                               type="checkbox" 
                                               id="select-all-<?= htmlspecialchars(str_replace(' ', '', $loc)) ?>"
                                               data-location="<?= htmlspecialchars($loc) ?>">
                                        <label class="form-check-label small text-white" 
                                               for="select-all-<?= htmlspecialchars(str_replace(' ', '', $loc)) ?>">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($loc_requests)): ?>
                                <div class="p-4 text-center text-muted">No requests</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($loc_requests as $req): 
                                        $is_pending = ($req['status'] ?? 'Pending') === 'Pending';
                                        $is_own_request = $req['user_id'] == ($_SESSION['user_id'] ?? 0);
                                        $can_edit = $is_pending && $is_own_request;
                                    ?>
                                        <li class="list-group-item bg-dark text-white border-bottom border-secondary py-3" 
                                            data-request-id="<?= $req['id'] ?>">
                                            <div class="d-flex align-items-start gap-3 flex-wrap flex-md-nowrap">
                                                <?php if (!empty($_SESSION['is_admin'])): ?>
                                                    <div class="form-check pt-1" onclick="event.stopPropagation()">
                                                        <input class="form-check-input request-checkbox" 
                                                               type="checkbox" 
                                                               value="<?= $req['id'] ?>" 
                                                               data-location="<?= htmlspecialchars($loc) ?>">
                                                    </div>
                                                <?php endif; ?>

                                                <div class="flex-grow-1">
                                                    <div class="fw-bold mb-1">
                                                        <?= htmlspecialchars($req['item_name']) ?>
                                                        <?php if (!empty($req['variant_name'])): ?>
                                                            <small class="text-muted ms-2">(<?= htmlspecialchars($req['variant_name']) ?>)</small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <span class="badge status-badge <?= getStatusClass($req['status'] ?? 'Pending') ?> me-2" id="badge-<?= $req['id'] ?>">
                                                        <?= htmlspecialchars($req['status'] ?? 'Pending') ?>
                                                    </span>

                                                    <div class="small text-muted mt-1">
                                                        Requested by <strong><?= htmlspecialchars($req['username']) ?></strong> 
                                                        • Qty: <strong id="qty-<?= $req['id'] ?>"><?= $req['quantity'] ?></strong>
                                                    </div>

                                                    <div class="small text-muted mt-1 opacity-75">
                                                        <?= date('M d, Y  g:ia', strtotime($req['created_at'])) ?>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                                                    <?php if ($can_edit): ?>
                                                        <!-- User can edit quantity -->
                                                        <div class="input-group input-group-sm" style="width: 120px;">
                                                            <input type="number" class="form-control bg-dark text-white border-secondary user-qty-input" 
                                                                   value="<?= $req['quantity'] ?>" 
                                                                   min="1" 
                                                                   data-request-id="<?= $req['id'] ?>">
                                                            <button class="btn btn-outline-success user-update-qty-btn" 
                                                                    data-request-id="<?= $req['id'] ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                        </div>

                                                        <!-- User can delete own pending request -->
                                                        <button class="btn btn-sm btn-outline-danger user-delete-btn"
                                                                data-request-id="<?= $req['id'] ?>"
                                                                title="Delete this request">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if (!empty($_SESSION['is_admin'])): ?>
                                                        <select class="form-select form-select-sm status-select bg-dark text-white border-secondary" 
                                                                data-request-id="<?= $req['id'] ?>">
                                                            <option value="Pending"    <?= ($req['status'] ?? '') === 'Pending'    ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Approved"   <?= ($req['status'] ?? '') === 'Approved'   ? 'selected' : '' ?>>Approved</option>
                                                            <option value="Acknowledged" <?= ($req['status'] ?? '') === 'Acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                                                            <option value="Denied"     <?= ($req['status'] ?? '') === 'Denied'     ? 'selected' : '' ?>>Denied</option>
                                                            <option value="Fulfilled"  <?= ($req['status'] ?? '') === 'Fulfilled'  ? 'selected' : '' ?>>Fulfilled</option>
                                                            <option value="Ordered"    <?= ($req['status'] ?? '') === 'Ordered'    ? 'selected' : '' ?>>Ordered</option>
                                                        </select>

                                                        <button class="btn btn-sm btn-outline-danger delete-request-btn"
                                                                data-request-id="<?= $req['id'] ?>"
                                                                title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php $first = false; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Helpers
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
    return map[status?.trim()] || 'bg-secondary text-white';
}

function updateTopCounters(newCounts, newTotal) {
    const totalEl = document.getElementById('total-count');
    if (totalEl && newTotal !== undefined) {
        totalEl.textContent = `${newTotal} Total`;
    }

    const container = document.getElementById('status-badges-container');
    if (!container) return;

    container.innerHTML = '';

    const priority = ['Pending', 'Ordered', 'Backordered', 'Approved', 'Acknowledged', 'Fulfilled'];

    priority.forEach(status => {
        const count = newCounts[status] || 0;
        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5 px-4 py-2 status-badge`;
            badge.textContent = `${count} ${status}`;
            container.appendChild(badge);
        }
    });

    Object.keys(newCounts).forEach(status => {
        if (!priority.includes(status) && newCounts[status] > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5 px-4 py-2 status-badge`;
            badge.textContent = `${newCounts[status]} ${status}`;
            container.appendChild(badge);
        }
    });
}

function updateBulkUI() {
    const checkedCount = document.querySelectorAll('.request-checkbox:checked').length;
    
    const bulkContainer = document.getElementById('bulk-actions');
    const countElement  = document.getElementById('selected-count');

    if (bulkContainer) {
        bulkContainer.style.display = checkedCount > 0 ? 'block' : 'none';
    }

    if (countElement) {
        countElement.textContent = checkedCount;
    }
}

function findRequestRow(id) {
    return document.querySelector(`[data-request-id="${id}"]`);
}

// ────────────────────────────────────────────────
// User: Update quantity
// ────────────────────────────────────────────────
document.querySelectorAll('.user-update-qty-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const requestId = this.dataset.requestId;
        const input = document.querySelector(`.user-qty-input[data-request-id="${requestId}"]`);
        const newQty = parseInt(input.value, 10);

        if (isNaN(newQty) || newQty < 1) {
            alert('Please enter a valid quantity (1 or more)');
            return;
        }

        const originalBtnHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('update_request_qty.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${encodeURIComponent(requestId)}&quantity=${newQty}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`qty-${requestId}`).textContent = newQty;
                input.style.backgroundColor = '#1e3a2f';
                setTimeout(() => input.style.backgroundColor = '', 800);
            } else {
                alert(data.error || 'Failed to update quantity');
                input.value = data.old_quantity || input.value;
            }
        })
        .catch(() => {
            alert('Could not update quantity');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = originalBtnHtml;
        });
    });
});

// ────────────────────────────────────────────────
// User: Delete own pending request
// ────────────────────────────────────────────────
document.querySelectorAll('.user-delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const requestId = this.dataset.requestId;
        const row = document.querySelector(`[data-request-id="${requestId}"]`);

        if (!confirm('Delete this request? This cannot be undone.')) return;

        const originalHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('delete_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${encodeURIComponent(requestId)}`
        })
        .then(async r => {
            const text = await r.text();

            console.log('DELETE DEBUG ───────────────────────────────');
            console.log('HTTP status:', r.status);
            console.log('Raw response body:', text);
            console.log('Response headers:', [...r.headers.entries()]);

            if (!r.ok) {
                console.warn('HTTP not OK:', r.status, r.statusText);
                throw new Error(`Server returned HTTP ${r.status}`);
            }

            let data;
            try {
                data = JSON.parse(text);
                console.log('Parsed JSON:', data);
            } catch (jsonErr) {
                console.error('JSON parse failed:', jsonErr.message);
                console.log('First 300 chars of response:', text.substring(0, 300));
                throw new Error('Invalid JSON response from server');
            }

            return data;
        })
        .then(data => {
            console.log('Success path reached — data:', data);

            if (data && data.success === true) {
                console.log('Delete successful — removing row');
                row.remove();

                if (data.counts && data.total !== undefined) {
                    console.log('Updating counters with:', data.counts, data.total);
                    updateTopCounters(data.counts, data.total);
                }
            } else {
                console.warn('Server said success=false or no success field');
                alert('Delete failed: ' + (data?.error || 'Unknown server response'));
            }
        })
        .catch(err => {
            console.error('DELETE ERROR ──────────────────────────────');
            console.error(err);
            alert('Could not complete delete: ' + err.message);
        })
        .finally(() => {
            console.log('Delete operation finished');
            this.disabled = false;
            this.innerHTML = originalHtml;
        });
    });
});

// ────────────────────────────────────────────────
// Admin-only handlers (bulk delete, status change, etc.)
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    // Single delete (admin)
    document.querySelectorAll('.delete-request-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const requestId = this.dataset.requestId;
            const row = findRequestRow(requestId);

            if (!row) return;

            if (!confirm('Delete this request?')) return;

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('delete_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `request_id=${encodeURIComponent(requestId)}`
            })
            .then(r => r.json())
            .then(data => {
                row.remove();
                if (data.counts && data.total !== undefined) {
                    updateTopCounters(data.counts, data.total);
                }
                updateBulkUI();
            })
            .catch(() => alert('Failed to delete request'))
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-trash"></i>';
            });
        });
    });

    // Status change (admin)
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const id = this.dataset.requestId;
            const newStatus = this.value;
            const row = findRequestRow(id);
            const badge = row?.querySelector('.status-badge');

            if (!badge) return;

            const oldStatus = badge.textContent.trim();

            this.disabled = true;

            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `request_id=${id}&status=${encodeURIComponent(newStatus)}`
            })
            .then(r => r.json())
            .then(data => {
                badge.textContent = newStatus;
                badge.className = `badge status-badge ${getStatusClass(newStatus)} me-2`;
                if (data.counts && data.total !== undefined) {
                    updateTopCounters(data.counts, data.total);
                }
            })
            .catch(() => {
                alert('Failed to update status');
                this.value = oldStatus;
            })
            .finally(() => this.disabled = false);
        });
    });

    // Bulk UI events (admin)
    document.querySelectorAll('.request-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkUI);
    });

    document.querySelectorAll('.select-all-location').forEach(sa => {
        sa.addEventListener('change', function() {
            const loc = this.dataset.location;
            document.querySelectorAll(`.request-checkbox[data-location="${loc}"]`)
                .forEach(cb => cb.checked = this.checked);
            updateBulkUI();
        });
    });

    // Bulk delete (admin)
    document.getElementById('bulk-delete-btn')?.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.request-checkbox:checked');
        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);

        if (selectedIds.length === 0) return;

        if (!confirm(`Delete ${selectedIds.length} request(s)?`)) return;

        const button = this;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Deleting...';

        const idsToRemove = new Set(selectedIds);

        fetch('delete_bulk_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_ids=${encodeURIComponent(selectedIds.join(','))}`
        })
        .then(r => r.json())
        .then(data => {
            idsToRemove.forEach(id => {
                const row = findRequestRow(id);
                if (row) row.remove();
            });

            if (data.counts && data.total !== undefined) {
                updateTopCounters(data.counts, data.total);
            }

            document.querySelectorAll('.request-checkbox, .select-all-location').forEach(el => el.checked = false);
            updateBulkUI();
        })
        .catch(err => {
            console.error('Bulk delete failed', err);
            alert('Bulk delete failed');
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-trash me-2"></i> Delete <span id="selected-count">0</span> selected';
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