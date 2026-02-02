<?php
session_start();
require 'db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$current_page = basename(__FILE__);

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h2 mb-4">Admin Panel</h1>

    <div class="accordion" id="adminAccordion">
        <!-- Categories Management -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategories" aria-expanded="true" aria-controls="collapseCategories">
                    <i class="bi bi-tags me-2"></i> Categories Management
                </button>
            </h2>
            <div id="collapseCategories" class="accordion-collapse collapse show" data-bs-parent="#adminAccordion">
                <div class="accordion-body">
                    <!-- PASTE YOUR EXISTING CATEGORIES MANAGEMENT CODE HERE -->
                    <!-- Example: Add category form + categories table/list -->
                    <!-- Your current category add/edit/delete code goes here -->
                </div>
            </div>
        </div>

        <!-- Items Management -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseItems" aria-expanded="false" aria-controls="collapseItems">
                    <i class="bi bi-box-seam me-2"></i> Items Management
                </button>
            </h2>
            <div id="collapseItems" class="accordion-collapse collapse show" data-bs-parent="#adminAccordion">
                <div class="accordion-body">
                    <!-- PASTE YOUR EXISTING ITEMS MANAGEMENT CODE HERE -->
                    <!-- Example: Items table with edit/delete buttons -->
                    <!-- Your current items list/table code goes here -->
                </div>
            </div>
        </div>

        <!-- Add New Item -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddItem" aria-expanded="false" aria-controls="collapseAddItem">
                    <i class="bi bi-plus-circle me-2"></i> Add New Item
                </button>
            </h2>
            <div id="collapseAddItem" class="accordion-collapse collapse show" data-bs-parent="#adminAccordion">
                <div class="accordion-body">
                    <!-- PASTE YOUR EXISTING ADD NEW ITEM FORM HERE -->
                    <!-- Example: Form with item name, category select, variants, etc. -->
                    <!-- Your current add item form code goes here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>