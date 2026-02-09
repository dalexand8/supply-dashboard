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
    <div class="text-center mb-4">
        <h1 class="h2 mb-3">Supply Requests Dashboard</h1>
        <div class="fs-5 mb-3">
            <span class="badge bg-secondary fs-5" id="total-count"><?= $total_requests ?> Total</span>
        </div>

        <div class="d-flex justify-content-center flex-wrap gap-3 mb-4" id="status-badges-container">
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
                <span class="badge <?= $class ?> fs-5 status-badge"><?= $count ?> <?= htmlspecialchars($status) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Floating bulk delete button -->
    <div class="position-fixed bottom-0 end-0 m-4" style="z-index: 1050; display: none;" id="bulk-actions">
        <button class="btn btn-danger shadow-lg px-4 py-2 rounded-pill" id="bulk-delete-btn">
            <i class="bi bi-trash me-2"></i> Delete <span id="selected-count">0</span> selected
        </button>
    </div>

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
                        <button class="accordion-button fw-bold text-white <?= 'collapsed' ?>" 
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
                                    <?php foreach ($loc_requests as $req): ?>
                                        <li class="list-group-item bg-dark text-white border-bottom border-secondary py-3" 
                                            data-request-id="<?= $req['id'] ?>">
                                            <div class="d-flex align-items-start gap-3">
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
                                                        • Qty: <strong><?= $req['quantity'] ?></strong>
                                                    </div>

                                                    <div class="small text-muted mt-1 opacity-75">
                                                        <?= date('M d, Y  g:ia', strtotime($req['created_at'])) ?>
                                                    </div>
                                                </div>

                                                <?php if (!empty($_SESSION['is_admin'])): ?>
                                                    <div class="d-flex align-items-center gap-2">
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
                                                    </div>
                                                <?php endif; ?>
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

    container.querySelectorAll('.status-badge:not(#total-count)').forEach(b => b.remove());

    const priority = ['Pending', 'Ordered', 'Backordered', 'Approved', 'Acknowledged', 'Fulfilled'];

    priority.forEach(status => {
        const count = newCounts[status] || 0;
        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5 status-badge`;
            badge.textContent = `${count} ${status}`;
            container.appendChild(badge);
        }
    });

    Object.keys(newCounts).forEach(status => {
        if (!priority.includes(status) && newCounts[status] > 0) {
            const badge = document.createElement('span');
            badge.className = `badge ${getStatusClass(status)} fs-5 status-badge`;
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

document.addEventListener('DOMContentLoaded', function() {

    // Single delete
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

    // Status change
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

    // ────────────────────────────────────────────────
    // Bulk delete – optimistic + safe
    // ────────────────────────────────────────────────
    document.getElementById('bulk-delete-btn')?.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.request-checkbox:checked');
        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);

        if (selectedIds.length === 0) return;

        if (!confirm(`Delete ${selectedIds.length} request(s)?`)) return;

        const button = this;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Deleting...';

        // Remember which ones we are trying to delete
        const idsToRemove = new Set(selectedIds);

        fetch('delete_bulk_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_ids=${encodeURIComponent(selectedIds.join(','))}`
        })
        .then(async r => {
            const text = await r.text();
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.warn('Bulk delete response is not valid JSON', text.substring(0, 300));
            }
            return { ok: r.ok, data };
        })
        .then(({ ok, data }) => {
            // Remove rows that still exist (optimistic UI)
            let removed = 0;
            idsToRemove.forEach(id => {
                const row = findRequestRow(id);
                if (row) {
                    row.remove();
                    removed++;
                }
            });

            // Try to update counters if server gave valid data
            if (data && data.counts && data.total !== undefined) {
                updateTopCounters(data.counts, data.total);
            }

            // Reset checkboxes
            document.querySelectorAll('.request-checkbox, .select-all-location').forEach(el => el.checked = false);
            updateBulkUI();

            // Only complain if almost nothing was removed
            if (removed === 0) {
                alert('Bulk delete may have failed – please refresh and check.');
            }
        })
        .catch(err => {
            console.error('Bulk delete network error', err);

            // Check if rows are still there → only then show error
            let anyStillThere = false;
            idsToRemove.forEach(id => {
                if (findRequestRow(id)) anyStillThere = true;
            });

            if (anyStillThere) {
                alert('Bulk delete failed – check console for details.');
            }
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