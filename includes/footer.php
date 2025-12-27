<?php
// Pastikan variabel prefix tersedia (sama logika seperti di header jika footer dipanggil terpisah)
if (!isset($basePrefix)) {
    $scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
    $inAuth = (strpos($scriptDir, '/auth') !== false);
    $basePrefix = $inAuth ? '../' : '';
}
?>
<footer class="site-footer" id="footer">
    <div class="container footer-grid">
        <div>
            <div class="brand small">
                <img src="<?= $basePrefix ?>storage/assets/img/logo/logo_trans.png" alt="Logo AcisPedia kecil" style="height:26px;width:auto;" />
                <span>AcisPedia - SMM Panel</span>
            </div>
            <p class="muted">Panel SMM modern untuk kebutuhan promosi media sosial Anda.</p>
        </div>
        <nav aria-label="Menu footer">
            <h4>Menu</h4>
            <ul>
                <li><a href="<?= $basePrefix ?>index.php">Utama</a></li>
                <li><a href="<?= $basePrefix ?>services.php">Layanan</a></li>
                <li><a href="<?= $basePrefix ?>contact.php">Kontak</a></li>
            </ul>
        </nav>
        <nav aria-label="Informasi">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#tos">Ketentuan Layanan</a></li>
                <li><a href="#privacy">Kebijakan Privasi</a></li>
            </ul>
        </nav>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>© 2025 ACISPAY. All rights reserved.</p>
        </div>
    </div>
</footer>
<script>/* App base prefix for client-side API calls */
    window.APP_BASE_PREFIX = <?= json_encode($basePrefix) ?>;
    window.API_ROOT = (window.APP_BASE_PREFIX || '') + 'api';
