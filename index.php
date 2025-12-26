	<?php 
	$activePage = 'home';
	$pageTitle = 'AcisPedia - SMM Panel';
	require 'includes/header.php';
	?>
		
		<!-- Animated particles background -->
		<div class="particles-container">
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
			<div class="particle"></div>
		</div>

		<!-- Link lewati ke konten untuk aksesibilitas -->
		<main id="mainContent" class="reveal">
			<!-- =========================== -->
			<!-- Hero Section                -->
			<!-- =========================== -->
			<section id="hero" class="hero reveal">
				<div class="container hero-grid">
					<div class="hero-text">
						<h1>SMM Panel Indonesia Terbaik</h1>
						<p class="subtitle">
							Kelola pertumbuhan media sosial Anda dengan cepat, otomatis, dan terukur. Harga terjangkau, kualitas terjaga, dukungan siap 24/7.
						</p>

						<!-- Statistik utama -->
						<div class="stats reveal">
							<div class="stat">
								<div class="stat-number" data-target="1200" data-suffix="+">129+</div>
								<div class="stat-label">Pengguna</div>
							</div>
							<div class="stat">
								<div class="stat-number" data-target="100000" data-suffix="+">831</div>
								<div class="stat-label">Pesanan</div>
							</div>
							<div class="stat">
								<div class="stat-number" data-target="929" data-suffix="+">929</div>
								<div class="stat-label">Layanan</div>
							</div>
						</div>
					</div>

					<div class="hero-art parallax-shift" aria-hidden="true">
						<!-- Dekorasi vektor sederhana untuk nuansa modern (tanpa gambar eksternal) -->
						<div class="orb orb-1"></div>
						<div class="orb orb-2"></div>
						<div class="card-mock">
							<div class="card-row"></div>
							<div class="card-row short"></div>
							<div class="card-progress">
								<span style="width:72%"></span>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- =========================== -->
			<!-- Tentang Kami                -->
			<!-- =========================== -->
			<section id="tentang" class="section about reveal">
				<div class="container about-grid">
					<div>
						<h2>Tentang Kami</h2>
						<p>
							AcisPedia adalah platform SMM (Social Media Marketing) yang membantu Anda meningkatkan jangkauan dan kredibilitas akun secara mudah. Dengan sistem otomatis, pesanan diproses cepat, aman, dan transparan.
						</p>
						<p>
							Cocok untuk agensi, pebisnis, maupun kreator. Fokus berkarya—biarkan kami urus pertumbuhan Anda.
						</p>
						<a class="btn btn-primary" href="#kontak">Hubungi Kami</a>
					</div>
					<ul class="about-points">
						<li>Proses otomatis 24/7</li>
						<li>Harga kompetitif</li>
						<li>Dashboard modern & mobile-friendly</li>
						<li>Riwayat pesanan lengkap & transparan</li>
					</ul>
				</div>
			</section>

			<!-- =========================== -->
			<!-- Kenapa Memilih Kami         -->
			<!-- =========================== -->
			<section id="layanan" class="section why reveal">
				<div class="container">
					<h2>Kenapa Memilih Kami</h2>
					<div class="cards">
						<!-- Kartu 1 -->
						<article class="card reveal">
							<div class="icon" aria-hidden="true">
								<!-- Icon bintang -->
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 3l2.94 5.95 6.57.96-4.75 4.63 1.12 6.53L12 18.77 6.12 21.07l1.12-6.53L2.5 9.91l6.56-.96L12 3z" fill="currentColor"/>
								</svg>
							</div>
							<h3>Layanan Berkualitas</h3>
							<p>Ribuan layanan tepercaya, stabil, dan dipantau berkala untuk menjaga performa akun Anda.</p>
						</article>

						<!-- Kartu 2 -->
						<article class="card reveal">
							<div class="icon" aria-hidden="true">
								<!-- Icon headset -->
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 3a9 9 0 00-9 9v5a3 3 0 003 3h2v-8H6a6 6 0 0112 0h-2v8h2a3 3 0 003-3v-5a9 9 0 00-9-9z" fill="currentColor"/>
								</svg>
							</div>
							<h3>Dukungan 24/7</h3>
							<p>Tim support responsif siap membantu kapan pun Anda butuh bantuan.</p>
						</article>

						<!-- Kartu 3 -->
						<article class="card reveal">
							<div class="icon" aria-hidden="true">
								<!-- Icon layout -->
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M4 4h16v6H4V4zm0 8h7v8H4v-8zm9 0h7v8h-7v-8z" fill="currentColor"/>
								</svg>
							</div>
							<h3>Desain Modern</h3>
							<p>UI/UX bersih, enak dilihat, dan optimal untuk perangkat mobile.</p>
						</article>
					</div>
				</div>
			</section>

			<!-- =========================== -->
			<!-- Testimoni Pengguna          -->
			<!-- =========================== -->
			<!-- <section id="testimoni" class="section testimonials reveal">
				<div class="container">
					<h2>Testimoni Pengguna</h2>
					<div class="cards testimonials-grid">
						<figure class="testimonial reveal">
							<blockquote>“Pesanan cepat masuk dan hasilnya stabil. Panel favorit untuk kampanye harian saya.”</blockquote>
							<figcaption>— Rina, Social Media Specialist</figcaption>
						</figure>
						<figure class="testimonial reveal">
							<blockquote>“Harganya ramah kantong, cocok untuk reseller. Dashboard-nya juga gampang dipahami.”</blockquote>
							<figcaption>— Dimas, Agency Owner</figcaption>
						</figure>
						<figure class="testimonial reveal">
							<blockquote>“Support 24/7 benar-benar membantu saat ada kendala teknis tengah malam.”</blockquote>
							<figcaption>— Sari, Content Creator</figcaption>
						</figure>
					</div>
				</div>
			</section> -->

			<!-- =========================== -->
			<!-- FAQ                          -->
			<!-- =========================== -->
			<section id="faq" class="section faq reveal">
				<div class="container">
					<h2>Pertanyaan Umum</h2>
					<!-- Menggunakan <details> agar bisa dibuka/tutup tanpa JS -->
					<details>
						<summary>Apa itu SMM Panel?</summary>
						<div class="answer">
							SMM Panel adalah platform untuk membeli layanan promosi media sosial (followers, likes, views, dsb.) secara otomatis dan terjadwal.
						</div>
					</details>
					<details>
						<summary>Bagaimana cara memulai?</summary>
						<div class="answer">
							Daftarkan akun, isi saldo, pilih layanan yang diinginkan, lalu buat pesanan dengan tautan/username yang valid.
						</div>
					</details>
					<details>
						<summary>Apakah aman digunakan?</summary>
						<div class="answer">
							Kami mengutamakan privasi dan keamanan. Selalu ikuti ketentuan platform media sosial dan hindari penyalahgunaan.
						</div>
					</details>
				</div>
			</section>

			<section id="pembayaran" class="section payments reveal">
				<div class="container">
					<h2>Metode Pembayaran</h2>
					<p class="muted">Dukungan Bank Transfer, dan QRIS.</p>
					<div class="pay-logos">
						Bank transfer
						<!-- <img src="storage/assets/img/icon/bca.png" alt="BCA" />
						<img src="storage/assets/img/icon/bni.png" alt="BNI" />
						<img src="storage/assets/img/icon/bri.png" alt="BRI" />
						<img src="storage/assets/img/icon/mandiri.png" alt="Mandiri" />
						<img src="storage/assets/img/icon/permata.png" alt="Permata" />
						<img src="storage/assets/img/icon/seabank.png" alt="SeaBank" />
						<img src="storage/assets/img/icon/bsi.png" alt="BSI" />
						<img src="storage/assets/img/icon/mybank.jpg" alt="MyBank" />
						<img src="storage/assets/img/icon/neobank.png" alt="Neo Bank" />
						QRIS -->
						<img src="storage/assets/img/icon/qr.png" alt="QRIS" />
					</div>
				</div>
			</section>
		</main>

	<?php require 'includes/footer.php'; ?>