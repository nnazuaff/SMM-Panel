<?php
require_once __DIR__ . '/includes/auth.php';
auth_require();
auth_refresh_balance();
$user = auth_user();
$activePage = 'order';
$sectionTitle = 'Buat Pesanan';

// Include Medanpedia API
require_once __DIR__ . '/api/MedanpediaAPI.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan - AcisPedia</title>
    <link rel="icon" href="storage/assets/img/logo/logo_trans.png">
    
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style-v2.css">
    
    
    <style>
        /* Dashboard Layout */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Enhanced */
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
        
        /* Top Header */
        .top-header {
            background: rgba(255,255,255,.05);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            position: sticky; /* stay above scrolling content */
            top: 0;
            z-index: 4500; /* ensure header sits above content */
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
        
        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
            z-index: 2500; /* ensure above form */
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
            position: absolute; /* stays within header for positioning */
            top: 100%;
            right: 0;
            background: rgba(15,25,40,.85);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            box-shadow: 0 10px 32px -4px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.05);
            backdrop-filter: blur(16px) saturate(140%);
            min-width: 280px;
            z-index: 6000; /* higher than any form container */
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Two Column Layout */
        .order-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            align-items: start;
        }
        
        /* Desktop: Order form on left, Info on right */
        @media (min-width: 1025px) {
            .order-container {
                order: 1;
            }
            
            .info-container {
                order: 2;
            }
        }
        
        /* Information Box */
        .info-container {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: sticky;
            top: 100px;
        }
        
        .info-header {
            background: linear-gradient(135deg, var(--navy-600), var(--navy-700));
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        
        .info-header-icon {
            width: 20px;
            height: 20px;
            color: var(--teal-300);
        }
        
        .info-header-icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .info-header h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
        
        .info-content {
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .info-section {
            margin-bottom: 24px;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--teal-300);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-section-title::before {
            content: '●';
            color: var(--teal-500);
            font-size: 12px;
        }
        
        .info-text {
            font-size: 13px;
            line-height: 1.5;
            color: var(--slate-200);
            margin-bottom: 8px;
        }
        
        .info-steps {
            list-style: none;
            padding: 0;
            margin: 12px 0;
        }
        
        .info-steps li {
            font-size: 13px;
            line-height: 1.5;
            color: var(--slate-200);
            margin-bottom: 6px;
            padding-left: 20px;
            position: relative;
        }
        
        .info-steps li::before {
            content: counter(step-counter);
            counter-increment: step-counter;
            position: absolute;
            left: 0;
            top: 0;
            background: var(--teal-500);
            color: white;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }
        
        .info-steps {
            counter-reset: step-counter;
        }
        
        .info-warning {
            background: rgba(245,158,11,.1);
            border: 1px solid rgba(245,158,11,.25);
            border-radius: 8px;
            padding: 12px;
            margin: 12px 0;
        }
        
        .info-warning .info-text {
            color: #fcd34d;
            margin-bottom: 0;
        }
        
        /* Order Form Styles */
        .order-container {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(10px);
            padding: 0;
            overflow: hidden;
            position: relative;
            z-index: 1; /* keep low so dropdown overlays */
        }
        
        .order-header {
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .order-header-icon {
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .order-header-icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .order-header h1 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .order-form {
            padding: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--slate-200);
            margin-bottom: 8px;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: rgba(15,41,66,.6);
            color: var(--slate-100);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--teal-500);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
            background: rgba(15,41,66,.75);
        }
        
        .form-textarea {
            resize: none; /* prevent manual resize */
            min-height: 140px;
            pointer-events: none; /* make read-only feel */
            overflow-y: auto;
        }
    .service-desc-name {font-weight:600;font-size:15px;margin-bottom:4px;letter-spacing:.2px;}
    .service-desc-meta {font-size:12px;color:var(--slate-300);margin-bottom:6px;}
    .service-desc-meta strong {color: var(--teal-300);}
    .service-desc-sep {border:0;border-top:1px solid rgba(255,255,255,.08);margin:10px 0 8px;}
    .service-desc-label {font-size:12px;font-weight:600;color:var(--slate-300);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
    .service-desc-body {font-size:13px;line-height:1.55;}
    .service-desc-avgtime {margin-top:10px;font-size:12px;color:var(--teal-300);font-weight:500;}
        
        .form-select option {
            background: var(--navy-800);
            color: var(--slate-100);
        }
        
        /* Quantity input adjustments (manual only) */
        .quantity-input {
            width: 220px;
            text-align: left;
            padding: 10px 14px;
        }
        /* Remove native number spinners */
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .quantity-input[type=number] { -moz-appearance: textfield; }
        
        .quantity-info {
            display: flex;
            gap: 16px;
            margin-top: 4px;
        }
        
        .quantity-badge {
            background: rgba(20,184,166,.12);
            color: var(--teal-300);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .price-input {
            font-weight: 600;
            color: var(--teal-300);
        }
        
        .price-input::before {
            content: 'Rp ';
            color: var(--slate-300);
            font-weight: normal;
        }
    .form-input.invalid {border-color:#f87171 !important; box-shadow:0 0 0 3px rgba(248,113,113,.25);}        
        
        .order-actions {
            padding: 24px;
            background: rgba(255,255,255,.03);
            border-top: 1px solid rgba(255,255,255,.08);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .btn-order {
            background: #14b8a6;
            border: none;
            border-radius: 10px;
            padding: 15px 36px;
            color: white;
            font-size: 15px;
            font-weight: 500;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 14px 0 rgba(20, 184, 166, 0.25);
            min-width: 180px;
            letter-spacing: 0.3px;
        }
        
        .btn-order::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .btn-order:hover:not(:disabled) {
            background: #0d9488;
            box-shadow: 0 6px 20px 0 rgba(20, 184, 166, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-order:hover:not(:disabled)::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-order:active:not(:disabled) {
            transform: translateY(0px);
            box-shadow: 0 2px 8px 0 rgba(20, 184, 166, 0.3);
            transition: all 0.1s ease;
        }
        
        .btn-order:disabled {
            background: rgba(255, 255, 255, 0.05);
            color: var(--slate-400);
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        
        .btn-order:disabled::before {
            display: none;
        }
        
        /* Loading State */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--slate-300);
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,.1);
            border-top: 2px solid var(--teal-500);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert.success {
            background: rgba(34,197,94,.12);
            border: 1px solid rgba(34,197,94,.25);
            color: #86efac;
        }
        
        .alert.error {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5;
        }
        
        .alert.warning {
            background: rgba(245,158,11,.12);
            border: 1px solid rgba(245,158,11,.25);
            color: #fcd34d;
        }
        
        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .order-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .info-container {
                position: static;
                order: 1;
            }
            
            .order-container {
                order: 2;
            }
        }
        
        /* Mobile Dropdown Styles */
        @media (max-width: 768px) {
            .order-layout {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
            
            .info-container {
                border-radius: 8px;
                background: rgba(255,255,255,.06);
                border: 1px solid rgba(255,255,255,.1);
                order: 1;
                margin-bottom: 0;
            }
            
            .order-container {
                order: 2;
            }
            
            .info-header {
                background: rgba(255,255,255,.05);
                border-radius: 8px 8px 0 0;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                padding: 14px 16px;
            }
            
            .info-header:hover {
                background: rgba(255,255,255,.08);
            }
            
            .info-header::after {
                content: '';
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%) rotate(0deg);
                width: 12px;
                height: 12px;
                border: 2px solid var(--slate-300);
                border-left: none;
                border-top: none;
                transition: transform 0.3s ease;
            }
            
            .info-container.collapsed .info-header::after {
                transform: translateY(-50%) rotate(-135deg);
            }
            
            .info-container.expanded .info-header::after {
                transform: translateY(-50%) rotate(45deg);
            }
            
            .info-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.4s ease, padding 0.3s ease, opacity 0.3s ease;
                padding: 0;
                margin: 0;
                opacity: 0;
            }
            
            .info-container.expanded .info-content {
                max-height: 2000px;
                padding: 16px;
                overflow-y: visible;
                opacity: 1;
            }
            
            /* Ensure no content bleeds through when collapsed */
            .info-container.collapsed .info-content {
                padding: 0;
                margin: 0;
                max-height: 0;
                overflow: hidden;
            }
            
            /* Add border radius to bottom when collapsed */
            .info-container.collapsed {
                border-radius: 8px;
            }
            
            .info-container.collapsed .info-header {
                border-radius: 8px;
            }
            
            .info-header h3 {
                font-size: 14px;
                color: var(--slate-200);
                font-weight: 500;
            }
            
            .info-header-icon {
                color: var(--slate-300);
                width: 18px;
                height: 18px;
            }
        }
        
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
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .order-form {
                padding: 20px;
            }
            
            .order-header {
                padding: 16px 20px;
            }
            
            .order-actions {
                padding: 20px;
            }
            
            .quantity-info {
                flex-direction: column;
                gap: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 18px;
            }
            
            .order-header h1 {
                font-size: 18px;
            }
            
            .content-area {
                padding: 12px;
            }
            
            .order-form {
                padding: 16px;
            }
            
            .order-header {
                padding: 14px 16px;
            }
            
            .order-actions {
                padding: 18px 16px;
            }
            
            .info-header {
                padding: 14px 16px;
            }
            
            .info-content {
                padding: 16px;
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
        /* Searchable select styles */
        .searchable-select-wrapper {position:relative;font-size:14px;}
        .searchable-select-display {background:rgba(15,41,66,.6);border:1px solid rgba(255,255,255,.14);color:var(--slate-100);padding:12px 14px;border-radius:var(--radius-sm);cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;transition:border-color .2s, background .2s;}
        .searchable-select-display:hover {border-color:var(--teal-500);}
        .searchable-select-placeholder {color:var(--slate-400);}
        .searchable-select-arrow {width:14px;height:14px;flex-shrink:0;transition:transform .25s;opacity:.85;}
        .searchable-select-wrapper.open .searchable-select-arrow {transform:rotate(180deg);}
        .searchable-select-panel {position:absolute;top:100%;left:0;right:0;z-index:500;background:rgba(15,25,40,.95);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.12);border-radius:10px;margin-top:6px;box-shadow:0 10px 30px -8px rgba(0,0,0,.6);display:none;flex-direction:column;max-height:320px;}
        .searchable-select-wrapper.open .searchable-select-panel {display:flex;}
        .searchable-select-search {padding:10px 12px;border:0;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:8px;}
        .searchable-select-search input {width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);padding:8px 10px;border-radius:6px;color:var(--slate-100);font-family:inherit;font-size:13px;}
        .searchable-select-search input:focus {outline:none;border-color:var(--teal-500);box-shadow:0 0 0 3px rgba(20,184,166,.15);}
        .searchable-select-options {list-style:none;margin:0;padding:4px 0;overflow:auto;scrollbar-width:thin;}
        .searchable-select-options li {padding:10px 14px;font-size:13px;cursor:pointer;color:var(--slate-200);display:flex;align-items:center;gap:6px;transition:background .15s,color .15s;}
        .searchable-select-options li:hover {background:rgba(255,255,255,.06);color:var(--slate-100);}
        .searchable-select-options li.active {background:linear-gradient(135deg,var(--teal-600),var(--teal-500));color:#fff;}
        .searchable-select-nores {padding:14px;font-size:12px;color:var(--slate-400);text-align:center;}
        .searchable-hidden-select {display:none !important;}
        @media (max-width: 480px){
            .searchable-select-panel {max-height:260px;}
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
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
                
                <a href="order.php" class="menu-item active">
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
                
                <a href="deposit.php" class="menu-item">
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
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburgerBtn">
                        <div class="hamburger" id="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                    <h1 class="page-title">Buat Pesanan</h1>
                </div>
                
                <div class="header-right">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        </button>
                        
                        <div class="profile-menu" id="profileMenu">
                            <div class="profile-menu-header">
                                <div class="profile-info">
                                    <div class="profile-avatar">
                                        <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="profile-details">
                                        <h4><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Pengguna'); ?></h4>
                                        <p><?= htmlspecialchars($user['email'] ?? 'user@example.com'); ?></p>
                                    </div>
                                </div>
                                <div class="balance-info">
                                    <span class="balance-icon" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(20,184,166,.18);border:1px solid rgba(20,184,166,.35);border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.5px;color:var(--teal-300);">RP</span>
                                    &nbsp;Saldo: Rp <?= number_format((int)($user['balance'] ?? 0), 0, ',', '.'); ?>
                                </div>
                            </div>
                            
                            <div class="profile-menu-items">
                                <a href="dashboard.php" class="profile-menu-item">
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
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="order-layout">
                    <!-- Information Box Column (Mobile: Above form) -->
                    <div class="info-container collapsed" id="infoContainer">
                        <div class="info-header" id="infoHeader">
                            <div class="info-header-icon">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4"/>
                                    <path d="M12 8h.01"/>
                                </svg>
                            </div>
                            <h3>Informasi</h3>
                        </div>
                        
                        <div class="info-content">
                            <!-- Instagram Information -->
                            <div class="info-section">
                                <div class="info-section-title">Informasi Penting Instagram</div>
                                <div class="info-text">
                                    Jika Anda tidak melihat pengikut baru, kemungkinan besar karena mereka perlu disetujui secara manual. Untuk mengatasinya, ikuti langkah-langkah berikut untuk akun yang mau diisi followernya:
                                </div>
                                <ol class="info-steps">
                                    <li>Buka Pengaturan dan Privasi.</li>
                                    <li>Pilih Ikuti dan Undang Teman.</li>
                                    <li>Nonaktifkan opsi Tandai untuk Ditinjau.</li>
                                    <li>Jika baru Nonaktifkan opsi Tandai untuk Ditinjau, kamu bisa test pesan sedikit dulu.</li>
                                </ol>
                                <div class="info-warning">
                                    <div class="info-text">
                                        Ini akan memungkinkan pengikut baru diterima secara otomatis tanpa perlu persetujuan manual. Tolong lakukan agar tidak terjadi sukses tetapi followers tidak masuk padahal sebenarnya masuk tapi hanya masuk ke spam.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Steps -->
                            <div class="info-section">
                                <div class="info-section-title">Langkah-langkah Membuat Pesanan Baru</div>
                                <ol class="info-steps">
                                    <li>Pilih salah satu Kategori.</li>
                                    <li>Pilih salah satu Layanan yang ingin dipesan.</li>
                                    <li>Masukkan Target pesanan sesuai ketentuan yang diberikan layanan tersebut.</li>
                                    <li>Masukkan Jumlah Pesanan yang diinginkan.</li>
                                    <li>Klik Submit untuk membuat pesanan baru.</li>
                                </ol>
                            </div>
                            
                            <!-- Order Rules -->
                            <div class="info-section">
                                <div class="info-section-title">Ketentuan Membuat Pesanan Baru</div>
                                <div class="info-text">
                                    Silahkan membuat pesanan sesuai langkah-langkah diatas.
                                </div>
                                <div class="info-text">
                                    Jika ingin membuat pesanan dengan Target yang sama dengan pesanan yang sudah pernah dipesan sebelumnya, mohon menunggu sampai pesanan sebelumnya selesai diproses. Jika terjadi kesalahan / mendapatkan pesan gagal yang kurang jelas, silahkan hubungi Admin untuk informasi lebih lanjut.
                                </div>
                                <div class="info-text">
                                    <strong>Perhatian:</strong>
                                </div>
                                <ul style="margin: 8px 0; padding-left: 20px; color: var(--slate-200); font-size: 13px; line-height: 1.5;">
                                    <li>Jangan memasukkan orderan yang sama jika orderan sebelumnya belum selesai.</li>
                                    <li>Jangan memasukkan orderan yang sama di panel lain jika orderan di MedanPedia belum selesai.</li>
                                    <li>Jangan mengganti username atau menghapus link target saat sudah order.</li>
                                    <li>Orderan yang sudah masuk tidak dapat di cancel / refund manual, seluruh proses orderan dikerjakan secara otomatis oleh server.</li>
                                    <li>Jika Anda memasukkan orderan di MedanPedia berarti Anda sudah mengerti aturan MedanPedia dan jangan lupa baca menu F.A.Q serta Terms.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Form Column -->
                    <div class="order-container">
                        <div class="order-header">
                            <div class="order-header-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                </svg>
                            </div>
                            <h1>Buat Pesanan Baru</h1>
                        </div>
                        
                        <form class="order-form" id="orderForm">
                            <!-- Alert Container -->
                            <div id="alertContainer"></div>
                            
                            <!-- Category row -->
                            <div class="form-row full">
                                <div class="form-group">
                                    <label class="form-label" for="category">Kategori</label>
                                    <select class="form-select searchable-hidden-select" id="category" name="category">
                                        <option value="">Pilih Kategori...</option>
                                    </select>
                                    <div id="categorySearchable"></div>
                                </div>
                            </div>

                            <!-- Service row below category -->
                            <div class="form-row full">
                                <div class="form-group">
                                    <label class="form-label" for="service">Layanan</label>
                                    <select class="form-select searchable-hidden-select" id="service" name="service">
                                        <option value="">Pilih kategori terlebih dahulu...</option>
                                    </select>
                                    <div id="serviceSearchable"></div>
                                </div>
                            </div>
                            
                            <div class="form-row full">
                                <div class="form-group">
                                    <label class="form-label" for="description">Deskripsi Layanan</label>
                                    <div class="form-textarea" id="description" aria-readonly="true">Deskripsi akan tampil di sini...</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="target">Link / Username</label>
                                    <input type="text" class="form-input" id="target" name="target" placeholder="https://... atau username (@user)" required>
                                    <small id="targetHelp" style="margin-top:6px;font-size:11px;color:var(--slate-400);display:block;">Masukkan URL lengkap (https://...) atau username tanpa spasi. Contoh: https://t.me/channel atau @channelname</small>
                                </div>
                            </div>

                            <!-- Quantity now on its own row below target -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="quantity">Jumlah</label>
                                    <input type="number" class="form-input quantity-input" id="quantity" name="quantity" min="1" max="10000" placeholder="Masukkan jumlah" required>
                                    <div class="quantity-info">
                                        <span class="quantity-badge" id="minBadge">Min: -</span>
                                        <span class="quantity-badge" id="maxBadge">Max: -</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="price">Total Harga</label>
                                    <input type="text" class="form-input price-input" id="price" name="price" readonly placeholder="0">
                                </div>
                            </div>
                            
                            <div class="order-actions">
                                <button type="submit" class="btn-order" id="submitBtn">
                                    Buat Pesanan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let servicesData = {};
        let categoriesData = {};
        let currentService = null;
    let categorySearchableComponent = null;
    let serviceSearchableComponent = null;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebar();
            initializeProfile();
            loadCategories();
            initializeOrderForm();
            initializeInfoDropdown();
            // Build searchable components after DOM ready
            categorySearchableComponent = createSearchableSelect(document.getElementById('category'), document.getElementById('categorySearchable'), {placeholder:'Pilih Kategori...', searchPlaceholder:'Cari kategori...'});
            serviceSearchableComponent = createSearchableSelect(document.getElementById('service'), document.getElementById('serviceSearchable'), {placeholder:'Pilih Layanan...', searchPlaceholder:'Cari layanan...'});
            
            // Check for service parameters from URL (from services.php)
            checkServiceParams();
        });

        // Sidebar functionality
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

        // Profile dropdown functionality
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

        // Initialize info dropdown for mobile
        function initializeInfoDropdown() {
            const infoContainer = document.getElementById('infoContainer');
            const infoHeader = document.getElementById('infoHeader');
            
            if (!infoContainer || !infoHeader) return;
            
            // Check if mobile and set initial state
            function checkMobileState() {
                if (window.innerWidth <= 768) {
                    // Mobile: start collapsed
                    infoContainer.classList.add('collapsed');
                    infoContainer.classList.remove('expanded');
                } else {
                    // Desktop: always expanded, remove mobile classes
                    infoContainer.classList.remove('collapsed', 'expanded');
                }
            }
            
            // Initial check
            checkMobileState();
            
            // Toggle function
            function toggleInfo() {
                if (window.innerWidth <= 768) {
                    if (infoContainer.classList.contains('collapsed')) {
                        infoContainer.classList.remove('collapsed');
                        infoContainer.classList.add('expanded');
                    } else {
                        infoContainer.classList.remove('expanded');
                        infoContainer.classList.add('collapsed');
                    }
                }
            }
            
            // Click handler for mobile
            infoHeader.addEventListener('click', toggleInfo);
            
            // Resize handler
            window.addEventListener('resize', checkMobileState);
        }

        // Load categories from Medanpedia API
        async function loadCategories() {
            try {
                showAlert('info', 'Memuat kategori layanan...', false);
                
                const response = await fetch('api/services.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_services'
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                // console.log removed (Raw API Response)
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Response tidak berupa JSON valid: ' + parseError.message);
                }
                
                // console.log removed (Parsed API Response)
                
                if (data.success) {
                    // Validasi struktur data
                    if (!data.services || typeof data.services !== 'object') {
                        throw new Error('Format data services tidak valid');
                    }
                    
                    if (!data.categories || typeof data.categories !== 'object') {
                        throw new Error('Format data categories tidak valid');
                    }
                    
                    servicesData = data.services;
                    categoriesData = data.categories;
                    populateCategories();
                    hideAlert();
                    
                    // console.log removed (services & categories counts + debug info)
                } else {
                    throw new Error(data.message || 'Gagal memuat kategori layanan');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                console.error('Error details:', error.message);
                showAlert('error', `Terjadi kesalahan: ${error.message}`);
            }
        }

        // Populate categories dropdown
        function populateCategories() {
            const categorySelect = document.getElementById('category');
            categorySelect.innerHTML = '<option value="">Pilih Kategori...</option>';

            if (!categoriesData || typeof categoriesData !== 'object') {
                console.error('Categories data is invalid:', categoriesData);
                showAlert('error', 'Data kategori tidak valid');
                return;
            }

            const categoryKeys = Object.keys(categoriesData);
            
            if (categoryKeys.length === 0) {
                console.warn('No categories found');
                showAlert('warning', 'Tidak ada kategori layanan tersedia');
                return;
            }

            categoryKeys.forEach(categoryKey => {
                const categoryName = categoriesData[categoryKey];
                if (categoryName) {
                    const option = document.createElement('option');
                    option.value = categoryKey;
                    option.textContent = categoryName;
                    categorySelect.appendChild(option);
                    // console.log removed (Added category)
                }
            });
            // Update searchable component options
            if (categorySearchableComponent) {
                const opts = categoryKeys.map(k => ({value:k,label:categoriesData[k]}));
                categorySearchableComponent.setOptions(opts);
            }
            
            // console.log removed (Categories populated)
        }

        // Initialize order form
        function initializeOrderForm() {
            const categorySelect = document.getElementById('category');
            const serviceSelect = document.getElementById('service');
            const descriptionTextarea = document.getElementById('description');
            const quantityInput = document.getElementById('quantity');
            const priceInput = document.getElementById('price');
            const minBadge = document.getElementById('minBadge');
            const maxBadge = document.getElementById('maxBadge');

            // Category change handler
            categorySelect.addEventListener('change', function() {
                const category = this.value;
                serviceSelect.innerHTML = '<option value="">Pilih Layanan...</option>';
                descriptionTextarea.innerHTML = '';
                currentService = null;
                updatePrice();

                // console.log removed (Category changed)

                if (category && servicesData && servicesData[category]) {
                    const services = servicesData[category];
                    
                    // console.log removed (Services for category)
                    
                    if (Array.isArray(services)) {
                        services.forEach((service, index) => {
                            if (service && service.id && service.name) {
                                const option = document.createElement('option');
                                option.value = service.id;
                                
                                // Add price to service name
                                const basePrice = parseFloat(service.price) || 0;
                                const pricePerK = basePrice + 200; // markup applied (200 rupiah flat)
                                const priceText = pricePerK > 0 ? ` - Rp ${pricePerK.toLocaleString('id-ID')}/K` : '';
                                option.textContent = service.name + priceText;
                                
                                serviceSelect.appendChild(option);
                                // console.log removed (Added service)
                            } else {
                                console.warn(`Invalid service at index ${index}:`, service);
                            }
                        });
                        if (serviceSearchableComponent) {
                            const serviceOpts = services.filter(s=>s && s.id && s.name).map(s=>{
                                // Add price to service name for searchable component
                                const basePrice = parseFloat(s.price) || 0;
                                const pricePerK = basePrice + 200; // markup applied (200 rupiah flat)
                                const priceText = pricePerK > 0 ? ` - Rp ${pricePerK.toLocaleString('id-ID')}/K` : '';
                                return {value:s.id, label:s.name + priceText};
                            });
                            serviceSearchableComponent.setOptions(serviceOpts);
                        }
                        // console.log removed (Services populated for category)
                    } else {
                        console.error('Services data for category is not an array:', category, services);
                        showAlert('error', 'Data layanan untuk kategori ini tidak valid');
                    }
                } else if (category) {
                    console.warn('No services found for category:', category);
                    // console.log removed (Available categories)
                    showAlert('warning', 'Tidak ada layanan tersedia untuk kategori ini');
                    if (serviceSearchableComponent) serviceSearchableComponent.clearOptions('Pilih kategori terlebih dahulu...');
                }
            });

            // Service change handler
            serviceSelect.addEventListener('change', function() {
                const serviceId = parseInt(this.value);
                currentService = null;
                
                // console.log removed (Service changed)
                
                if (serviceId) {
                    // Find service in selected category
                    const category = categorySelect.value;
                    // console.log removed (Looking for service in category)
                    
                    if (category && servicesData && servicesData[category] && Array.isArray(servicesData[category])) {
                        currentService = servicesData[category].find(s => s && s.id === serviceId);
                        // console.log removed (Found service)
                    }
                }

                if (currentService) {
                    // Validate service data
                    const description = currentService.description || 'Tidak ada deskripsi tersedia';
                    const min = parseInt(currentService.min) || 1;
                    const max = parseInt(currentService.max) || 10000;
                    const price = parseFloat(currentService.price) || 0;
                    
                    /* console.log removed (Service details): {
                        name: currentService.name,
                        description: description,
                        min: min,
                        max: max,
                        price: price
                    } */
                    
                    // Build structured description block
                    const safeDesc = (description || '').replace(/\n/g,'<br>');
                    const basePrice = parseFloat(currentService.price) || 0; // base per 1000
                    const pricePerK = basePrice + 200; // markup applied (200 rupiah flat)
                    const priceLine = pricePerK ? `Harga: <strong>Rp ${pricePerK.toLocaleString('id-ID')}/K</strong>` : '';
                    const avgTimeText = currentService.average_time ? currentService.average_time : '';
                    let metaParts = [];
                    if (priceLine) metaParts.push(priceLine);
                    const nameHtml = `<div class=\"service-desc-name\">${currentService.name}</div>`;
                    const metaHtml = metaParts.length ? `<div class=\"service-desc-meta\">${metaParts.join(' | ')}</div>` : '';
                    const bodyHtml = `<div class=\"service-desc-label\">Deskripsi:</div><div class=\"service-desc-body\">${safeDesc}</div>`;
                    const avgFooter = avgTimeText ? `<div class=\"service-desc-avgtime\">Rata-rata proses: ${avgTimeText}</div>` : '';
                    descriptionTextarea.innerHTML = nameHtml + metaHtml + '<hr class="service-desc-sep">' + bodyHtml + avgFooter;
                    quantityInput.min = min;
                    quantityInput.max = max;
                    // Biarkan user mengisi manual, tidak auto set ke minimal
                    quantityInput.value = '';
                    
                    // Update quantity badges
                    minBadge.textContent = `Min: ${min.toLocaleString('id-ID')}`;
                    maxBadge.textContent = `Max: ${max.toLocaleString('id-ID')}`;
                    
                    // console.log removed (Service selected)
                } else {
                    descriptionTextarea.innerHTML = '';
                    quantityInput.min = 1;
                    quantityInput.max = 10000;
                    quantityInput.value = '';
                    minBadge.textContent = 'Min: -';
                    maxBadge.textContent = 'Max: -';
                    if (serviceSearchableComponent) serviceSearchableComponent.highlightSelection(null);
                    
                    if (serviceId) {
                        console.error('Service not found for ID:', serviceId);
                        // console.log removed (Available services in current category)
                        showAlert('error', 'Layanan tidak ditemukan');
                    }
                }
                
                updatePrice();
            });

            // Quantity change handler
            quantityInput.addEventListener('input', updatePrice);

            // (Removed plus/minus buttons; user inputs manually)

            // Form submission
            document.getElementById('orderForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitOrder();
            });
        }

        // Create a reusable searchable select component
        function createSearchableSelect(originalSelect, mountEl, config={}) {
            const state = {options:[], filtered:[], selectedValue: originalSelect.value || '', open:false};
            const placeholder = config.placeholder || 'Pilih...';
            const searchPlaceholder = config.searchPlaceholder || 'Cari...';
            mountEl.innerHTML = '';
            const wrapper = document.createElement('div');
            wrapper.className = 'searchable-select-wrapper';
            const display = document.createElement('div');
            display.className = 'searchable-select-display';
            const displayText = document.createElement('span');
            displayText.className = 'searchable-select-text';
            const arrow = document.createElement('span');
            arrow.className = 'searchable-select-arrow';
            arrow.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
            display.appendChild(displayText);display.appendChild(arrow);
            const panel = document.createElement('div');
            panel.className = 'searchable-select-panel';
            const searchBox = document.createElement('div');
            searchBox.className = 'searchable-select-search';
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = searchPlaceholder;
            searchBox.appendChild(searchInput);
            const list = document.createElement('ul');
            list.className = 'searchable-select-options';
            panel.appendChild(searchBox);panel.appendChild(list);
            wrapper.appendChild(display);wrapper.appendChild(panel);
            mountEl.appendChild(wrapper);

            function renderList() {
                list.innerHTML='';
                if (!state.filtered.length) {
                    const empty = document.createElement('div');
                    empty.className='searchable-select-nores';
                    empty.textContent='Tidak ada hasil';
                    list.appendChild(empty);
                    return;
                }
                state.filtered.forEach(opt => {
                    const li = document.createElement('li');
                    li.textContent = opt.label;
                    li.dataset.value = opt.value;
                    if (opt.value === state.selectedValue) li.classList.add('active');
                    li.addEventListener('click', () => selectValue(opt.value));
                    list.appendChild(li);
                });
            }
            function updateDisplay() {
                const selected = state.options.find(o=>o.value===state.selectedValue);
                displayText.textContent = selected ? selected.label : placeholder;
                if (!selected) displayText.classList.add('searchable-select-placeholder');
                else displayText.classList.remove('searchable-select-placeholder');
            }
            function open() {state.open=true;wrapper.classList.add('open');panel.style.display='flex';searchInput.focus();filter(searchInput.value);} 
            function close() {state.open=false;wrapper.classList.remove('open');panel.style.display='none';searchInput.value='';filter('');}
            function toggle() {state.open?close():open();}
            function filter(term) {
                const t = term.toLowerCase();
                state.filtered = !t? [...state.options] : state.options.filter(o=>o.label.toLowerCase().includes(t));
                renderList();
            }
            function selectValue(val) {
                state.selectedValue = val || '';
                originalSelect.value = state.selectedValue;
                originalSelect.dispatchEvent(new Event('change'));
                updateDisplay();
                close();
            }
            display.addEventListener('click', e=>{e.stopPropagation();toggle();});
            searchInput.addEventListener('input', e=>filter(e.target.value));
            document.addEventListener('click', e=>{ if(!wrapper.contains(e.target)) close(); });
            // Keyboard nav
            searchInput.addEventListener('keydown', e=>{
                if(e.key==='Escape'){close();}
            });
            updateDisplay();
            filter('');
            return {
                setOptions(arr){
                    state.options = Array.isArray(arr)?arr:[];
                    // Preserve selection if still present
                    if (!state.options.find(o=>o.value===state.selectedValue)) state.selectedValue='';
                    updateDisplay();
                    filter(searchInput.value);
                },
                clearOptions(msg){
                    state.options=[];state.selectedValue='';updateDisplay();list.innerHTML = `<div class="searchable-select-nores">${msg||'Tidak ada data'}</div>`;originalSelect.value='';
                },
                highlightSelection(val){state.selectedValue=val||'';updateDisplay();filter(searchInput.value);},
                selectValue(val){
                    selectValue(val);
                }
            };
        }

        // Update price calculation
        function updatePrice() {
            const priceInput = document.getElementById('price');
            const quantityInput = document.getElementById('quantity');
            
            if (currentService && currentService.price !== undefined) {
                const rawVal = quantityInput.value.trim();
                const quantity = rawVal === '' ? 0 : (parseInt(rawVal, 10) || 0);
                const basePrice = parseFloat(currentService.price) || 0;
                
                // Apply flat 200 rupiah markup per 1000 units
                const pricePerUnit = basePrice + 200;
                const totalPrice = Math.round((pricePerUnit / 1000) * quantity);
                
                priceInput.value = totalPrice.toLocaleString('id-ID');
                
                /* console.log removed (Price updated): {
                    serviceName: currentService.name,
                    quantity: quantity,
                    basePrice: basePrice,
                    pricePerUnit: pricePerUnit,
                    totalPrice: totalPrice
                } */
            } else {
                priceInput.value = '0';
                if (currentService) {
                    console.warn('Current service missing price:', currentService);
                }
            }
        }

        // Validate & normalize target (accept URL or username)
        function validateAndNormalizeTarget() {
            const input = document.getElementById('target');
            const help = document.getElementById('targetHelp');
            let val = input.value.trim();
            input.classList.remove('invalid');
            help.style.color='var(--slate-400)';

            if (!val) {
                help.textContent = 'Field ini wajib diisi.';
                help.style.color = '#fca5a5';
                input.classList.add('invalid');
                return null;
            }

            // Username: allow starting with @ then remove
            if (val.startsWith('@')) val = val.substring(1);

            const isURL = /^(https?:\/\/)[^\s]+$/i.test(val);
            const isUsername = /^[A-Za-z0-9._-]{3,64}$/.test(val);

            if (!isURL && !isUsername) {
                help.textContent = 'Format tidak valid. Gunakan URL (https://...) atau username (huruf/angka . _ - , 3-64 karakter).';
                help.style.color = '#fca5a5';
                input.classList.add('invalid');
                return null;
            }

            help.textContent = isURL ? 'Terdeteksi URL.' : 'Terdeteksi username.';
            help.style.color = 'var(--teal-300)';
            return val;
        }

        // Submit order
        async function submitOrder() {
            if (!currentService) {
                showAlert('error', 'Silakan pilih layanan terlebih dahulu');
                return;
            }

            // Client-side quantity validation (manual input allowed earlier)
            const quantityInput = document.getElementById('quantity');
            const rawQty = quantityInput.value.trim();
            if (rawQty === '') {
                showAlert('error', 'Silakan isi jumlah pesanan');
                quantityInput.focus();
                return;
            }
            const qty = parseInt(rawQty, 10);
            if (isNaN(qty) || qty <= 0) {
                showAlert('error', 'Jumlah tidak valid');
                quantityInput.focus();
                return;
            }
            const minAllowed = parseInt(quantityInput.min) || 1;
            const maxAllowed = parseInt(quantityInput.max) || 10000;
            if (qty < minAllowed) {
                showAlert('error', `Jumlah di bawah minimum (${minAllowed})`);
                quantityInput.focus();
                return;
            }
            if (qty > maxAllowed) {
                showAlert('error', `Jumlah melebihi maksimum (${maxAllowed})`);
                quantityInput.focus();
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const formData = new FormData(document.getElementById('orderForm'));
            
            const orderData = {
                // API expects keys: service_id, link, quantity
                service_id: currentService.id,
                link: validateAndNormalizeTarget(),
                quantity: qty
            };
            if (!orderData.link) {
                return; // validation already showed message
            }

            try {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses...';
                
                const response = await fetch('api/order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(orderData)
                });

                const data = await response.json();
                
                if (data.success) {
                    // Prefer internal order id, fallback to provider order id
                    const internalId = data.order_id;
                    const providerId = data.provider_order_id;
                    const shownId = internalId || providerId || '???';
                    showAlert('success', `Pesanan berhasil dibuat! ID Pesanan: #${shownId}. Mengalihkan ke riwayat...`);
                    document.getElementById('orderForm').reset();
                    currentService = null;
                    updatePrice();
                    // Dispatch custom event for balance refresh (listener elsewhere will pull)
                    document.dispatchEvent(new CustomEvent('order:created', {detail:{order_id:internalId, provider_order_id:providerId}}));
                    // Redirect to transactions after short delay
                    setTimeout(() => {
                        window.location.href = 'transactions.php';
                    }, 1800);
                } else {
                    showAlert('error', data.message || 'Gagal membuat pesanan');
                }
            } catch (error) {
                console.error('Error submitting order:', error);
                showAlert('error', 'Terjadi kesalahan saat membuat pesanan');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Buat Pesanan';
            }
        }

        // Function to check and handle service parameters from URL
        function checkServiceParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const serviceId = urlParams.get('service_id');
            const serviceName = urlParams.get('service_name');
            
            if (serviceId && serviceName) {
                // Show alert that service is being loaded
                showAlert('info', `Memuat layanan: ${decodeURIComponent(serviceName)}...`);
                
                // Wait for categories to load first, then select the service
                setTimeout(() => {
                    selectServiceFromParams(serviceId, serviceName);
                }, 1000);
            }
        }

        // Function to select service based on parameters
        async function selectServiceFromParams(serviceId, serviceName) {
            try {
                // Find the service in loaded data
                let serviceFound = false;
                
                // Look through all categories for the service
                for (const categoryKey in servicesData) {
                    const categoryServices = servicesData[categoryKey];
                    const service = categoryServices.find(s => s.id == serviceId);
                    
                    if (service) {
                        // Found the service, now select its category first
                        if (categorySearchableComponent) {
                            categorySearchableComponent.selectValue(categoryKey);
                        }
                        
                        // Wait a bit for category change to process
                        setTimeout(() => {
                            // Now select the service
                            if (serviceSearchableComponent) {
                                serviceSearchableComponent.selectValue(serviceId);
                            }
                            
                            hideAlert();
                            showAlert('success', `Layanan "${decodeURIComponent(serviceName)}" berhasil dipilih!`, true);
                            
                            // Clean up URL parameters
                            const url = new URL(window.location);
                            url.searchParams.delete('service_id');
                            url.searchParams.delete('service_name');
                            window.history.replaceState({}, document.title, url.pathname);
                        }, 500);
                        
                        serviceFound = true;
                        break;
                    }
                }
                
                if (!serviceFound) {
                    hideAlert();
                    showAlert('warning', `Layanan dengan ID ${serviceId} tidak ditemukan.`);
                }
                
            } catch (error) {
                console.error('Error selecting service from params:', error);
                hideAlert();
                showAlert('error', 'Gagal memuat layanan yang dipilih.');
            }
        }

        // Alert functions
        function showAlert(type, message, autoHide = true) {
            const alertContainer = document.getElementById('alertContainer');
            
            // Remove existing alerts
            alertContainer.innerHTML = '';
            
            if (!message) return;
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${type}`;
            
            if (type === 'info') {
                alertDiv.innerHTML = `
                    <div class="spinner"></div>
                    ${message}
                `;
            } else {
                alertDiv.textContent = message;
            }
            
            alertContainer.appendChild(alertDiv);
            
            if (autoHide && type !== 'info') {
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }

        function hideAlert() {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = '';
        }
    </script>
</body>
</html>
