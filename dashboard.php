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

        <div class="d-flex flex-column align-items-center gap-3 mb-4">
            <span class="badge bg-secondary fs-4 px-5 py-3" id="total-count">
                <?= $total_requests ?> Total
            </span>

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

                                                <div class="d-flex align-items-center gap-3 mt-2 mt-md-0 flex-wrap">
                                                    <!-- Quantity editing: admins always, users only own pending -->
                                                    <?php if (!empty($_SESSION['is_admin']) || $can_edit): ?>
                                                        <div class="input-group input-group-sm" style="width: 130px; flex-shrink: 0;">
                                                            <input type="number" class="form-control bg-dark text-white border-secondary qty-input"
                                                                   value="<?= $req['quantity'] ?>"
                                                                   min="1"
                                                                   data-request-id="<?= $req['id'] ?>">
                                                            <button class="btn btn-outline-success update-qty-btn"
                                                                    data-request-id="<?= $req['id'] ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Qty: <?= $req['quantity'] ?></span>
                                                    <?php endif; ?>

                                                    <!-- User delete (only own pending) -->
                                                    <?php if ($can_edit): ?>
                                                        <button class="btn btn-sm btn-outline-danger user-delete-btn"
                                                                data-request-id="<?= $req['id'] ?>"
                                                                title="Delete this request">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <!-- Admin controls -->
                                                    <?php if (!empty($_SESSION['is_admin'])): ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <select class="form-select form-select-sm status-select bg-dark text-white border-secondary" 
                                                                    style="width: 140px;"
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
                                                        </div>
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
// Helpers (unchanged)
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

    if (bulkContainer) bulkContainer.style.display = checkedCount > 0 ? 'block' : 'none';
    if (countElement) countElement.textContent = checkedCount;
}

function findRequestRow(id) {
    return document.querySelector(`[data-request-id="${id}"]`);
}

// Quantity update – auto on blur + manual checkmark
document.querySelectorAll('.qty-input').forEach(input => {
    const requestId = input.dataset.requestId;
    const btn = document.querySelector(`.update-qty-btn[data-request-id="${requestId}"]`);
    const qtyDisplay = document.getElementById(`qty-${requestId}`);

    if (!qtyDisplay) {
        console.warn('Missing qty display for request', requestId);
        return;
    }

    const doUpdate = () => {
        const newQty = parseInt(input.value.trim(), 10);

        if (isNaN(newQty) || newQty < 1) {
            alert('Please enter a valid quantity (at least 1)');
            input.value = qtyDisplay.textContent;
            return;
        }

        if (newQty === parseInt(qtyDisplay.textContent, 10)) return;

        input.disabled = true;
        if (btn) btn.disabled = true;

        fetch('update_request_qty.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${encodeURIComponent(requestId)}&quantity=${newQty}`
        })
        .then(async r => {
            const text = await r.text();
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return JSON.parse(text);
        })
        .then(data => {
            if (data.success) {
                qtyDisplay.textContent = newQty;
                input.style.backgroundColor = '#1e3a2f';
                setTimeout(() => input.style.backgroundColor = '', 800);
            } else {
                alert(data.error || 'Failed to update quantity');
                input.value = data.old_quantity ?? qtyDisplay.textContent;
            }
        })
        .catch(err => {
            console.error('Quantity update failed:', err);
            alert('Could not update quantity');
            input.value = qtyDisplay.textContent;
        })
        .finally(() => {
            input.disabled = false;
            if (btn) btn.disabled = false;
        });
    };

    input.addEventListener('blur', doUpdate);
    if (btn) btn.addEventListener('click', e => { e.preventDefault(); doUpdate(); });
});

// All admin/user handlers inside DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // User delete own pending request
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
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    if (data.counts && data.total !== undefined) {
                        updateTopCounters(data.counts, data.total);
                    }
                } else {
                    alert(data.error || 'Delete failed');
                }
            })
            .catch(() => alert('Could not delete request'))
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalHtml;
            });
        });
    });

    // Admin: single delete
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

    // Admin: status change
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

    // Bulk UI events
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

    // Bulk delete
    document.getElementById('bulk-delete-btn')?.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.request-checkbox:checked');
        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);

        if (selectedIds.length === 0) return;

        if (!confirm(`Delete ${selectedIds.length} request(s)?`)) return;

        const button = this;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Deleting...';

        fetch('delete_bulk_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_ids=${encodeURIComponent(selectedIds.join(','))}`
        })
        .then(r => r.json())
        .then(data => {
            selectedIds.forEach(id => {
                const row = findRequestRow(id);
                if (row) row.remove();
            });

            if (data.counts && data.total !== undefined) {
                updateTopCounters(data.counts, data.total);
            }

            document.querySelectorAll('.request-checkbox, .select-all-location').forEach(el => el.checked = false);
            updateBulkUI();
        })
        .catch(() => alert('Bulk delete failed'))
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