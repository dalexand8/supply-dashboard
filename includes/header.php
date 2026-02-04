<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supply Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
    body { min-height: 100vh; background-color: var(--bs-body-bg); }
    .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 0; box-shadow: inset -1px 0 0 rgba(255, 255, 255, .1); }
    .sidebar .nav-link { color: rgba(255, 255, 255, .75); padding: 0.75rem 1rem; border-radius: 0.375rem; margin: 0.125rem 0.5rem; transition: all 0.2s ease; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.15); }
    .sidebar .nav-link i { width: 24px; text-align: center; }
    main { margin-left: 280px; transition: margin-left 0.3s ease; }
    @media (max-width: 767.98px) { main { margin-left: 0; } }
    .avatar-img { width: 40px; height: 40px; object-fit: cover; }

    /* Bigger/taller dropdown + locked dark trigger box */
    .select2-dropdown {
        min-width: 600px !important; /* Wider - change to 700px+ */
        max-width: none !important;
        max-height: 800px !important; /* Taller - more items */
        border-radius: 0.5rem !important;
        background-color: #212529 !important;
    }

    .select2-results__options {
        max-height: 800px !important; /* Key for tall scrollable list */
    }

    .select2-results__option {
        padding: 14px 18px !important; /* Bigger rows */
        font-size: 1.2rem !important; /* Bigger text */
        background-color: #212529 !important;
        color: #fff !important;
    }

    .select2-results__group {
        padding: 14px 18px !important;
        font-size: 1.3rem !important;
        font-weight: bold !important;
        background-color: #343a40 !important;
        color: #adb5bd !important;
    }

    /* Search field subtle */
    .select2-search--dropdown {
        padding: 12px 16px !important;
        background-color: #212529 !important;
    }

    .select2-search__field {
        background-color: #343a40 !important;
        color: #fff !important;
        border: 1px solid #495057 !important;
        border-radius: 0.375rem !important;
        padding: 12px 16px !important;
        font-size: 1.2rem !important;
    }

    /* Locked dark trigger box - stronger overrides (no light border/glow on placeholder/load) */
    .select2-container--default .select2-selection--single {
        background-color: #212529 !important;
        border: 1px solid #495057 !important; /* Dark border always */
        color: #fff !important;
        height: 38px !important;
        border-radius: 0.375rem !important;
        box-shadow: none !important; /* No glow on load */
        outline: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #fff !important;
        line-height: 36px !important;
        padding-left: 12px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #adb5bd !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #adb5bd transparent transparent transparent !important;
    }

    .select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #adb5bd transparent !important;
    }

    /* Remove light focus glow/border on load/placeholder - subtle blue only on real focus */
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #495057 !important; /* Dark border */
        box-shadow: none !important; /* No light glow on load */
        outline: 0 !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default .select2-selection--single:active {
        border-color: #0d6efd !important; /* Blue border on open/focus */
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important; /* Subtle blue glow */
    }

    /* Blue highlight */
    .select2-results__option--highlighted {
        background-color: #0d6efd !important;
        color: #fff !important;
    }

    /* Clean accordion controls - no blue glow/border, keep custom colors */
.accordion-button {
    box-shadow: none !important; /* No glow */
    border-color: #495057 !important; /* Dark border */
}

.accordion-button.collapsed {
    box-shadow: none !important;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1) !important; /* Super subtle blue (almost invisible) */
    border-color: #495057 !important;
}

.accordion-button:hover {
    box-shadow: none !important;
}

.accordion-item {
    border-color: #495057 !important; /* Dark borders between items */
}
/* Kill blue glow/border on accordion - keep custom colors */
.accordion-button,
.accordion-button.collapsed,
.accordion-button:not(.collapsed) {
    box-shadow: none !important;
    border-color: #495057 !important; /* Dark border */
}

.accordion-button:focus,
.accordion-button:active {
    box-shadow: none !important;
    border-color: #495057 !important;
    outline: none !important;
}

.accordion-item {
    border-color: #495057 !important;
}
/* Kill blue glow/outline/border on accordion focus/active/collapsed */
.accordion-button,
.accordion-button.collapsed,
.accordion-button:not(.collapsed),
.accordion-button:focus,
.accordion-button:active {
    box-shadow: none !important;
    outline: none !important;
    border-color: #495057 !important; /* Dark border */
}

.accordion-item {
    border-color: #495057 !important;
}
/* Full kill blue glow/outline/border on accordion - keep custom colors */
.accordion-button,
.accordion-button.collapsed,
.accordion-button:not(.collapsed) {
    box-shadow: none !important;
    outline: none !important;
    border-color: #495057 !important; /* Dark border */
}

.accordion-button:focus,
.accordion-button:active,
.accordion-button:focus-visible {
    box-shadow: none !important;
    outline: none !important;
    border-color: #495057 !important;
}

.accordion-item {
    border-color: #495057 !important;
}

/* Arrow clean (no blue tint) */
.accordion-button::after {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23adb5bd'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e") !important; /* Gray arrow */
}

</style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
    if (!isset($current_page)) {
        $current_page = basename(__FILE__);
    }
    ?>

    

    <!-- Desktop Sidebar -->
    <div class="sidebar bg-dark d-flex flex-column flex-shrink-0 p-3" style="width: 280px;">
        <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-4 mx-3 link-light text-decoration-none">
            <i class="bi bi-ear fs-3 me-2"></i>
            <span class="fs-4 fw-semibold">Supply Dashboard</span>
        </a>
        <hr class="border-secondary">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="submit.php" class="nav-link <?= $current_page === 'submit.php' ? 'active' : '' ?>">
                    <i class="bi bi-send"></i> Submit Request
                </a>
            </li>
            <li>
                <a href="suggest_item.php" class="nav-link <?= $current_page === 'suggest_item.php' ? 'active' : '' ?>">
                    <i class="bi bi-lightbulb"></i> Suggest New Item
                </a>
            </li>
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
            <li>
                <a href="shopping_list.php" class="nav-link <?= $current_page === 'shopping_list.php' ? 'active' : '' ?>">
                    <i class="bi bi-cart"></i> Shopping List
                </a>
            </li>
            <li>
                <a href="admin.php" class="nav-link <?= $current_page === 'admin.php' ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i> Admin Panel
                </a>
            </li>
            <li>
                <a href="users.php" class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Manage Users
                </a>
            </li>
            <li>
                <a href="login_activity.php" class="nav-link <?= $current_page === 'login_activity.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i> Login Activity Log
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <hr class="border-secondary">
        <div class="dropdown px-3">
            <a href="#" class="d-flex align-items-center link-light text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username'] ?? 'User') ?>&background=0D8ABC&color=fff&bold=true" alt="Avatar" class="rounded-circle me-2 avatar-img">
                <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow-1">
        <!-- Mobile Header -->
        <nav class="navbar navbar-dark bg-dark shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-dark d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <div class="navbar-brand mb-0 h1 ms-3 d-md-none">Supply Dashboard</div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid py-4">