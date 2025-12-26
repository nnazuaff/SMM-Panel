<?php 
$activePage = 'services';
$pageTitle = 'Layanan - AcisPedia SMM Panel';
require 'includes/header.php';
?>

<style>
/* Modern styling sesuai gambar referensi */
.services-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.filters-row {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 160px;
}

.filter-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #374151;
    font-size: 14px;
    transition: all 0.2s ease;
    box-sizing: border-box;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px;
    padding-right: 32px;
}

.search-group {
    flex: 2;
    min-width: 200px;
    position: relative;
}

.search-group input {
    width: 100%;
    padding: 8px 40px 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #374151;
    font-size: 14px;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.search-group::after {
    content: "🔍";
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: #9ca3af;
    pointer-events: none;
}

.filter-group select:focus, .search-group input:focus {
    outline: none;
    border-color: #14b8a6;
    box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.1);
}

.filter-group select:hover, .search-group input:hover {
    border-color: #9ca3af;
}

.status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}

.status-text {
    color: #64748b;
    font-size: 14px;
}

.total-filtered {
    background: #14b8a6;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.services-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.services-table thead {
    background: #f8fafc;
}

.services-table th {
    padding: 16px 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.services-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 14px;
}

.services-table tbody tr:hover {
    background: #f8fafc;
    transition: all 0.2s ease;
}

.service-name {
    font-weight: 600;
    color: #1e293b;
}

.service-price {
    font-weight: 600;
    color: #059669;
}

.service-category {
    display: none; /* Hide category completely */
}

.detail-btn {
    background: #14b8a6;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-left: 6px;
}

.detail-btn:hover {
    background: #0d9488;
    transform: translateY(-1px);
}

.buy-btn {
    background: #f59e0b;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.buy-btn:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
}

.refresh-btn {
    background: transparent;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: none; /* Hidden refresh button */
}

.refresh-btn:hover {
    border-color: #14b8a6;
    color: #14b8a6;
}

.filter-group select:focus, .filter-group input:focus, .search-group input:focus {
    outline: none;
    border-color: #14b8a6;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
}

.filter-group select option {
    color: #334155;
    background: white;
    padding: 8px;
}

/* Responsive Design untuk Mobile */
@media (max-width: 768px) {
    .services-container {
        padding: 16px;
        margin: 10px;
        border-radius: 8px;
    }
    
    .filters-row {
        flex-direction: column;
        gap: 12px;
    }
    
    .filter-group, .search-group {
        min-width: 100%;
        flex: 1;
    }
    
    .status-bar {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .status-bar h3 {
        font-size: 16px !important;
    }
    
    /* Hide table and show card layout for mobile */
    .services-table thead {
        display: none;
    }
    
    .services-table tbody tr {
        display: block;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 16px;
        padding: 18px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        position: relative;
    }
    
    .services-table tbody tr:hover {
        background: #f8fafc;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    
    .services-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }
    
    /* Perfect center for button container */
    .services-table td:last-child {
        border-bottom: none !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding: 20px 0 10px 0 !important;
        margin-top: 16px !important;
        border-top: 1px solid #f1f5f9 !important;
        width: 100% !important;
    }
    
    .services-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
        min-width: 80px;
    }
    
    /* Remove ::before for button cell */
    .services-table td:last-child::before {
        display: none !important;
        content: none !important;
    }
    
    .services-table td:nth-child(1)::before { content: "ID"; }
    .services-table td:nth-child(2)::before { content: "Layanan"; }
    .services-table td:nth-child(3)::before { content: "Harga/K"; }
    .services-table td:nth-child(4)::before { content: "Min."; }
    .services-table td:nth-child(5)::before { content: "Maks."; }
    .services-table td:nth-child(6)::before { content: "Waktu"; }
    
    .service-name {
        text-align: right;
        font-size: 14px;
        line-height: 1.4;
        font-weight: 600;
        color: #1e293b;
        max-width: 220px;
        word-wrap: break-word;
    }
    
    .service-price {
        font-size: 14px;
        font-weight: 700;
    }
    
    .detail-btn {
        padding: 12px 24px;
        font-size: 14px;
        border-radius: 8px;
        font-weight: 600;
        min-width: 120px;
        width: auto;
        max-width: 200px;
        box-shadow: 0 2px 4px rgba(20, 184, 166, 0.2);
        display: inline-block;
        text-align: center;
        margin: 0 auto;
    }
    
    .detail-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(20, 184, 166, 0.3);
    }

    .buy-btn {
        padding: 12px 24px;
        font-size: 14px;
        border-radius: 8px;
        font-weight: 600;
        min-width: 120px;
        width: auto;
        max-width: 200px;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
        display: inline-block;
        text-align: center;
        margin: 0 auto;
    }
    
    .buy-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .services-container {
        padding: 12px;
        margin: 5px;
    }
    
    .filter-group select, .search-group input {
        padding: 10px 12px;
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    .search-group input {
        padding-right: 40px;
    }
    
    .services-table td {
        font-size: 13px;
        padding: 6px 0;
    }
    
    /* Enhanced centering for small screens */
    .services-table td:last-child {
        padding: 18px 0 8px 0 !important;
        margin-top: 12px !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        text-align: center !important;
    }
    
    .services-table td::before {
        font-size: 11px;
        min-width: 70px;
    }
    
    .service-name {
        font-size: 12px;
        max-width: 180px;
    }
    
    .detail-btn {
        padding: 10px 20px;
        font-size: 13px;
        min-width: 100px;
        width: auto;
        max-width: 160px;
        font-weight: 600;
        border-radius: 6px;
        margin: 0;
    }

    .buy-btn {
        padding: 10px 20px;
        font-size: 13px;
        min-width: 100px;
        width: auto;
        max-width: 160px;
        font-weight: 600;
        border-radius: 6px;
        margin: 0;
    }

    .action-buttons {
        display: flex;
        gap: 6px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .modal-content {
        width: 95%;
        padding: 20px;
        margin: 10px;
    }
    
    .modal-title {
        font-size: 16px;
    }
    
    .detail-label {
        font-size: 13px;
    }
    
    .detail-value {
        font-size: 13px;
    }
    
    /* Improve spacing and readability */
    .services-table tbody tr {
        margin-bottom: 14px;
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        padding: 16px;
    }
    
    .service-name {
        max-width: 200px;
        word-wrap: break-word;
        hyphens: auto;
    }
    
    /* Better visual hierarchy */
    .services-table td::before {
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
    }
    
    /* Smooth animations */
    .services-table tbody tr {
        animation: slideInUp 0.4s ease-out;
        transition: all 0.3s ease;
    }
    
    .services-table tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
}

/* Extra small mobile devices */
@media (max-width: 360px) {
    .services-container {
        padding: 10px;
        margin: 2px;
    }
    
    .services-table tbody tr {
        padding: 14px;
        margin-bottom: 12px;
    }
    
    .services-table td {
        padding: 5px 0;
        font-size: 12px;
    }
    
    /* Ultra-precise centering for tiny screens */
    .services-table td:last-child {
        padding: 16px 0 6px 0 !important;
        margin-top: 8px !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        text-align: center !important;
        width: 100% !important;
    }
    
    .services-table td::before {
        font-size: 10px;
        min-width: 60px;
    }
    
    .service-name {
        font-size: 11px;
        max-width: 150px;
        line-height: 1.3;
    }
    
    .detail-btn {
        padding: 8px 16px;
        font-size: 12px;
        min-width: 80px;
        width: auto;
        max-width: 140px;
        border-radius: 5px;
        margin: 0;
    }

    .buy-btn {
        padding: 8px 16px;
        font-size: 12px;
        min-width: 80px;
        width: auto;
        max-width: 140px;
        border-radius: 5px;
        margin: 0;
    }

    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }
}

