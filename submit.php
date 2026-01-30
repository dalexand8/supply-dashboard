<?php
// submit.php - Fixed Select2 loading and form submit
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$locations = ['Turlock office', 'Modesto office', 'Merced office', 'Atwater Office', 'Sonora office'];
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = $_POST['location'] ?? '';
    $item = $_POST['item'] ?? '';
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
       
        $stmt = $pdo->prepare("INSERT INTO requests (location, item_name, suggested, user_id, quantity, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$location, $item_name, $suggested, $_SESSION['user_id'], $quantity]);
       
        $success = 'Request submitted successfully';
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error submitting request: ' . $e->getMessage();
    }
}
?>
<?php include 'includes/header.php'; ?>

<!-- Select2 CSS (safe here after header) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Submit Supply Request</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <select class="form-select" id="location" name="location" required>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo $loc; ?>"><?php echo $loc; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="item" class="form-label">Item</label>
            <select class="form-select js-example-basic-single" id="item" name="item" required>
            </select>
        </div>
        <div class="mb-3" id="other_field" style="display:none;">
            <label for="other" class="form-label">Other Item (max 25 chars)</label>
            <input type="text" class="form-control" id="other" name="other" maxlength="25">
        </div>
        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity</label>
            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('#item').select2({
            placeholder: 'Select an item',
            allowClear: true,
            ajax: {
                url: 'api_items.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });
        $('#item').append(new Option('Other', 'other', false, false));
        $('#item').val(null).trigger('change');
        $('#item').on('change', function() {
            if ($(this).val() === 'other') {
                $('#other_field').show();
            } else {
                $('#other_field').hide();
            }
        });
    });
</script>

