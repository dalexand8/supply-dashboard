</div> <!-- Close container-fluid -->

        <footer class="bg-dark text-white text-center py-3 mt-5 border-top border-secondary">
            <p>&copy; <?= date('Y') ?> Supply Dashboard.</p>
        </footer>
    </main>

    <!-- Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas" style="width: 280px;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="bi bi-speedometer2 me-2"></i>Supply Dashboard</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="p-3">
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
                <div class="dropdown">
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
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