</script>
<script src="<?= $basePrefix ?>js/js.js?v=20250816"></script>
<?php if(isset($activePage) && $activePage==='services'): ?>
<script>
(function(){
    const API_URL = '<?= $basePrefix ?>api/services.php';
    const tableBody = document.querySelector('#servicesTable tbody');
    const statusEl = document.getElementById('servicesStatus');
    const selCat = document.getElementById('filterCategory');
    const selSort = document.getElementById('sortSelect');
    const inpSearch = document.getElementById('searchInput');
    // btnRefresh dihapus karena tombol sudah tidak ada
    // Pagination container
    let pagerEl = document.getElementById('servicesPager');
    if(!pagerEl){
        pagerEl = document.createElement('div');
        pagerEl.id = 'servicesPager';
        pagerEl.style.margin = '16px 0 6px';
        pagerEl.style.display = 'flex';
        pagerEl.style.flexWrap = 'wrap';
        pagerEl.style.alignItems = 'center';
        pagerEl.style.gap = '10px';
        const container = document.getElementById('dynamicServices')?.querySelector('.container');
        if(container) container.appendChild(pagerEl);
        // Inject pager styles once
        if(!document.getElementById('pagerStyles')){
            const st = document.createElement('style');
            st.id = 'pagerStyles';
            st.textContent = `#servicesPager .pager-info{font-size:12px;color:var(--muted);margin-right:4px;}
            #servicesPager .pager-group{display:flex;flex-wrap:wrap;gap:6px;}
            #servicesPager button.pager-btn{cursor:pointer;min-width:34px;padding:6px 11px;font-size:12px;line-height:1;font-weight:500;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:6px;transition:background .18s,border-color .18s,color .18s,transform .18s;}
            #servicesPager button.pager-btn:hover:not(:disabled){background:var(--surface-alt);}
            #servicesPager button.pager-btn:focus-visible{outline:2px solid var(--accent);outline-offset:1px;}
            #servicesPager button.pager-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);box-shadow:0 2px 4px -1px rgba(0,0,0,.25);}
            #servicesPager button.pager-btn:disabled{opacity:.55;cursor:default;}
            #servicesPager span.gap{padding:6px 4px;font-size:12px;color:var(--muted);} 
            `;
            document.head.appendChild(st);
        }
    }

    if(!tableBody) return; // not on page

    let currentData = [];
    let categoriesLoaded = false;
    let timer;
    let currentPage = 1;
    let totalPages = 0;
    const PER_PAGE = 25; // sinkron dengan backend get_services default

    function setStatus(msg){ if(statusEl) statusEl.textContent = msg; }

    function fetchData(opts={}){
        setStatus('Memuat layanan...');
        const params = new URLSearchParams();
        if(opts.q) params.set('q', opts.q);
        if(opts.category) params.set('category', opts.category);
        if(opts.sort) params.set('sort', opts.sort);
        if(opts.refresh) params.set('refresh','1');
        if(opts.page) params.set('page', opts.page);
        params.set('per_page', PER_PAGE);
        fetch(API_URL + (params.toString()?('?'+params.toString()):''))
            .then(r=>r.json())
            .then(json=>{
                // Unified success flag handling (accept status or success)
                const ok = (json.status === true) || (json.success === true);
                if(!ok){
                    setStatus('Gagal memuat: '+ (json.msg || json.message || 'Tidak diketahui'));
                    return;
                }

                // Support two response shapes:
                // 1) Legacy/default: services = Array<service> with price_formatted etc.
                // 2) New grouped: services = { catKey: [ {id,name,price,min,max,category,description} ] }
                let servicesRaw = json.services;
                let flattened = [];
                if(Array.isArray(servicesRaw)){
                    flattened = servicesRaw;
                } else if (servicesRaw && typeof servicesRaw === 'object') {
                    Object.values(servicesRaw).forEach(list => {
                        if(Array.isArray(list)) list.forEach(s => flattened.push(s));
                    });
                }

                // Normalize each service to guarantee fields used in UI
                // If price_formatted missing, apply 3x markup like backend default branch
                flattened = flattened.map(s => {
                    const basePrice = typeof s._price_num === 'number' ? s._price_num : (typeof s.price === 'number' ? s.price : parseFloat(s.price||'0'));
                    const priceFormatted = s.price_formatted || ('Rp ' + Number((basePrice + 200) || 0).toLocaleString('id-ID'));
                    return {
                        id: s.id,
                        name: s.name || '-',
                        category: s.category || 'Other',
                        price_formatted: priceFormatted,
                        min: s.min ?? '',
                        max: s.max ?? '',
                        refill: s.refill ?? 0,
                        average_time: s.average_time ?? '',
                    };
                });

                currentData = flattened;
                currentPage = json.page || 1;
                totalPages = json.total_pages || 1;
                renderRows();
                renderPager();
                if(!categoriesLoaded){
                    populateCategories(json.categories);
                    categoriesLoaded = true;
                }
                // Backend (action=get_services) menyediakan valid_services & shown_services
                const total = json.valid_services || json.total || flattened.length;
                const count = json.shown_services || json.count || flattened.length;
                setStatus('Menampilkan '+ count +' dari '+ total +' layanan (Halaman '+currentPage+' / '+ (totalPages||1) +')');
            }).catch(e=>{
                setStatus('Error: '+ e.message);
            });
    }

    function populateCategories(cats){
        if(!cats) return;
        // Accept object (key=>name) or array
        if(!Array.isArray(cats)){
            cats = Object.values(cats);
        }
        try { cats.sort((a,b)=>a.localeCompare(b)); } catch(e){}
        const added = new Set();
        cats.forEach(c=>{
            if(!c || added.has(c)) return; added.add(c);
            const opt = document.createElement('option');
            opt.value = c; opt.textContent = c; selCat.appendChild(opt);
        });
    }

    function renderRows(){
        const frag = document.createDocumentFragment();
        currentData.forEach(s=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${s.id}</td>
            <td><div class="service-name">${escapeHtml(s.name)}</div></td>
            <td><span class="service-price">${s.price_formatted}</span></td>
            <td>${s.min??''}</td>
            <td>${s.max??''}</td>
            <td>${s.average_time?escapeHtml(s.average_time):''}</td>
            <td>
                <div class="action-buttons">
                    <button class="buy-btn" onclick="buyService(${s.id}, '${escapeHtml(s.name)}')">Beli</button>
                    <button class="detail-btn" onclick="showServiceDetail(${s.id}, '${escapeHtml(s.name)}', '${s.price_formatted}', '${s.min??''}', '${s.max??''}', '${escapeHtml(s.average_time??'')}', '${escapeHtml(s.category)}', '${s.refill==1?'Ya':'Tidak'}')">Detail</button>
                </div>
            </td>`;
            frag.appendChild(tr);
        });
        tableBody.innerHTML='';
        tableBody.appendChild(frag);
        
        // Update total filtered counter
        const totalFilteredEl = document.getElementById('totalFiltered');
        if(totalFilteredEl) {
            totalFilteredEl.textContent = `Total data terfilter: ${currentData.length}`;
        }
    }

    function escapeHtml(str){
        return str?str.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])):'';
    }

    function scheduleReload(){
        clearTimeout(timer);
        timer = setTimeout(()=>{
            fetchData({
                q: inpSearch.value.trim(),
                category: selCat.value,
                sort: selSort.value,
                page: 1 // reset to page 1 jika filter berubah
            });
        }, 320);
    }

    selCat.addEventListener('change', scheduleReload);
    selSort.addEventListener('change', scheduleReload);
    inpSearch.addEventListener('input', scheduleReload);
    // Event listener untuk btnRefresh dihapus karena tombol sudah tidak ada

    function renderPager(){
        if(!pagerEl) return;
        pagerEl.innerHTML = '';
        if(totalPages <= 1){
            return; // tidak perlu pager
        }
        const info = document.createElement('div');
        info.className='pager-info';
        info.textContent = 'Halaman ' + currentPage + ' dari ' + totalPages;
        pagerEl.appendChild(info);
        const group = document.createElement('div');
        group.className = 'pager-group';
        pagerEl.appendChild(group);
        const makeBtn = (label, page, disabled=false, active=false)=>{
            const b = document.createElement('button');
            b.textContent = label;
            b.type = 'button';
            b.className = 'pager-btn' + (active?' active':'');
            b.disabled = disabled || active;
            if(!b.disabled){
                b.addEventListener('click', ()=>{
                    fetchData({
                        q: inpSearch.value.trim(),
                        category: selCat.value,
                        sort: selSort.value,
                        page: page
                    });
                });
            }
            return b;
        };
        // Prev
        group.appendChild(makeBtn('«', currentPage-1, currentPage===1));
        // Show limited window (e.g., 1.., current-2 .. current+2 .. last)
        const windowSize = 2;
        let start = Math.max(1, currentPage - windowSize);
        let end = Math.min(totalPages, currentPage + windowSize);
        if(start > 1){
            group.appendChild(makeBtn('1',1,false, currentPage===1));
            if(start > 2){
                const span = document.createElement('span'); span.textContent = '...'; span.className='gap'; group.appendChild(span);
            }
        }
        for(let p = start; p <= end; p++){
            group.appendChild(makeBtn(String(p), p, false, currentPage===p));
        }
        if(end < totalPages){
            if(end < totalPages -1){
                const span = document.createElement('span'); span.textContent = '...'; span.className='gap'; group.appendChild(span);
            }
            group.appendChild(makeBtn(String(totalPages), totalPages, false, currentPage===totalPages));
        }
        // Next
        group.appendChild(makeBtn('»', currentPage+1, currentPage===totalPages));
    }

    fetchData({page:1});
})();

// Modal Detail Functions
function showServiceDetail(id, name, price, min, max, avgTime, category, refill) {
    const modal = document.getElementById('detailModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = `Detail Layanan #${id}`;
    
    modalBody.innerHTML = `
        <div class="detail-item">
            <div class="detail-label">Layanan</div>
            <div class="detail-value">${name}</div>
        </div>
        
        <div class="detail-item">
            <div class="detail-label">Harga/K</div>
            <div class="detail-value price">${price}</div>
        </div>
        
        <div class="detail-item">
            <div class="detail-label">Min. Pesan</div>
            <div class="detail-value">${min}</div>
        </div>
        
        <div class="detail-item">
            <div class="detail-label">Max. Pesan</div>
            <div class="detail-value">${max}</div>
        </div>
        
        <div class="detail-item">
            <div class="detail-label">Waktu Rata-Rata</div>
            <div class="detail-value">${avgTime || 'Tidak tersedia'}</div>
        </div>
        
        <div class="detail-item">
            <div class="detail-label">Deskripsi</div>
            <div class="detail-value description">
                Kategori: ${category}<br>
                Refill: ${refill}<br>
                Start Time: 0-10 Min<br>
                No Refill / No Refund
            </div>
        </div>
    `;
    
    modal.classList.add('active');
}

// Function to buy service - redirect to order page with selected service
function buyService(serviceId, serviceName) {
    // Check if user is logged in by checking if userNav exists (PHP variable)
    const isLoggedIn = <?php echo json_encode(isset($_SESSION['user']) && $_SESSION['user']); ?>;
    
    if (!isLoggedIn) {
        // Redirect to login page
        window.location.href = 'auth/login.php';
        return;
    }
    
    // Create URL with service parameters
    const orderUrl = new URL('order.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
    orderUrl.searchParams.set('service_id', serviceId);
    orderUrl.searchParams.set('service_name', encodeURIComponent(serviceName));
    
    // Redirect to order page
    window.location.href = orderUrl.toString();
}

// Close modal events
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailModal');
    const closeBtn = document.getElementById('closeModal');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('active');
        });
    }
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>