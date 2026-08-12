<?php
/**
 * Example: How to use the layout template
 * 
 * This file demonstrates how to use views/layout.php
 */

// Example 1: Using with $content variable
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$content = '<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Welcome</h5>
                </div>
                <div class="card-body">
                    <p>This is the main content area.</p>
                </div>
            </div>
        </div>
    </div>
</div>';

include __DIR__ . '/layout.php';

// Example 2: Using with $viewFile variable
/*
$pageTitle = 'Seksi';
$activePage = 'seksi';
$viewFile = __DIR__ . '/seksi/index.php';
include __DIR__ . '/layout.php';
*/

// Example 3: Adding custom CSS/JS
/*
$pageTitle = 'Reports';
$activePage = 'reports';
$additionalCSS = ['/css/reports.css'];
$additionalJS = ['/js/reports.js'];
$viewFile = __DIR__ . '/reports/index.php';
include __DIR__ . '/layout.php';
*/
?>

