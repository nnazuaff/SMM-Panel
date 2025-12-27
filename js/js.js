// ===== Navbar (toggle + active section highlight + header scrolled state) =====
(function() {
	const header = document.querySelector('.site-header');
	const toggle = document.querySelector('.nav-toggle');
	const drawer = document.getElementById('mobileNav');

	function setHeaderState() {
		if (!header) return;
		if (window.scrollY > 12) header.classList.add('scrolled');
		else header.classList.remove('scrolled');
	}

	if (toggle && drawer) {
		function close() {
			drawer.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			document.body.classList.remove('nav-open');
		}
		function open() {
			drawer.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
			document.body.classList.add('nav-open');
		}
		toggle.addEventListener('click', () => {
			const expanded = toggle.getAttribute('aria-expanded') === 'true';
			expanded ? close() : open();
		});
		drawer.addEventListener('click', e => {
			if (e.target.matches('a[href^="#"]')) close();
		});
		window.addEventListener('resize', () => {
			if (window.innerWidth > 980 && !drawer.hidden) close();
		});
		window.addEventListener('keydown', e => {
			if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') close();
		});
	}

	// Active section highlight
	const links = document.querySelectorAll('a[data-nav]');
	if (!links.length) return;
	const map = Array.from(links).map(a => {
		const id = a.getAttribute('href').slice(1);
		return { a, section: document.getElementById(id) };
	}).filter(o => o.section);

	function onScroll() {
		const y = window.scrollY + (window.innerHeight * 0.25);
		let current = null;
		for (const o of map) {
			const rect = o.section.getBoundingClientRect();
			const top = window.scrollY + rect.top;
			if (top <= y) current = o; else break;
		}
		links.forEach(l => l.classList.remove('active'));
		if (current) current.a.classList.add('active');
		setHeaderState();
	}

	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', onScroll);
	setTimeout(() => { onScroll(); }, 120);
})();

// ===== Scroll Reveal =====
(function(){
	const revealEls = document.querySelectorAll('.reveal');
	if(!('IntersectionObserver' in window)) {
		revealEls.forEach(el=>el.classList.add('show')); return;
	}
	const io = new IntersectionObserver(entries=>{
		entries.forEach(en=>{
			if(en.isIntersecting){
				en.target.classList.add('show');
				io.unobserve(en.target);
			}
		});
	},{threshold:0.12, rootMargin:'0px 0px -40px 0px'});
	revealEls.forEach(el=>io.observe(el));
	// Fallback: if after 3s still not shown (maybe error), show them to avoid blank content
	setTimeout(()=>{
		revealEls.forEach(el=>{ if(!el.classList.contains('show')) el.classList.add('show'); });
	}, 3000);
})();

// ===== Realtime Balance Updater =====
(function(){
	const BALANCE_SELECTOR_LIST = [
		'.balance-info', // container yang berisi teks "Saldo:" (akan kita parse & update bagian nominal)
		'.stat-card-value' // kemungkinan card ringkasan saldo
	];

	function formatRupiah(num){
		return new Intl.NumberFormat('id-ID').format(Math.floor(num));
	}

	function updateBalanceDom(raw){
		const formatted = 'Rp ' + formatRupiah(raw);
		// Update elemen yang secara eksplisit punya data-balance-target
		document.querySelectorAll('[data-balance]').forEach(el=>{
			el.textContent = formatted;
		});

		// Heuristik fallback: ganti bagian setelah 'Saldo:' dalam selector yang kita daftar
		BALANCE_SELECTOR_LIST.forEach(sel=>{
			document.querySelectorAll(sel).forEach(el=>{
				const txt = el.textContent;
				if(/Saldo:/i.test(txt)){
					el.innerHTML = el.innerHTML.replace(/Saldo:\s*Rp[^<]*/i,'Saldo: ' + formatted);
				}
			});
		});
	}

	let lastValue = null;
	let inFlight = false;
	async function fetchBalance(){
		if(inFlight) return; // hindari overlap
		inFlight = true;
		try {
			const apiRoot = (typeof window.API_ROOT !== 'undefined') ? window.API_ROOT : 'api';
		const res = await fetch(apiRoot + '/balance.php', {cache:'no-store'});
			if(!res.ok) throw new Error('HTTP '+res.status);
			const data = await res.json();
			if(data && data.success){
				if(lastValue === null || lastValue !== data.balance){
					updateBalanceDom(data.balance);
					lastValue = data.balance;
				}
			}
		} catch(e){
			// Silent; bisa tambahkan console.debug jika perlu
		} finally { inFlight = false; }
	}

	// Polling setiap 15 detik (cukup jarang agar ringan)
	setInterval(fetchBalance, 15000);

	// Fetch awal setelah page load stabil
	window.addEventListener('load', ()=> setTimeout(fetchBalance, 800));

	// Listener custom event: saat order sukses dari form JS lain bisa dispatch event ini
	window.addEventListener('order:created', ()=> fetchBalance());
})();