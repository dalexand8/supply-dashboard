<?php
// submit.php
require 'includes/auth.php';  // Auth + session/db

require_once 'vendor/autoload.php';  // Dotenv

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

include 'includes/notify.php';  // Notification function

$current_page = basename(__FILE__);

// Locations from .env
$locations = array_filter(array_map('trim', explode(',', $_ENV['OFFICE_LOCATIONS'] ?? '')));

if (empty($locations)) {
    $locations = ['Turlock office'];  // Fallback
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = $_POST['location'] ?? '';
    $item = $_POST['item'] ?? '';
    $variant_id = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $quantity = (int)($_POST['quantity'] ?? 1);

    try {
        $item_name = $item;
        $suggested = 0;

        $stmt = $pdo->prepare("INSERT INTO requests (location, item_name, variant_id, suggested, user_id, quantity, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$location, $item_name, $variant_id, $suggested, $_SESSION['user_id'], $quantity]);

        $success = 'Request submitted successfully!';

        // Notification
        $subject = 'New Supply Request';
        $body = "<p>User <strong>" . htmlspecialchars($_SESSION['username']) . "</strong> submitted a request:</p>";
        $body .= "<p><strong>" . htmlspecialchars($item_name) . "</strong>";
        if ($variant_id) {
            $vstmt = $pdo->prepare("SELECT name FROM item_variants WHERE id = ?");
            $vstmt->execute([$variant_id]);
            $variant = $vstmt->fetchColumn();
            if ($variant) $body .= " (" . htmlspecialchars($variant) . ")";
        }
        $body .= " - Qty " . $quantity . " - Location: " . htmlspecialchars($location) . "</p>";
        notify_admins($subject, $body);

        // AJAX response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $success]);
            exit;
        }

        // Fallback redirect
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error submitting request: ' . $e->getMessage();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    html { color-scheme: dark; }

    .form-select, .form-control {
        background-color: #2d343a;
        color: #e9ecef;
        border-color: #495057;
    }

    .form-select:focus, .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Green when valid (after validation triggered) */
    .was-validated .form-select:valid,
    .was-validated .form-control:valid {
        border-color: #198754;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }

    /* Red when invalid */
    .was-validated .form-select:invalid,
    .was-validated .form-control:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
</style>

<div class="container py-4 px-2 px-sm-4">
    <h2 class="mb-4">Submit Supply Request</h2>

    <div id="alert-container"></div>

    <form id="submit-form" method="POST" class="needs-validation" novalidate>
        <div class="row g-3">
            <div class="col-12">
                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                <select class="form-select" id="location" name="location" required>
                    <option value="" disabled selected>Choose location...</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a location.</div>
            </div>

            <div class="col-12">
                <label for="item" class="form-label">Item <span class="text-danger">*</span></label>
                <select class="form-select" id="item" name="item" required></select>
                <div class="invalid-feedback">Please select an item.</div>
            </div>

            <div class="col-12" id="variant_container" style="display:none;">
                <label for="variant_id" class="form-label">Variant</label>
                <select class="form-select" id="variant_id" name="variant_id">
                    <option value="">Select Variant</option>
                </select>
                <small class="text-muted" id="no_variants_msg" style="display:none;">No variants available for this item.</small>
            </div>

            <div class="col-12 col-sm-6">
                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                <div class="invalid-feedback">Please enter a quantity of 1 or more.</div>
            </div>

            <div class="col-12 mt-4">
                <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-3">
                    <button type="submit" class="btn btn-primary flex-fill" id="submit-btn">
                        <span class="submit-text">Submit Request</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary flex-fill">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Force clear validation on every load (prevents red on reload)
    $('#submit-form').removeClass('was-validated');
    $('#submit-form .form-select, #submit-form .form-control').each(function() {
        $(this).removeClass('is-invalid is-valid');
        this.setCustomValidity('');
    });

    // Initialize Select2
    $('#item').select2({
        width: '100%',
        dropdownAutoWidth: true,
        placeholder: 'Select an item',
        allowClear: true,
        ajax: {
            url: 'api_items.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        minimumInputLength: 0
    });

    $('#item').val(null).trigger('change');

    $('#item').on('select2:open', function () {
        $('.select2-search__field').attr('placeholder', 'Search items or categories...');
    });

    // Load variants
    $('#item').on('change', function() {
        const item = $(this).val();
        const $vc = $('#variant_container');
        const $vs = $('#variant_id');
        const $nm = $('#no_variants_msg');

        $vc.hide();
        $vs.empty().prop('disabled', true);
        $nm.hide();

        if (item) {
            $.get('api_variants.php', { item }, function(variants) {
                $vs.empty().append('<option value="">Select Variant</option>');

                if (variants?.length > 0) {
                    variants.forEach(v => $vs.append(`<option value="${v.id}">${v.name}</option>`));
                    $vs.prop('disabled', false);
                } else {
                    $vs.append('<option value="" selected>N/A</option>').prop('disabled', true);
                    $nm.show();
                }

                $vc.show();
            }, 'json').fail(() => {
                $vs.append('<option value="" selected>Error loading variants</option>');
                $vc.show();
            });
        }
    });

    // Submit handler
    $('#submit-form').on('submit', function(e) {
        e.preventDefault();

        const form = this;
        let valid = true;

        // Reset validity messages
        ['location', 'item', 'quantity'].forEach(id => {
            const el = document.getElementById(id);
            el.setCustomValidity('');
        });

        // Location
        const loc = document.getElementById('location');
        if (!loc.value) {
            loc.setCustomValidity('Please select a location');
            valid = false;
        }

        // Item
        const itemEl = document.getElementById('item');
        if (!itemEl.value) {
            itemEl.setCustomValidity('Please select an item');
            valid = false;
        }

        // Quantity
        const qty = document.getElementById('quantity');
        if (qty.value < 1 || !qty.value) {
            qty.setCustomValidity('Please enter a quantity of 1 or more');
            valid = false;
        }

        if (!valid) {
            form.classList.add('was-validated');
            form.querySelector(':invalid')?.focus();
            return;
        }

        // Show spinner
        const btn = $('#submit-btn');
        btn.find('.submit-text').addClass('d-none');
        btn.find('.spinner-border').removeClass('d-none');
        btn.prop('disabled', true);

        // AJAX
        $.ajax({
            url: 'submit.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#alert-container').html(`
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> Request Submitted.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(() => $('.alert').alert('close'), 5000);

                    form.reset();
                    $('#item').val(null).trigger('change');
                    $('#variant_container').hide();
                    form.classList.remove('was-validated');

                    // Clear validation classes on success
                    ['location', 'item', 'quantity'].forEach(id => {
                        const el = document.getElementById(id);
                        el.classList.remove('is-invalid', 'is-valid');
                        el.setCustomValidity('');
                    });

                    setTimeout(() => window.location.href = 'dashboard.php', 1500);
                } else {
                    $('#alert-container').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> ${response.message || 'Unknown error'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#alert-container').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> Submission failed - try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            },
            complete: function() {
                btn.find('.submit-text').removeClass('d-none');
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
            }
        });
    });

    // Live re-validation after first submit attempt
    const form = document.getElementById('submit-form');
    form.addEventListener('change', function(e) {
        if (form.classList.contains('was-validated')) {
            const field = e.target;
            if (field.checkValidity()) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                field.setCustomValidity('');
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
            }
        }
    });

    // Clear validation state on every page load / reload
    window.addEventListener('pageshow', function() {
        const form = document.getElementById('submit-form');
        if (form) {
            form.classList.remove('was-validated');
            form.querySelectorAll('.form-select, .form-control').forEach(el => {
                el.classList.remove('is-invalid', 'is-valid');
                el.setCustomValidity('');
            });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>