/* Modal Detail Layanan */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-content {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 12px;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background 0.2s ease;
}

.modal-close:hover {
    background: #f1f5f9;
}

.modal-body {
    color: #374151;
}

.detail-item {
    margin-bottom: 16px;
}

.detail-label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
    font-size: 14px;
}

.detail-value {
    color: #374151;
    font-size: 14px;
    line-height: 1.5;
}

.detail-value.price {
    color: #059669;
    font-weight: 600;
    font-size: 16px;
}

.detail-value.description {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #14b8a6;
}

/* Loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #14b8a6;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    z-index: 10;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Better touch targets for mobile */
@media (max-width: 768px) {
    .hero {
        padding: 80px 0 40px !important;
    }
    
    .detail-btn, .buy-btn, .filter-group select, .search-group input {
        min-height: 44px; /* iOS recommended minimum touch target */
    }
    
    .container {
        padding: 0 16px;
    }
    
    /* Center button container properly */
    .services-table td:last-child {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding: 16px 0 8px 0 !important;
        margin-top: 10px !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    
    /* Better spacing for action buttons on mobile */
    .action-buttons {
        display: flex !important;
        gap: 8px !important;
        justify-content: center !important;
        align-items: center !important;
        flex-wrap: wrap !important;
        width: 100% !important;
    }
    
    .buy-btn, .detail-btn {
        flex: 1 !important;
        min-width: 90px !important;
        max-width: 140px !important;
    }
}
</style>

    <main id="mainContent">
        <!-- Hero Section -->
        <section class="hero" style="padding: 120px 0 60px;">
            <div class="container">
                <div style="text-align: center; max-width: 720px; margin: 0 auto;">
                    <h1 style="font-size: clamp(1.8rem, 4vw, 2.5rem); margin-bottom: 1rem;">Layanan SMM Panel</h1>
                    <p class="subtitle" style="text-align: center; margin: 0 auto; font-size: clamp(0.9rem, 2.5vw, 1.1rem); line-height: 1.6;">
                        Berbagai layanan media sosial terlengkap untuk meningkatkan engagement dan reach akun Anda
                    </p>
                </div>
            </div>
        </section>

        <!-- Dynamic Services Table -->
        <section class="section" id="dynamicServices">
            <div class="container">
                <div class="services-container">
                    <div class="status-bar">
                        <h3 style="margin: 0; color: #1e293b; font-size: 18px;">📋 Layanan</h3>
                        <span class="total-filtered" id="totalFiltered">Total data terfilter: 0</span>
                    </div>
                    
                    <div class="filters-row">
                        <div class="filter-group">
                            <select id="filterCategory">
                                <option value="">Semua Kategori</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select id="sortSelect">
                                <option value="">Sortir</option>
                                <option value="price_asc">Harga Termurah</option>
                                <option value="price_desc">Harga Termahal</option>
                                <option value="name_asc">Nama A-Z</option>
                                <option value="name_desc">Nama Z-A</option>
                            </select>
                        </div>
                        
                        <div class="search-group">
                            <input id="searchInput" placeholder="Cari..." type="text" />
                        </div>
                    </div>
                    
                    <div id="servicesStatus" class="status-text" style="margin-bottom: 16px;">Memuat layanan...</div>
                    
                    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px;">
                        <table class="services-table" id="servicesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Layanan</th>
                                    <th>Harga/K</th>
                                    <th>Min.</th>
                                    <th>Maks.</th>
                                    <th>Waktu Rata-rata</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Detail Layanan -->
        <div id="detailModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Detail Layanan</h3>
                    <button class="modal-close" id="closeModal">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>

<?php require 'includes/footer.php'; ?>
