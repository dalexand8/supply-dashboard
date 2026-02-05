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
    $other = isset($_POST['other']) ? substr($_POST['other'], 0, 25) : '';
   
    try {
        if ($item === 'other' && $other) {
            $item_name = $other;
            $suggested = 1;
            $stmt = $pdo->prepare("INSERT INTO suggestions (name, category_id, user_id) VALUES (?, NULL, ?)");
            $stmt->execute([$item_name, $_SESSION['user_id']]);
        } else {
            $item_name = $item;
            $suggested = 0;
        }
       
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
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $success]);
            exit;
        }
        
        // Normal redirect
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error submitting request: ' . $e->getMessage();
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container py-4">
    <h2 class="mb-4">Submit Supply Request</h2>
    
    <div id="alert-container"></div>
    
    <form id="submit-form" method="POST">
        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <select class="form-select" id="location" name="location" required>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="item" class="form-label">Item</label>
            <select class="form-select" id="item" name="item" required></select>
        </div>
        <div class="mb-3" id="variant_container" style="display:none;">
            <label for="variant_id" class="form-label">Variant</label>
            <select class="form-select" id="variant_id" name="variant_id">
                <option value="">Select Variant</option>
            </select>
            <small class="text-muted" id="no_variants_msg" style="display:none;">No variants available for this item.</small>
        </div>
        <div class="mb-3" id="other_field" style="display:none;">
            <label for="other" class="form-label">Other Item (max 25 chars)</label>
            <input type="text" class="form-control" id="other" name="other" maxlength="25">
        </div>
        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity</label>
            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit Request</button>
        <a href="dashboard.php" class="btn btn-secondary ms-3">Back to Dashboard</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
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

        $('#item').append(new Option('Other', 'other', false, false));
        $('#item').val(null).trigger('change');

        // Placeholder in search
        $('#item').on('select2:open', function () {
            $('.select2-search__field').attr('placeholder', 'Search items or categories...');
        });

        // Dark dropdown no flash
        $('#item').on('select2:open', function () {
            setTimeout(function() {
                $('.select2-dropdown').css({
                    'background-color': '#212529',
                    'border': '1px solid #495057',
                    'color': '#fff',
                    'opacity': '1'
                });
                $('.select2-results__options').css('background-color', '#212529');
                $('.select2-results__option').css({
                    'background-color': '#212529',
                    'color': '#fff'
                });
                $('.select2-results__group').css({
                    'background-color': '#343a40',
                    'color': '#adb5bd'
                });
                $('.select2-search__field').css({
                    'background-color': '#343a40',
                    'color': '#fff',
                    'border': '1px solid #495057'
                });
            }, 150);
        });

        // Variant load
        $('#item').on('change', function() {
            var item = $(this).val();
            var $variantContainer = $('#variant_container');
            var $variantSelect = $('#variant_id');
            var $noMsg = $('#no_variants_msg');
            
            $variantContainer.hide();
            $variantSelect.empty().prop('disabled', true);
            $noMsg.hide();
            $('#other_field').hide();
            
            if (item === 'other') {
                $('#other_field').show();
            } else if (item) {
                $.get('api_variants.php', { item: item }, function(variants) {
                    $variantSelect.empty();
                    $variantSelect.append('<option value="">Select Variant</option>');
                    if (variants.length > 0) {
                        variants.forEach(function(v) {
                            $variantSelect.append('<option value="' + v.id + '">' + v.name + '</option>');
                        });
                        $variantSelect.prop('disabled', false);
                    } else {
                        $variantSelect.append('<option value="" selected>N/A</option>').prop('disabled', true);
                        $noMsg.show();
                    }
                    $variantContainer.show();
                }, 'json');
            }
        });

        // AJAX form submit
        $('#submit-form').on('submit', function(e) {
            e.preventDefault();

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
        // Auto-fade after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);

        // Clear form
        $('#submit-form')[0].reset();
        $('#item').val(null).trigger('change');
        $('#variant_container').hide();
        $('#other_field').hide();
    } else {
        $('#alert-container').html(`
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> ${response.message}
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
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>