<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/balance_conversion.php';
auth_require();
// Refresh saldo agar nilai terbaru langsung tampil saat halaman di-load
auth_refresh_balance();
$user = auth_user();
$activePage = 'deposit';
$sectionTitle = 'Top Up Saldo';

// Set timezone to WIB (GMT+7)
date_default_timezone_set('Asia/Jakarta');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Up Saldo - AcisPedia</title>
    <link rel="icon" href="storage/assets/img/logo/logo_trans.png">
    
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style-v2.css">
    
    <style>
        /* Dashboard Layout - SAMA PERSIS DENGAN ORDER.PHP */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Enhanced - SAMA PERSIS */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--navy-800) 0%, var(--navy-700) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow: hidden;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: 80px;
            background: linear-gradient(135deg, var(--teal-500) 0%, var(--teal-600) 100%);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--navy-600);
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            min-height: 72px;
        }
        
        .sidebar.collapsed .sidebar-header {
            padding: 20px 15px;
            justify-content: center;
        }
        
        .sidebar-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-title {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--slate-200);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            white-space: nowrap;
            position: relative;
        }
        
        .sidebar.collapsed .menu-item {
            padding: 12px 15px;
            justify-content: center;
        }
        
        .menu-item:hover, .menu-item.active {
            background: var(--navy-600);
            color: white;
            border-left-color: var(--teal-500);
        }
        
        .menu-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--slate-200);
        }

        .menu-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .menu-item:hover .menu-icon,
        .menu-item.active .menu-icon {
            color: var(--teal-300);
        }
        
        .menu-text {
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        /* Tooltip for collapsed sidebar */
        .menu-tooltip {
            position: absolute;
            left: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--navy-600);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .menu-tooltip::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 50%;
            transform: translateY(-50%);
            border: 4px solid transparent;
            border-right-color: var(--navy-600);
        }
        
        .sidebar.collapsed .menu-item:hover .menu-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: var(--navy-900);
        }
        
        .main-content.collapsed {
            margin-left: 80px;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Top Header - SAMA PERSIS */
        .top-header {
            background: rgba(255,255,255,.05);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 4500;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .hamburger-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .hamburger-btn:hover {
            background: rgba(255,255,255,.06);
            transform: scale(1.05);
        }
        
        .hamburger-btn:hover .hamburger span {
            background: var(--teal-500);
        }
        
        .hamburger {
            width: 20px;
            height: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .hamburger span {
            width: 100%;
            height: 2px;
            background: var(--slate-100);
            border-radius: 1px;
            transition: all 0.3s ease;
        }
        
        .hamburger.active span {
            background: var(--teal-500);
        }
        
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--slate-100);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        /* Profile Dropdown - SAMA PERSIS */
        .profile-dropdown {
            position: relative;
            z-index: 2500;
        }
        
        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .profile-btn:hover {
            background: rgba(255,255,255,.06);
        }
        
        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(15,25,40,.85);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            box-shadow: 0 10px 32px -4px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.05);
            backdrop-filter: blur(16px) saturate(140%);
            min-width: 280px;
            z-index: 6000;
            margin-top: 8px;
            display: none;
        }
        
        .profile-menu.show {
            display: block;
        }
        
        .profile-menu-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-details h4 {
            font-weight: 600;
            font-size: 14px;
            color: var(--slate-100);
            margin: 0;
        }
        
        .profile-details p {
            font-size: 12px;
            color: var(--slate-300);
            margin: 0;
        }
        
        .balance-info {
            margin-top: 12px;
            padding: 8px 12px;
            background: rgba(20,184,166,.12);
            border: 1px solid rgba(20,184,166,.25);
            border-radius: 8px;
            font-size: 12px;
            color: var(--teal-300);
        }
        
        .balance-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            color: var(--teal-300);
            margin-right: 4px;
        }

        .balance-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .profile-menu-items {
            padding: 8px;
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--slate-200);
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .profile-menu-item:hover {
            background: rgba(255,255,255,.06);
        }
        
        .profile-menu-item.danger {
            color: #f87171;
        }
        
        .profile-menu-item.danger:hover {
            background: rgba(220,38,38,.12);
        }
        
        .profile-menu-icon {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        /* Content Area */
        .content-area {
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* DEPOSIT SPECIFIC STYLES */
        .deposit-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .deposit-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        
        .deposit-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .deposit-header h2 {
            color: var(--slate-100);
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .deposit-header p {
            color: var(--slate-300);
            font-size: 14px;
            margin: 0;
        }
        
        .method-section {
            margin-bottom: 32px;
        }
        
        .method-title {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Method Toggle Styles */
        .method-toggle {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .method-option {
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .method-option:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }
        
        .method-option.active {
            background: rgba(20, 184, 166, 0.1);
            border-color: var(--teal-500);
        }
        
        .method-radio {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            flex-shrink: 0;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .method-option.active .method-radio {
            border-color: var(--teal-500);
        }
        
        .method-option.active .method-radio::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 10px;
            height: 10px;
            background: var(--teal-500);
            border-radius: 50%;
        }
        
        .method-details {
            flex: 1;
        }
        
        .method-details h3 {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }
        
        .method-details p {
            color: var(--slate-300);
            font-size: 13px;
            margin: 0;
        }
        
        /* Legacy method card (backup) */
        .method-card {
            background: rgba(20, 184, 166, 0.1);
            border: 2px solid var(--teal-500);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .method-icon {
            width: 40px;
            height: 40px;
            background: var(--teal-500);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
        }
        
        .method-info h3 {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }
        
        .method-info p {
            color: var(--slate-300);
            font-size: 14px;
            margin: 0;
        }
        
        .amount-section {
            margin-bottom: 32px;
        }
        
        .amount-title {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .quick-amount {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 16px;
            text-align: center;
            color: var(--slate-200);
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .quick-amount:hover {
            background: rgba(20, 184, 166, 0.1);
            border-color: rgba(20, 184, 166, 0.3);
            color: var(--teal-300);
        }
        
        .quick-amount.active {
            background: rgba(20, 184, 166, 0.15);
            border-color: var(--teal-500);
            color: var(--teal-300);
        }
        
        .custom-amount {
            margin-top: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: var(--slate-100);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--slate-100);
            font-size: 16px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--teal-500);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 1px rgba(20, 184, 166, 0.2);
        }
        
        .input-hint {
            color: var(--slate-400);
            font-size: 12px;
            margin-top: 6px;
        }
        
        /* Input with Prefix Styles */
        .input-with-prefix {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-prefix {
            position: absolute;
            left: 16px;
            color: var(--slate-300);
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            pointer-events: none;
            z-index: 1;
        }
        
        .form-input.with-prefix {
            padding-left: 42px;
        }
        
        .label-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: var(--teal-500);
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            margin-right: 6px;
            vertical-align: middle;
        }
        
        /* Conversion Section Styles */
        .conversion-section {
            margin-bottom: 32px;
        }
        
        .conversion-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .conversion-info-box {
            background: rgba(20, 184, 166, 0.08);
            border: 1px solid rgba(20, 184, 166, 0.25);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            gap: 12px;
            color: var(--slate-200);
            font-size: 14px;
            line-height: 1.6;
        }
        
        .conversion-info-box svg {
            flex-shrink: 0;
            margin-top: 2px;
            stroke: var(--teal-300);
        }
        
        .conversion-info-box strong {
            color: var(--teal-300);
            display: block;
            margin-bottom: 4px;
        }
        
        .conversion-info-box ul {
            color: var(--slate-300);
        }
        
        .form-input::placeholder {
            color: var(--slate-400);
        }
        
        .amount-limits {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 12px;
            color: var(--slate-400);
        }
        
        .unique-code-section {
            margin-top: 20px;
            padding: 16px;
            background: rgba(20, 184, 166, 0.1);
            border: 1px solid rgba(20, 184, 166, 0.3);
            border-radius: 8px;
        }
        
        .unique-code-title {
            color: var(--teal-300);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .unique-code-value {
            color: var(--slate-100);
            font-size: 20px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .copy-code-btn {
            background: rgba(20, 184, 166, 0.1);
            border: 1px solid rgba(20, 184, 166, 0.3);
            border-radius: 6px;
            padding: 6px 12px;
            color: var(--teal-300);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s ease-out;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        
        .copy-code-btn:hover {
            background: rgba(20, 184, 166, 0.15);
            border-color: rgba(20, 184, 166, 0.4);
            color: var(--teal-200);
        }
        
        .copy-code-btn:active {
            transform: scale(0.95);
        }
        
        .unique-code-info {
            color: var(--slate-400);
            font-size: 12px;
            margin-top: 8px;
            line-height: 1.4;
        }
        
        .upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 32px 16px;
            text-align: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .upload-area:hover {
            border-color: rgba(20, 184, 166, 0.5);
            background: rgba(20, 184, 166, 0.03);
        }
        
        .upload-area.dragover {
            border-color: var(--teal-500);
            background: rgba(20, 184, 166, 0.08);
            transform: scale(1.02);
        }
        
        .upload-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            color: var(--slate-400);
        }
        
        .upload-text {
            color: var(--slate-200);
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .upload-subtext {
            color: var(--slate-400);
            font-size: 14px;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-preview {
            display: none;
            margin-top: 16px;
        }
        
        .file-preview.show {
            display: block;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .file-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .file-name {
            color: var(--slate-200);
            font-size: 14px;
        }
        
        .remove-file {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 6px;
            padding: 4px 8px;
            color: #f87171;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .remove-file:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        /* Submit Button States */
        .submit-btn {
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            border: none;
            border-radius: 8px;
            padding: 14px 32px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #0d9488, #0f766e);
        }
        
        .submit-btn:disabled {
            background: rgba(255, 255, 255, 0.05);
            color: var(--slate-400);
            cursor: not-allowed;
        }
        
        /* QRIS Popup Modal */
        .qris-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 20px;
        }
        
        .qris-modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .qris-modal-content {
            background: rgba(15, 25, 40, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(16px);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .qris-modal.show .qris-modal-content {
            transform: scale(1);
        }
        
        .qris-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .qris-title {
            color: var(--slate-100);
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .qris-close {
            background: none;
            border: none;
            color: var(--slate-400);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .qris-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--slate-200);
        }
        
        .qris-amount-info {
            background: rgba(20, 184, 166, 0.1);
            border: 1px solid rgba(20, 184, 166, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .qris-amount-label {
            color: var(--slate-300);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .qris-amount-value {
            color: var(--slate-100);
            font-size: 24px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .qris-amount-note {
            color: var(--teal-300);
            font-size: 12px;
            font-weight: 500;
        }
        
        .qris-code-section {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .qris-code-title {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .qris-code-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: inline-block;
            margin-bottom: 12px;
        }
        
        .qris-code-image {
            width: 200px;
            height: 200px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 14px;
            text-align: center;
        }
        
        .qris-instructions {
            color: var(--slate-300);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .qris-upload-section {
            margin-bottom: 24px;
        }
        
        .qris-upload-title {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .qris-upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 24px 16px;
            text-align: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .qris-upload-area:hover {
            border-color: rgba(20, 184, 166, 0.5);
            background: rgba(20, 184, 166, 0.03);
        }
        
        .qris-upload-area.dragover {
            border-color: var(--teal-500);
            background: rgba(20, 184, 166, 0.08);
            transform: scale(1.02);
        }
        
        .qris-upload-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 12px;
            color: var(--slate-400);
        }
        
        .qris-upload-text {
            color: var(--slate-200);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .qris-upload-subtext {
            color: var(--slate-400);
            font-size: 12px;
        }
        
        .qris-file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .qris-file-preview {
            display: none;
            margin-top: 16px;
        }
        
        .qris-file-preview.show {
            display: block;
        }
        
        .qris-preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            margin: 0 auto;
            display: block;
        }
        
        .qris-file-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .qris-file-name {
            color: var(--slate-200);
            font-size: 14px;
        }
        
        .qris-remove-file {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 6px;
            padding: 4px 8px;
            color: #f87171;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .qris-remove-file:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .qris-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .qris-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            border: none;
        }
        
        .qris-btn:active {
            transform: translateY(1px);
        }
        
        .qris-btn-cancel {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--slate-300);
        }
        
        .qris-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: var(--slate-200);
        }
        
        .qris-btn-confirm {
            background: var(--teal-500);
            color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        }
        
        .qris-btn-confirm:hover {
            background: var(--teal-600);
            box-shadow: 0 2px 6px rgba(20, 184, 166, 0.25);
        }
        
        .qris-btn-confirm:disabled {
            background: rgba(255, 255, 255, 0.05);
            color: var(--slate-400);
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.6;
        }
        
        .qris-btn-confirm:disabled:hover {
            background: rgba(255, 255, 255, 0.05);
            box-shadow: none;
            transform: none;
        }
        
        .qris-btn-confirm:disabled:active {
            transform: none;
        }
        
        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(15, 25, 40, 0.95);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10500;
            max-width: 400px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.error {
            border-left: 4px solid #ef4444;
        }
        
        .notification.success {
            border-left: 4px solid var(--teal-500);
        }
        
        .notification.warning {
            border-left: 4px solid #f59e0b;
        }
        
        .notification-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .notification-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .notification-text {
            flex: 1;
        }
        
        .notification-title {
            color: var(--slate-100);
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .notification-message {
            color: var(--slate-300);
            font-size: 13px;
            line-height: 1.4;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--slate-400);
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mobile Responsive - SAMA PERSIS */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-area {
                padding: 16px;
            }
            
            .deposit-card {
                padding: 20px;
            }
            
            .quick-amounts {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .notification {
                right: 16px;
                left: 16px;
                max-width: none;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 18px;
            }
            
            .content-area {
                padding: 12px;
            }
            
            .deposit-card {
                padding: 16px;
            }
            
            .deposit-header h2 {
                font-size: 20px;
            }
            
            .quick-amounts {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .method-card {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .method-info h3 {
                font-size: 14px;
            }
            
            .method-info p {
                font-size: 13px;
            }
            
            .upload-area {
                padding: 24px 12px;
            }
            
            .upload-text {
                font-size: 14px;
            }
            
            .upload-subtext {
                font-size: 12px;
            }
            
            .unique-code-section {
                padding: 12px;
            }
            
            .unique-code-value {
                font-size: 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .copy-code-btn {
                align-self: flex-start;
            }
            
            .qris-modal-content {
                padding: 20px;
                margin: 10px;
            }
            
            .qris-amount-value {
                font-size: 20px;
            }
            
            .qris-code-image {
                width: 180px;
                height: 180px;
            }
            
            .qris-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .qris-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        /* Loading Animation */
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar - SAMA PERSIS DENGAN DASHBOARD.PHP -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="storage/assets/img/logo/logo_trans.png" alt="AcisPedia" class="sidebar-logo">
                <span class="sidebar-title">AcisPedia</span>
            </div>
            
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                    </div>
                    <span class="menu-text">Dashboard</span>
                    <div class="menu-tooltip">Dashboard</div>
                </a>
                
                <a href="order.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                    </div>
                    <span class="menu-text">Buat Pesanan</span>
                    <div class="menu-tooltip">Buat Pesanan</div>
                </a>
                
                <a href="services.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <span class="menu-text">Layanan</span>
                    <div class="menu-tooltip">Layanan</div>
                </a>
                
                <a href="transactions.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4v-9zM15 11h4a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-4v-9z"/>
                            <path d="M9 7V2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/>
                        </svg>
                    </div>
                    <span class="menu-text">Riwayat Pesanan</span>
                    <div class="menu-tooltip">Riwayat Pesanan</div>
                </a>
                
                <a href="deposit.php" class="menu-item active">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </div>
                    <span class="menu-text">Top Up Saldo</span>
                    <div class="menu-tooltip">Top Up Saldo</div>
                </a>
                
                <a href="deposit_history.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                    </div>
                    <span class="menu-text">Riwayat Deposit</span>
                    <div class="menu-tooltip">Riwayat Deposit</div>
                </a>
        </aside>
        
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Header - SAMA PERSIS -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburgerBtn">
                        <div class="hamburger" id="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                    <h1 class="page-title">Top Up Saldo</h1>
                </div>
                
                <div class="header-right">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)) ?>
                            </div>
                        </button>
                        
                        <div class="profile-menu" id="profileMenu">
                            <div class="profile-menu-header">
                                <div class="profile-info">
                                    <div class="profile-avatar">
                                        <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div class="profile-details">
                                        <h4><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Pengguna') ?></h4>
                                        <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
                                    </div>
                                </div>
                                <div class="balance-info">
                                    <span class="balance-icon" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(20,184,166,.18);border:1px solid rgba(20,184,166,.35);border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.5px;color:var(--teal-300);">RP</span>
                                    &nbsp;Saldo: Rp <?= number_format((int)($user['balance'] ?? 0), 0, ',', '.'); ?>
                                </div>
                            </div>
                            <div class="profile-menu-items">
                                <a href="profile.php" class="profile-menu-item">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Profil Saya
                                </a>
                                <a href="deposit.php" class="profile-menu-item">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                        <line x1="8" y1="21" x2="16" y2="21"/>
                                        <line x1="12" y1="17" x2="12" y2="21"/>
                                    </svg>
                                    Top Up Saldo
                                </a>
                                <a href="auth/logout.php" class="profile-menu-item danger">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16,17 21,12 16,7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    Keluar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area - DEPOSIT CONTENT -->
            <div class="content-area">
                <div class="deposit-container">
                    <!-- Header -->
                    <div class="deposit-header">
                        <h2>Top Up Saldo</h2>
                        <p>Isi ulang saldo Anda untuk melanjutkan pemesanan layanan</p>
                    </div>
                    
                    <!-- Payment Method Selection -->
                    <div class="deposit-card">
                        <div class="method-section">
                            <div class="method-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"/>
                                    <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"/>
                                </svg>
                                Pilih Metode Pembayaran
                            </div>
                            
                            <!-- Method Toggle -->
                            <div class="method-toggle">
                                <div class="method-option active" data-method="qris" id="methodQris">
                                    <div class="method-radio"></div>
                                    <div class="method-icon">QR</div>
                                    <div class="method-details">
                                        <h3>QRIS Payment</h3>
                                        <p>Scan QR Code - Semua bank & e-wallet</p>
                                    </div>
                                </div>
                                
                                <div class="method-option" data-method="conversion" id="methodConversion">
                                    <div class="method-radio"></div>
                                    <div class="method-icon" style="background: linear-gradient(135deg, #0d9488, #14b8a6);">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <path d="M7 16V4M7 4L3 8M7 4L11 8"/>
                                            <path d="M17 8V20M17 20L21 16M17 20L13 16"/>
                                        </svg>
                                    </div>
                                    <div class="method-details">
                                        <h3>Konversi Saldo AcisPayment</h3>
                                        <p>Transfer saldo dari aplikasi AcisPayment</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- QRIS Amount Section -->
                        <div class="amount-section" id="qrisAmountSection">
                            <div class="amount-title">Pilih Nominal</div>
                            <div class="quick-amounts">
                                <div class="quick-amount" data-amount="10000">Rp 10.000</div>
                                <div class="quick-amount" data-amount="20000">Rp 20.000</div>
                                <div class="quick-amount" data-amount="50000">Rp 50.000</div>
                                <div class="quick-amount" data-amount="100000">Rp 100.000</div>
                                <div class="quick-amount" data-amount="200000">Rp 200.000</div>
                            </div>
                            
                            <div class="custom-amount">
                                <div class="form-group">
                                    <label class="form-label" for="amount">
                                        <span class="label-icon">Rp</span>
                                        Atau masukkan nominal manual
                                    </label>
                                    <div class="input-with-prefix">
                                        <span class="input-prefix">Rp</span>
                                        <input 
                                            type="text" 
                                            id="amount" 
                                            name="amount" 
                                            class="form-input with-prefix" 
                                            placeholder="0" 
                                            inputmode="numeric"
                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                            oninput="this.value = this.value.replace(/[^0-9.]/g, '')"
                                        >
                                    </div>
                                    <div class="amount-limits">
                                        <span>Minimal: Rp 1.000</span>
                                        <span>Maksimal: Rp 200.000</span>
                                    </div>
                                </div>
                                
                                <!-- Unique Code Section -->
                                <div class="unique-code-section" id="uniqueCodeSection" style="display: none;">
                                    <div class="unique-code-title">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"/>
                                            <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"/>
                                            <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"/>
                                        </svg>
                                        Nominal Transfer dengan Kode Unik
                                    </div>
                                    <div class="unique-code-value">
                                        <span id="finalAmount">Rp 0</span>
                                        <button type="button" class="copy-code-btn" id="copyAmountBtn">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/>
                                            </svg>
                                            Salin
                                        </button>
                                    </div>
                                    <div class="unique-code-info">
                                        Transfer sesuai nominal di atas (termasuk kode unik). Kode unik membantu admin memverifikasi deposit Anda.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conversion Form Section -->
                        <div class="conversion-section" id="conversionSection" style="display: none;">
                            <div class="conversion-form">
                                <div class="form-group">
                                    <label class="form-label" for="acisUsername">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke-width="2"/>
                                            <circle cx="12" cy="7" r="4" stroke-width="2"/>
                                        </svg>
                                        Username AcisPayment
                                    </label>
                                    <input 
                                        type="text" 
                                        id="acisUsername" 
                                        name="acisUsername" 
                                        class="form-input" 
                                        placeholder="Masukkan username AcisPayment Anda"
                                        required
                                    >
                                    <div class="input-hint">Username yang terdaftar di aplikasi AcisPayment</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="phoneNumber">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" stroke-width="2"/>
                                        </svg>
                                        Nomor HP
                                    </label>
                                    <input 
                                        type="tel" 
                                        id="phoneNumber" 
                                        name="phoneNumber" 
                                        class="form-input" 
                                        placeholder="08xxxxxxxxxx"
                                        pattern="[0-9]{10,20}"
                                        inputmode="numeric"
                                        maxlength="20"
                                        required
                                        onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.length > 20) this.value = this.value.slice(0,20);"
                                    >
                                    <div class="input-hint">Nomor HP yang terdaftar di AcisPayment</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="email">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke-width="2"/>
                                            <polyline points="22,6 12,13 2,6" stroke-width="2"/>
                                        </svg>
                                        Email
                                    </label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        class="form-input" 
                                        placeholder="email@example.com"
                                        required
                                    >
                                    <div class="input-hint">Email yang terdaftar di AcisPayment</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="conversionAmount">
                                        <span class="label-icon">Rp</span>
                                        Nominal Konversi
                                    </label>
                                    <div class="input-with-prefix">
                                        <span class="input-prefix">Rp</span>
                                        <input 
                                            type="text" 
                                            id="conversionAmount" 
                                            name="conversionAmount" 
                                            class="form-input with-prefix" 
                                            placeholder="0"
                                            inputmode="numeric"
                                            required
                                        >
                                    </div>
                                    <div class="amount-limits">
                                        <span>Minimal: Rp 1.000</span>
                                        <span>Maksimal: Rp 10.000.000</span>
                                    </div>
                                    <div class="fee-info" style="margin-top: 8px; padding: 10px 14px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px; font-size: 12px; color: #fbbf24; line-height: 1.6;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                            <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                            <line x1="12" y1="16" x2="12" y2="12" stroke-width="2"/>
                                            <line x1="12" y1="8" x2="12.01" y2="8" stroke-width="2"/>
                                        </svg>
                                        Biaya konversi <strong>0,7%</strong>
                                    </div>
                                    
                                    <!-- Conversion Summary (shown after input) -->
                                    <div id="conversionSummary" style="display: none; margin-top: 12px; padding: 12px; background: rgba(20, 184, 166, 0.1); border: 1px solid rgba(20, 184, 166, 0.3); border-radius: 8px;">
                                        <div style="font-size: 13px; color: var(--teal-300); margin-bottom: 8px; font-weight: 600;">Ringkasan Konversi:</div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--slate-300); margin-bottom: 4px;">
                                            <span>Nominal yang diminta:</span>
                                            <span id="summaryAmount">Rp 0</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--slate-300); margin-bottom: 4px;">
                                            <span>Biaya konversi (0,7%):</span>
                                            <span id="summaryFee">Rp 0</span>
                                        </div>
                                        <div style="border-top: 1px solid rgba(20, 184, 166, 0.3); margin: 8px 0; padding-top: 8px;"></div>
                                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--teal-200); font-weight: 600;">
                                            <span>Total yang harus Anda transfer:</span>
                                            <span id="summaryTotal">Rp 0</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #4ade80; margin-top: 6px; font-weight: 500;">
                                            <span>Saldo masuk ke SMM Panel:</span>
                                            <span id="summaryFinal">Rp 0</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="conversion-info-box">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                        <line x1="12" y1="16" x2="12" y2="12" stroke-width="2"/>
                                        <line x1="12" y1="8" x2="12.01" y2="8" stroke-width="2"/>
                                    </svg>
                                    <div>
                                        <strong>Cara Kerja:</strong>
                                        <ol style="margin: 8px 0 0; padding-left: 20px; font-size: 13px;">
                                            <li>Isi username AcisPayment dan nominal yang ingin dikonversi</li>
                                            <li>Permintaan akan masuk ke queue dan menunggu verifikasi admin</li>
                                            <li>Admin akan mengecek saldo AcisPayment Anda</li>
                                            <li>Setelah disetujui, saldo akan otomatis masuk ke akun SMM Panel</li>
                                            <li>Proses verifikasi membutuhkan waktu maksimal 1x24 jam</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="submit-section">
                            <button type="button" class="submit-btn" id="submitBtn" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="9,11 12,14 22,4" stroke-width="2"/>
                                    <path d="M21,12v7a2,2 0 0,1 -2,2H5a2,2 0 0,1 -2,-2V5a2,2 0 0,1 2,-2h11" stroke-width="2"/>
                                </svg>
                                <span id="submitBtnText">Lanjutkan</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Info Card -->
                    <div class="deposit-card" id="infoCard">
                        <div class="method-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                <path d="M9,9 h6 v6 h-6 z" stroke-width="2"/>
                            </svg>
                            Informasi Penting
                        </div>
                        <div id="qrisInfo" style="color: var(--slate-300); font-size: 14px; line-height: 1.6;">
                            <strong style="color: var(--teal-300); display: block; margin-bottom: 8px;">Pembayaran QRIS:</strong>
                            <ol style="margin: 0; padding-left: 20px;">
                                <li>Transfer sesuai dengan nominal yang tertera (termasuk kode unik)</li>
                                <li>Scan QR Code dengan aplikasi mobile banking atau e-wallet</li>
                                <li>Upload bukti transfer yang jelas dan dapat dibaca</li>
                                <li>Proses verifikasi membutuhkan waktu maksimal 1x24 jam</li>
                                <li>Saldo akan otomatis masuk setelah diverifikasi admin</li>
                            </ol>
                        </div>
                        <div id="conversionInfo" style="display: none; color: var(--slate-300); font-size: 14px; line-height: 1.6;">
                            <strong style="color: var(--teal-300); display: block; margin-bottom: 8px;">Konversi Saldo AcisPayment:</strong>
                            <ol style="margin: 0; padding-left: 20px;">
                                <li>Pastikan username AcisPayment yang Anda masukkan benar</li>
                                <li>Pastikan saldo di AcisPayment mencukupi untuk konversi</li>
                                <li>Permintaan akan masuk ke queue untuk diverifikasi admin</li>
                                <li>Admin akan mengecek saldo Anda di aplikasi AcisPayment</li>
                                <li>Setelah disetujui, saldo langsung masuk ke akun SMM Panel</li>
                                <li>Proses verifikasi membutuhkan waktu maksimal 1x24 jam</li>
                                <li>Jika ditolak, Anda akan menerima notifikasi dengan alasan penolakan</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QRIS Modal -->
    <div class="qris-modal" id="qrisModal">
        <div class="qris-modal-content">
            <div class="qris-header">
                <h3 class="qris-title">Pembayaran QRIS</h3>
                <button class="qris-close" id="qrisCloseBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="6" x2="6" y2="18" stroke-width="2"/>
                        <line x1="6" y1="6" x2="18" y2="18" stroke-width="2"/>
                    </svg>
                </button>
            </div>
            
            <!-- Amount Information -->
            <div class="qris-amount-info">
                <div class="qris-amount-label">Total Transfer</div>
                <div class="qris-amount-value" id="qrisAmountDisplay">Rp 0</div>
                <div class="qris-amount-note">Termasuk kode unik untuk verifikasi</div>
            </div>
            
            <!-- QR Code Section -->
            <div class="qris-code-section">
                <div class="qris-code-title">Scan QR Code</div>
                <div class="qris-code-container">
                    <div class="qris-code-image">
                        QR Code akan muncul di sini<br>
                        (Demo purposes)
                    </div>
                </div>
                <div class="qris-instructions">
                    1. Buka aplikasi mobile banking atau e-wallet<br>
                    2. Pilih menu "Scan QR" atau "QRIS"<br>
                    3. Scan QR Code di atas<br>
                    4. Pastikan nominal sesuai dengan yang tertera
                </div>
            </div>
            
            <!-- Upload Proof Section -->
            <div class="qris-upload-section">
                <div class="qris-upload-title">Upload Bukti Transfer</div>
                <div class="qris-upload-area" id="qrisUploadArea">
                    <input type="file" id="qrisProofFile" name="proof" class="qris-file-input" accept="image/*" required>
                    <div class="qris-upload-content">
                        <svg class="qris-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke-width="2"/>
                            <polyline points="7,10 12,15 17,10" stroke-width="2"/>
                            <line x1="12" y1="15" x2="12" y2="3" stroke-width="2"/>
                        </svg>
                        <div class="qris-upload-text">Klik atau drag bukti transfer ke sini</div>
                        <div class="qris-upload-subtext">Format: JPG, PNG, JPEG (Max 5MB)</div>
                    </div>
                </div>
                <div class="qris-file-preview" id="qrisFilePreview">
                    <img id="qrisPreviewImage" class="qris-preview-image" alt="Preview">
                    <div class="qris-file-info">
                        <span class="qris-file-name" id="qrisFileName"></span>
                        <button type="button" class="qris-remove-file" id="qrisRemoveFile">Hapus</button>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="qris-actions">
                <button type="button" class="qris-btn qris-btn-cancel" id="qrisCancelBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="6" x2="6" y2="18" stroke-width="2"/>
                        <line x1="6" y1="6" x2="18" y2="18" stroke-width="2"/>
                    </svg>
                    Batal
                </button>
                <button type="button" class="qris-btn qris-btn-confirm" id="qrisConfirmBtn" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke-width="2"/>
                        <polyline points="22,4 12,14.01 9,11.01" stroke-width="2"/>
                    </svg>
                    Konfirmasi Deposit
                </button>
            </div>
        </div>
    </div>

    <script>
        // JAVASCRIPT SAMA PERSIS DENGAN DASHBOARD.PHP
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebar();
            initializeProfile();
            initializeDeposit();
        });

        // Sidebar functionality - SAMA PERSIS
        function initializeSidebar() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const hamburger = document.getElementById('hamburger');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            hamburgerBtn.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    // Mobile: toggle sidebar visibility
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                    hamburger.classList.toggle('active');
                } else {
                    // Desktop: toggle sidebar collapse
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    hamburger.classList.toggle('active');
                }
            });

            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                hamburger.classList.remove('active');
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    mainContent.classList.remove('expanded');
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.classList.add('collapsed');
                    } else {
                        mainContent.classList.remove('collapsed');
                    }
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('collapsed');
                    if (!sidebar.classList.contains('show')) {
                        mainContent.classList.add('expanded');
                    }
                }
            });
        }

        // Profile dropdown functionality - SAMA PERSIS
        function initializeProfile() {
            const profileBtn = document.getElementById('profileBtn');
            const profileMenu = document.getElementById('profileMenu');

            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            // Close profile menu when clicking outside
            document.addEventListener('click', function() {
                profileMenu.classList.remove('show');
            });

            profileMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // DEPOSIT FUNCTIONALITY
        function initializeDeposit() {
            // Method toggle elements
            const methodQris = document.getElementById('methodQris');
            const methodConversion = document.getElementById('methodConversion');
            const qrisAmountSection = document.getElementById('qrisAmountSection');
            const conversionSection = document.getElementById('conversionSection');
            
            // QRIS elements
            const quickAmounts = document.querySelectorAll('.quick-amount');
            const amountInput = document.getElementById('amount');
            const submitBtn = document.getElementById('submitBtn');
            const submitBtnText = document.getElementById('submitBtnText');
            const uniqueCodeSection = document.getElementById('uniqueCodeSection');
            const finalAmount = document.getElementById('finalAmount');
            const copyAmountBtn = document.getElementById('copyAmountBtn');
            
            // Conversion elements
            const acisUsernameInput = document.getElementById('acisUsername');
            const phoneNumberInput = document.getElementById('phoneNumber');
            const emailInput = document.getElementById('email');
            const conversionAmountInput = document.getElementById('conversionAmount');
            const conversionSummary = document.getElementById('conversionSummary');
            const summaryAmount = document.getElementById('summaryAmount');
            const summaryFee = document.getElementById('summaryFee');
            const summaryTotal = document.getElementById('summaryTotal');
            const summaryFinal = document.getElementById('summaryFinal');
            
            // QRIS Modal elements
            const qrisModal = document.getElementById('qrisModal');
            const qrisCloseBtn = document.getElementById('qrisCloseBtn');
            const qrisCancelBtn = document.getElementById('qrisCancelBtn');
            const qrisConfirmBtn = document.getElementById('qrisConfirmBtn');
            const qrisAmountDisplay = document.getElementById('qrisAmountDisplay');
            const qrisUploadArea = document.getElementById('qrisUploadArea');
            const qrisFileInput = document.getElementById('qrisProofFile');
            const qrisFilePreview = document.getElementById('qrisFilePreview');
            const qrisPreviewImage = document.getElementById('qrisPreviewImage');
            const qrisFileName = document.getElementById('qrisFileName');
            const qrisRemoveFile = document.getElementById('qrisRemoveFile');
            
            let selectedAmount = 0;
            let uniqueCode = 0;
            let finalAmountWithCode = 0;
            let uploadedFile = null;
            let currentMethod = 'qris'; // default method

            // Method toggle functionality
            methodQris.addEventListener('click', function() {
                if (currentMethod !== 'qris') {
                    currentMethod = 'qris';
                    methodQris.classList.add('active');
                    methodConversion.classList.remove('active');
                    qrisAmountSection.style.display = 'block';
                    conversionSection.style.display = 'none';
                    submitBtn.disabled = selectedAmount <= 0;
                    // Toggle info card
                    document.getElementById('qrisInfo').style.display = 'block';
                    document.getElementById('conversionInfo').style.display = 'none';
                    // Reset unique code section
                    uniqueCodeSection.style.display = 'none';
                    pendingAmount = 0;
                }
            });
            
            methodConversion.addEventListener('click', function() {
                if (currentMethod !== 'conversion') {
                    currentMethod = 'conversion';
                    methodConversion.classList.add('active');
                    methodQris.classList.remove('active');
                    conversionSection.style.display = 'block';
                    qrisAmountSection.style.display = 'none';
                    validateConversionForm();
                    // Toggle info card
                    document.getElementById('qrisInfo').style.display = 'none';
                    document.getElementById('conversionInfo').style.display = 'block';
                    // Reset unique code section
                    uniqueCodeSection.style.display = 'none';
                    pendingAmount = 0;
                }
            });
            
            // Validate conversion form
            function validateConversionForm() {
                const username = acisUsernameInput.value.trim();
                const phone = phoneNumberInput.value.trim();
                const email = emailInput.value.trim();
                const amountRaw = conversionAmountInput.value.replace(/\./g, '');
                const amount = parseInt(amountRaw, 10);
                
                // Clear previous errors
                clearConversionErrors();
                
                let isValid = true;
                
                // Username validation
                if (!username) {
                    isValid = false;
                }
                
                // Phone validation (10-20 digits)
                if (!phone) {
                    isValid = false;
                } else if (phone.length < 10) {
                    showConversionError(phoneNumberInput, 'Nomor HP minimal 10 digit');
                    isValid = false;
                } else if (phone.length > 20) {
                    showConversionError(phoneNumberInput, 'Nomor HP maksimal 20 digit');
                    isValid = false;
                } else if (!/^[0-9]{10,20}$/.test(phone)) {
                    showConversionError(phoneNumberInput, 'Nomor HP hanya boleh berisi angka');
                    isValid = false;
                }
                
                // Email validation
                if (!email) {
                    isValid = false;
                } else {
                    const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
                    if (!emailRegex.test(email)) {
                        showConversionError(emailInput, 'Format email tidak valid');
                        isValid = false;
                    }
                }
                
                // Amount validation
                if (!amountRaw || amount === 0 || isNaN(amount)) {
                    isValid = false;
                    conversionSummary.style.display = 'none';
                } else if (amount < 1000) {
                    showConversionError(conversionAmountInput, 'Nominal minimal Rp 1.000');
                    isValid = false;
                    conversionSummary.style.display = 'none';
                } else if (amount > 10000000) {
                    showConversionError(conversionAmountInput, 'Nominal maksimal Rp 10.000.000');
                    isValid = false;
                    conversionSummary.style.display = 'none';
                } else {
                    // Calculate and show conversion summary
                    const fee = Math.ceil(amount * 0.007); // Round up biaya
                    const totalTransfer = amount + fee;
                    const finalAmount = amount; // Yang masuk ke user tetap sesuai input
                    
                    summaryAmount.textContent = 'Rp ' + amount.toLocaleString('id-ID');
                    summaryFee.textContent = 'Rp ' + fee.toLocaleString('id-ID');
                    summaryTotal.textContent = 'Rp ' + totalTransfer.toLocaleString('id-ID');
                    summaryFinal.textContent = 'Rp ' + finalAmount.toLocaleString('id-ID');
                    conversionSummary.style.display = 'block';
                }
                
                submitBtn.disabled = !isValid;
            }
            
            // Show conversion form error
            function showConversionError(inputElement, message) {
                inputElement.style.borderColor = '#ef4444';
                
                // Remove existing error
                const existingError = inputElement.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 6px; display: block;';
                errorDiv.textContent = message;
                inputElement.parentNode.appendChild(errorDiv);
            }
            
            // Clear all conversion form errors
            function clearConversionErrors() {
                [acisUsernameInput, phoneNumberInput, emailInput, conversionAmountInput].forEach(input => {
                    input.style.borderColor = '';
                    const error = input.parentNode.querySelector('.error-message');
                    if (error) {
                        error.remove();
                    }
                });
            }
            
            // Listen to conversion input changes
            acisUsernameInput.addEventListener('input', validateConversionForm);
            phoneNumberInput.addEventListener('input', validateConversionForm);
            emailInput.addEventListener('input', validateConversionForm);
            
            // Format number with thousand separator
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
            
            // Handle conversion amount input with formatting
            conversionAmountInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\./g, ''); // Remove existing dots
                value = value.replace(/[^0-9]/g, ''); // Remove non-numeric
                
                if (value) {
                    e.target.value = formatNumber(value);
                }
                validateConversionForm();
            });
            
            // Handle QRIS amount input with formatting
            amountInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\./g, ''); // Remove existing dots
                value = value.replace(/[^0-9]/g, ''); // Remove non-numeric
                
                if (value) {
                    const numValue = parseInt(value);
                    e.target.value = formatNumber(value);
                    selectedAmount = numValue;
                    
                    // Remove active from quick buttons
                    quickAmounts.forEach(btn => btn.classList.remove('active'));
                    
                    // Update final amount
                    updateFinalAmount(numValue);
                    
                    // Validate
                    validateAmount(numValue);
                    validateForm();
                } else {
                    selectedAmount = 0;
                    uniqueCodeSection.style.display = 'none';
                    validateForm();
                }
            });

            // Generate unique code based on user ID and timestamp
            function generateUniqueCode() {
                const userId = <?= $user['id'] ?? 1 ?>;
                const timestamp = Date.now();
                // Generate code between 100-200 for smaller unique code
                const code = ((userId * 17 + timestamp % 1000) % 101) + 100;
                return code;
            }

            // Store selected amount (kode unik akan di-generate saat klik Lanjutkan)
            function updateFinalAmount(baseAmount) {
                selectedAmount = baseAmount;
                // Tidak show kode unik di sini, akan muncul di modal saat klik Lanjutkan
            }

            // Copy amount to clipboard
            copyAmountBtn.addEventListener('click', function() {
                navigator.clipboard.writeText(finalAmountWithCode.toString()).then(function() {
                    // Change button text temporarily
                    const originalHTML = copyAmountBtn.innerHTML;
                    copyAmountBtn.innerHTML = `
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Tersalin
                    `;
                    copyAmountBtn.style.background = 'rgba(34, 197, 94, 0.2)';
                    copyAmountBtn.style.borderColor = 'rgba(34, 197, 94, 0.4)';
                    copyAmountBtn.style.color = '#4ade80';
                    
                    setTimeout(() => {
                        copyAmountBtn.innerHTML = originalHTML;
                        copyAmountBtn.style.background = '';
                        copyAmountBtn.style.borderColor = '';
                        copyAmountBtn.style.color = '';
                    }, 2000);
                    
                    showNotification('success', 'Nominal tersalin', 'Nominal dengan kode unik telah disalin ke clipboard');
                }).catch(function() {
                    showNotification('error', 'Gagal menyalin', 'Tidak dapat menyalin ke clipboard. Salin manual: ' + finalAmountWithCode);
                });
            });

            // Quick amount selection
            quickAmounts.forEach(btn => {
                btn.addEventListener('click', function() {
                    const amount = parseInt(this.dataset.amount);
                    
                    // Remove active class from all buttons
                    quickAmounts.forEach(b => b.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Set amount input value with thousand separator
                    amountInput.value = formatNumber(amount.toString());
                    selectedAmount = amount;
                    
                    // Update final amount with unique code
                    updateFinalAmount(amount);
                    
                    // Clear any previous amount input errors
                    clearAmountError();
                    
                    // Validate form
                    validateForm();
                });
            });

            // Custom amount input - REMOVED (sudah di-handle di atas dengan formatting)

            // Submit button handler (handles both QRIS and conversion)
            submitBtn.addEventListener('click', function() {
                if (currentMethod === 'qris') {
                    // QRIS payment flow
                    if (!validateAmount(selectedAmount)) {
                        showNotification('error', 'Nominal tidak valid', 'Periksa kembali nominal yang Anda masukkan');
                        return;
                    }
                    openQrisModal();
                } else if (currentMethod === 'conversion') {
                    // Conversion request flow
                    submitConversionRequest();
                }
            });

            // QRIS Modal Functions
            function openQrisModal() {
                // Generate kode unik baru setiap kali modal dibuka
                uniqueCode = generateUniqueCode();
                finalAmountWithCode = selectedAmount + uniqueCode;
                qrisAmountDisplay.textContent = `Rp ${finalAmountWithCode.toLocaleString('id-ID')}`;
                qrisModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeQrisModal() {
                qrisModal.classList.remove('show');
                document.body.style.overflow = '';
                clearQrisFile();
            }

            // QRIS Modal event listeners
            qrisCloseBtn.addEventListener('click', closeQrisModal);
            qrisCancelBtn.addEventListener('click', closeQrisModal);
            
            // Close modal when clicking outside
            qrisModal.addEventListener('click', function(e) {
                if (e.target === qrisModal) {
                    closeQrisModal();
                }
            });

            // QRIS File upload handling
            qrisUploadArea.addEventListener('click', function() {
                qrisFileInput.click();
            });

            qrisUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            qrisUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            qrisUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleQrisFileSelect(files[0]);
                }
            });

            qrisFileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleQrisFileSelect(e.target.files[0]);
                }
            });

            qrisRemoveFile.addEventListener('click', function() {
                clearQrisFile();
            });

            // QRIS Confirm button
            qrisConfirmBtn.addEventListener('click', function() {
                handleQrisSubmit();
            });

            // QRIS file selection handler
            function handleQrisFileSelect(file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    showNotification('error', 'Format tidak valid', 'Harap upload file gambar (JPG, PNG, JPEG)');
                    return;
                }

                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('error', 'File terlalu besar', 'Ukuran file maksimal 5MB');
                    return;
                }

                uploadedFile = file;
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    qrisPreviewImage.src = e.target.result;
                    qrisFileName.textContent = file.name;
                    qrisFilePreview.classList.add('show');
                    qrisUploadArea.style.display = 'none';
                };
                reader.readAsDataURL(file);
                
                validateQrisForm();
            }

            // Clear QRIS file
            function clearQrisFile() {
                uploadedFile = null;
                qrisFileInput.value = '';
                qrisFilePreview.classList.remove('show');
                qrisUploadArea.style.display = 'block';
                validateQrisForm();
            }

            // QRIS form validation
            function validateQrisForm() {
                const isFileUploaded = uploadedFile !== null;
                qrisConfirmBtn.disabled = !isFileUploaded;
            }

            // Handle QRIS form submission
            async function handleQrisSubmit() {
                if (!uploadedFile) {
                    showNotification('error', 'Bukti transfer diperlukan', 'Harap upload bukti transfer Anda');
                    return;
                }
                
                // Disable button and show loading
                qrisConfirmBtn.disabled = true;
                qrisConfirmBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    </svg>
                    <span>Mengirim...</span>
                `;
                
                try {
                    // Send deposit request to API
                    const response = await fetch('api/deposit.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'create_deposit',
                            amount: selectedAmount,
                            unique_code: uniqueCode,
                            final_amount: finalAmountWithCode,
                            payment_method: 'qris'
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Show success notification
                        showNotification('success', 'Deposit berhasil dikirim', 
                            `Deposit sebesar Rp ${selectedAmount.toLocaleString('id-ID')} (Total transfer: Rp ${finalAmountWithCode.toLocaleString('id-ID')}) telah dikirim untuk verifikasi. Saldo akan masuk dalam 1x24 jam.`);
                        
                        // Close modal and reset form
                        closeQrisModal();
                        setTimeout(() => {
                            resetForm();
                        }, 1000);
                    } else {
                        showNotification('error', 'Gagal mengirim deposit', data.message || 'Terjadi kesalahan saat memproses deposit');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('error', 'Kesalahan', 'Terjadi kesalahan saat mengirim deposit. Silakan coba lagi.');
                } finally {
                    // Re-enable button
                    qrisConfirmBtn.disabled = false;
                    qrisConfirmBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Konfirmasi Pembayaran</span>
                    `;
                }
            }

            // Amount validation
            function validateAmount(amount) {
                clearAmountError();
                
                if (amount < 1000) {
                    showAmountError('Nominal minimal Rp 1.000');
                    return false;
                }
                
                if (amount > 200000) {
                    showAmountError('Nominal maksimal Rp 200.000');
                    return false;
                }
                
                return true;
            }

            // Show amount error
            function showAmountError(message) {
                const input = amountInput;
                const inputWrapper = input.parentNode;
                input.style.borderColor = '#ef4444';
                
                // Remove existing error message
                const formGroup = inputWrapper.parentNode;
                const existingError = formGroup.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add error message after input wrapper
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 6px; display: block; width: 100%;';
                errorDiv.textContent = message;
                formGroup.insertBefore(errorDiv, inputWrapper.nextSibling);
            }

            // Clear amount error
            function clearAmountError() {
                const input = amountInput;
                const inputWrapper = input.parentNode;
                input.style.borderColor = '';
                
                const formGroup = inputWrapper.parentNode;
                const existingError = formGroup.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
            }

            // Form validation
            function validateForm() {
                const isAmountValid = selectedAmount >= 1000 && selectedAmount <= 200000;
                
                if (isAmountValid) {
                    submitBtn.disabled = false;
                    submitBtnText.textContent = 'Lanjutkan';
                } else {
                    submitBtn.disabled = true;
                    submitBtnText.textContent = 'Lanjutkan';
                }
            }

            // Amount validation
            function validateAmount(amount) {
                clearAmountError();
                
                if (amount < 1000) {
                    showAmountError('Nominal minimal Rp 1.000');
                    return false;
                }
                
                if (amount > 200000) {
                    showAmountError('Nominal maksimal Rp 200.000');
                    return false;
                }
                
                return true;
            }

            // Reset form
            function resetForm() {
                selectedAmount = 0;
                finalAmountWithCode = 0;
                uniqueCode = 0;
                uploadedFile = null;
                amountInput.value = '';
                acisUsernameInput.value = '';
                phoneNumberInput.value = '';
                emailInput.value = '';
                conversionAmountInput.value = '';
                quickAmounts.forEach(btn => btn.classList.remove('active'));
                uniqueCodeSection.style.display = 'none';
                clearAmountError();
                validateForm();
            }
            
            // Submit conversion request
            async function submitConversionRequest() {
                const username = acisUsernameInput.value.trim();
                const phone = phoneNumberInput.value.trim();
                const email = emailInput.value.trim();
                const amountRaw = conversionAmountInput.value.replace(/\./g, '');
                const amount = parseInt(amountRaw, 10) || 0;
                
                // Validate
                if (!username) {
                    showNotification('error', 'Username diperlukan', 'Masukkan username AcisPayment Anda');
                    acisUsernameInput.focus();
                    return;
                }
                
                if (!phone) {
                    showNotification('error', 'Nomor HP diperlukan', 'Masukkan nomor HP yang terdaftar');
                    phoneNumberInput.focus();
                    return;
                }
                
                if (!email) {
                    showNotification('error', 'Email diperlukan', 'Masukkan email yang terdaftar');
                    emailInput.focus();
                    return;
                }
                
                if (amount < 1000) {
                    showNotification('error', 'Nominal terlalu kecil', 'Minimal konversi adalah Rp 1.000');
                    conversionAmountInput.focus();
                    return;
                }
                
                if (amount > 10000000) {
                    showNotification('error', 'Nominal terlalu besar', 'Maksimal konversi adalah Rp 10.000.000');
                    conversionAmountInput.focus();
                    return;
                }
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtnText.textContent = 'Mengirim...';
                
                try {
                    const response = await fetch('api/balance_conversion.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'submit_conversion',
                            acispayment_username: username,
                            phone_number: phone,
                            email: email,
                            amount: amount
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification('success', 'Permintaan Terkirim', data.message + ' Request ID: #' + data.request_id);
                        resetForm();
                    } else {
                        showNotification('error', 'Gagal', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('error', 'Kesalahan', 'Terjadi kesalahan saat mengirim permintaan');
                } finally {
                    submitBtn.disabled = false;
                    submitBtnText.textContent = 'Kirim Permintaan';
                    validateConversionForm();
                }
            }
        }

        // Notification system
        function showNotification(type, title, message) {
            // Remove existing notification
            const existing = document.querySelector('.notification');
            if (existing) {
                existing.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const iconSvg = type === 'success' 
                ? '<path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
                : type === 'error'
                ? '<circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M15 9l-6 6m0-6l6 6" stroke-width="2" stroke-linecap="round"/>'
                : '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke-width="2"/><line x1="12" y1="9" x2="12" y2="13" stroke-width="2"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2"/>';
            
            notification.innerHTML = `
                <div class="notification-content">
                    <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        ${iconSvg}
                    </svg>
                    <div class="notification-text">
                        <div class="notification-title">${title}</div>
                        <div class="notification-message">${message}</div>
                    </div>
                    <button class="notification-close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="18" y1="6" x2="6" y2="18" stroke-width="2"/>
                            <line x1="6" y1="6" x2="18" y2="18" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                hideNotification(notification);
            }, 5000);
            
            // Close button handler
            notification.querySelector('.notification-close').addEventListener('click', () => {
                hideNotification(notification);
            });
        }

        function hideNotification(notification) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    </script>
</body>
</html>
