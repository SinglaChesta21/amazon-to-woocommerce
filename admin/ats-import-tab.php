<?php if (!defined('ABSPATH')) exit; ?>

<style>
    .ats-import-options {
        display: flex;
        gap: 20px;
        margin-top: 30px;
        font-family: "Segoe UI", sans-serif;
    }
    .ats-import-card {
        flex: 1;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        text-align: center;
        padding: 30px 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: 0.3s ease;
    }
    .ats-import-card:hover {
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        transform: translateY(-3px);
    }
    .ats-import-card h3 {
        font-size: 18px;
        margin-bottom: 20px;
        color: #2E8BA6;
    }
    .ats-import-card i {
        font-size: 32px;
        color: #2E8BA6;
        margin-bottom: 10px;
        display: block;
    }
    .ats-import-card a {
        background-color: #2E8BA6;
        color: #fff;
        padding: 10px 18px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        margin-top: 10px;
        transition: background 0.2s;
    }
    .ats-import-card a:hover {
        background-color: #256f88;
    }
</style>

<div class="ats-import-options">
    <div class="ats-import-card">
        <i class="fas fa-barcode"></i>
        <h3>Import via ASIN</h3>
        <a href="admin.php?page=amazon-sync&tab=import&method=asin">Start</a>
    </div>
    <div class="ats-import-card">
        <i class="fas fa-keyboard"></i>
        <h3>Import via Keyword</h3>
        <a href="admin.php?page=amazon-sync&tab=import&method=keyword">Start</a>
    </div>
    <div class="ats-import-card">
        <i class="fas fa-sync-alt"></i>
        <h3>Campaign Import</h3>
        <a href="admin.php?page=amazon-sync&tab=import&method=campaign">Start</a>
    </div>
</div>
