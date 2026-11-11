<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="images/Icon.png" />
  <title>Santi Blinds — Window Coverings Designed for Light</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Quicksand:wght@300;400;500;600&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>

  <!-- NAV -->
  <header class="nav" id="nav">
    <div class="nav__inner">
      <a href="index.html" class="nav__logo">
        <img src="images/SantiLogoNoBg.png" alt="Santi Blinds logo" class="nav__logo-img" />
        <span class="nav__brand">SANTI <em>BLINDS</em></span>
      </a>
      <nav class="nav__links" id="navLinks">
        <a href="index.html" class="active">Home</a>
        <a href="about.html">About</a>
        <a href="products.html">Products</a>
        <a href="gallery.html">Gallery</a>
        <a href="process.html">Process</a>
        <a href="order.html">Order</a>
        <a href="contact.html">Contact</a>
      </nav>
      <div class="nav__account" id="navAccount"></div>
      <button class="nav__hamburger" id="hamburger" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- HERO -->
  <section class="hero" id="hero">
    <div class="hero__bg">
      <div class="hero__slats-overlay"></div>
    </div>
    <div class="hero__content">
      <p class="hero__eyebrow reveal">Window Coverings · Metro Manila</p>
      <h1 class="hero__title reveal">
        <span>Designed</span><br/>
        <span>for Light.</span><br/>
        <span class="hero__title--accent">Made for Life.</span>
      </h1>
      <p class="hero__sub reveal">Premium blinds crafted with precision — where form meets function in every slat.</p>
      <div class="hero__cta reveal">
        <a href="products.html" class="btn btn--primary">Explore Collection</a>
        <a href="contact.html" class="btn btn--ghost">Get a Free Quote</a>
      </div>
    </div>
    <div class="hero__right-deco" aria-hidden="true">
      <div class="hero__slat-art">
        <div class="slat-art__frame">
          <div class="slat-art__light"></div>
          <div class="slat-art__slats">
            <div class="slat" style="--i:0"></div><div class="slat" style="--i:1"></div>
            <div class="slat" style="--i:2"></div><div class="slat" style="--i:3"></div>
            <div class="slat" style="--i:4"></div><div class="slat" style="--i:5"></div>
            <div class="slat" style="--i:6"></div><div class="slat" style="--i:7"></div>
            <div class="slat" style="--i:8"></div><div class="slat" style="--i:9"></div>
            <div class="slat" style="--i:10"></div><div class="slat" style="--i:11"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="hero__scroll-hint">
      <span>Scroll</span>
      <div class="hero__scroll-line"></div>
    </div>
  </section>

  <!-- MARQUEE -->
  <!-- <div class="marquee">
    <div class="marquee__track">
      <span>MODERN</span><span class="dot">·</span><span>MINIMAL</span><span class="dot">·</span>
      <span>REFINED</span><span class="dot">·</span><span>TIMELESS</span><span class="dot">·</span>
      <span>MODERN</span><span class="dot">·</span><span>MINIMAL</span><span class="dot">·</span>
      <span>REFINED</span><span class="dot">·</span><span>TIMELESS</span><span class="dot">·</span>
      <span>MODERN</span><span class="dot">·</span><span>MINIMAL</span><span class="dot">·</span>
      <span>REFINED</span><span class="dot">·</span><span>TIMELESS</span><span class="dot">·</span>
    </div>
  </div> -->

  <!-- INTRO — Brand statement + stats -->
  <section class="home-intro section">
    <div class="container">
      <div class="home-intro__grid">

        <div class="home-intro__left reveal">
          <span class="section-label">Since 2010</span>
          <h2 class="section-title">Architecture<br/>of Light</h2>
          <div class="home-intro__divider"></div>
          <a href="about.html" class="link-arrow">Our Story <span>→</span></a>
        </div>

        <div class="home-intro__right">
          <p class="home-intro__body reveal">At Santi Blinds, we believe window coverings are more than a functional necessity — they are the architects of atmosphere. Every product we craft is a careful study in how light shapes a room, a mood, a life.</p>
          <p class="home-intro__body reveal" data-delay="60">Founded in Valenzuela City, we bring precision manufacturing, considered design, and enduring materials to every home and space we touch. What starts with a conversation ends with a space transformed.</p>
          <div class="home-intro__stats reveal" data-delay="120">
            <div class="home-intro__stat">
              <span class="stat__num">2+</span>
              <span class="stat__label">Years of Craft</span>
            </div>
            <div class="home-intro__stat">
              <span class="stat__num">35+</span>
              <span class="stat__label">Spaces Transformed</span>
            </div>
            <div class="home-intro__stat">
              <span class="stat__num">50+</span>
              <span class="stat__label">Design Options</span>
            </div>
          </div>
          <div class="home-intro__cta-row reveal" data-delay="180">
            <a href="products.html" class="btn btn--primary">View Collection</a>
            <a href="contact.html" class="btn btn--ghost">Book a Consultation</a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- VIDEO SHOWCASE -->
  <section class="video-showcase">
    <div class="video-showcase__header container">
      <span class="section-label reveal">In Motion</span>
      <h2 class="section-title reveal">Light. Crafted<br/><span class="accent">By Hand.</span></h2>
    </div>
    <div class="video-showcase__stage reveal">
      <div class="video-showcase__frame">
        <!-- Decorative slat strips flanking the frame -->
        <div class="video-showcase__slat-left" aria-hidden="true">
          <div class="vs-slat" style="--vi:0"></div><div class="vs-slat" style="--vi:1"></div>
          <div class="vs-slat" style="--vi:2"></div><div class="vs-slat" style="--vi:3"></div>
          <div class="vs-slat" style="--vi:4"></div><div class="vs-slat" style="--vi:5"></div>
          <div class="vs-slat" style="--vi:6"></div><div class="vs-slat" style="--vi:7"></div>
        </div>

        <!-- Video Player -->
        <div class="video-showcase__player" id="vsPlayer">
          <div class="video-showcase__thumb" id="vsThumb">
            <!-- Actual video -->
            <video
              class="video-showcase__video"
              id="vsVideo"
              src="video/Demoreel.mp4"
              playsinline
              preload="auto"
            ></video>

            <!-- Overlay (fades out when playing) -->
            <div class="video-showcase__overlay" id="vsOverlay">
              <button class="video-showcase__play" id="vsPlayBtn" aria-label="Play showreel">
                <div class="video-showcase__play-ring"></div>
                <svg class="video-showcase__play-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M8 5.5L19 12L8 18.5V5.5Z" fill="currentColor"/>
                </svg>
              </button>
            </div>

            <!-- Pause button (shown while playing, click anywhere on video) -->
            <button class="video-showcase__pause" id="vsPauseBtn" aria-label="Pause" style="display:none">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"/>
                <rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"/>
              </svg>
            </button>

            <!-- Caption bar -->
            <div class="video-showcase__caption">
              <span class="video-showcase__caption-label">Showreel 2026</span>
              <span class="video-showcase__caption-dur" id="vsDur">—</span>
            </div>
          </div>
        </div>

        <div class="video-showcase__slat-right" aria-hidden="true">
          <div class="vs-slat" style="--vi:0"></div><div class="vs-slat" style="--vi:1"></div>
          <div class="vs-slat" style="--vi:2"></div><div class="vs-slat" style="--vi:3"></div>
          <div class="vs-slat" style="--vi:4"></div><div class="vs-slat" style="--vi:5"></div>
          <div class="vs-slat" style="--vi:6"></div><div class="vs-slat" style="--vi:7"></div>
        </div>
      </div>

      <!-- Metadata row beneath the player -->
      <div class="video-showcase__meta container">
        <div class="vs-meta-item reveal" data-delay="0">
          <span class="vs-meta-item__num">01</span>
          <div>
            <h4>Craftsmanship</h4>
            <p>From raw material to refined finish — see our process in full.</p>
          </div>
        </div>
        <div class="vs-meta-divider"></div>
        <div class="vs-meta-item reveal" data-delay="100">
          <span class="vs-meta-item__num">02</span>
          <div>
            <h4>Installations</h4>
            <p>Real homes, real light — our work installed across Metro Manila.</p>
          </div>
        </div>
        <div class="vs-meta-divider"></div>
        <div class="vs-meta-item reveal" data-delay="200">
          <span class="vs-meta-item__num">03</span>
          <div>
            <h4>The Collection</h4>
            <p>Every product, in motion — experience light as design.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PRODUCT HIGHLIGHTS -->
  <section class="section home-products">
    <div class="container">
      <div class="section-header reveal">
        <span class="section-label">Our Collection</span>
        <h2 class="section-title">Six Ways to<br/>Control the Light</h2>
      </div>
      <div class="home-products__grid">
        <article class="product-card reveal" data-delay="0">
          <div class="product-card__img product-card__img--roller">
            <img src="images/Product_1.png" alt="Roller Blinds fabric swatches" class="product-card__photo" />
            <div class="product-card__slat-deco"></div>
            <span class="product-card__tag">Fabric</span>
          </div>
          <div class="product-card__body">
            <h3>Roller Blinds</h3>
            <p>Clean-lined and effortlessly modern. Blackout, light-filtering, and sheer in 30+ colours.</p>
            <a href="products.html" class="link-arrow">View Product <span>→</span></a>
          </div>
        </article>
        <article class="product-card reveal" data-delay="80">
          <div class="product-card__img product-card__img--venetian">
            <img src="images/Product_2.png" alt="Venetian Blinds close-up slats" class="product-card__photo" />
            <div class="product-card__slat-deco"></div>
            <span class="product-card__tag">Hard Slat</span>
          </div>
          <div class="product-card__body">
            <h3>Venetian Blinds</h3>
            <p>Timeless aluminium and wood slats with precise tilt control over light and privacy.</p>
            <a href="products.html" class="link-arrow">View Product <span>→</span></a>
          </div>
        </article>
        <article class="product-card reveal" data-delay="160">
          <div class="product-card__img product-card__img--roman">
            <img src="images/Product_3.png" alt="Roman Blinds fabric swatch collection" class="product-card__photo" />
            <div class="product-card__slat-deco"></div>
            <span class="product-card__tag">Fabric</span>
          </div>
          <div class="product-card__body">
            <h3>Roman Blinds</h3>
            <p>Soft fabric folds that add warmth and texture to living rooms and bedrooms.</p>
            <a href="products.html" class="link-arrow">View Product <span>→</span></a>
          </div>
        </article>
        <article class="product-card reveal" data-delay="240">
          <div class="product-card__img product-card__img--motorized">
            <img src="images/Product_6.png" alt="Motorized Blinds elegance fabric catalogue" class="product-card__photo" />
            <div class="product-card__slat-deco"></div>
            <span class="product-card__tag">Smart</span>
          </div>
          <div class="product-card__body">
            <h3>Motorized Blinds</h3>
            <p>Smart automation meets silent precision. App, remote, and voice control.</p>
            <a href="products.html" class="link-arrow">View Product <span>→</span></a>
          </div>
        </article>
      </div>
      <div class="home-products__footer reveal" data-delay="100">
        <a href="products.html" class="btn btn--ghost">View All Six Products</a>
      </div>
    </div>
  </section>

  <!-- GALLERY STRIP -->
  <section class="home-gallery section">
    <div class="home-gallery__header">
      <div class="reveal">
        <span class="section-label">Portfolio</span>
        <h2 class="section-title">Real Spaces,<br/>Real Transformations</h2>
      </div>
      <a href="gallery.html" class="link-arrow reveal" data-delay="80">Browse Gallery <span>→</span></a>
    </div>
    <div class="gallery__strip">
      <div class="gallery__item gallery__item--tall">
        <img src="images/1.png" alt="Navy Blue Day & Night Roller Blind" class="gallery__photo" />
        <div class="gallery__caption"><h4>Roller Blinds</h4><span>Living Room · Day & Night Navy</span></div>
      </div>
      <div class="gallery__item">
        <img src="images/10.png" alt="Jet Black Blackout Roller Blind" class="gallery__photo" />
        <div class="gallery__caption"><h4>Roller Blinds</h4><span>Bedroom · Blackout Jet Black</span></div>
      </div>
      <div class="gallery__item gallery__item--wide">
        <img src="images/4.png" alt="Ivory Embossed Roman Blind" class="gallery__photo" />
        <div class="gallery__caption"><h4>Roman Blinds</h4><span>Living Room · Embossed Ivory</span></div>
      </div>
      <div class="gallery__item">
        <img src="images/7.png" alt="Soft Lavender Day & Night Roller Blind" class="gallery__photo" />
        <div class="gallery__caption"><h4>Roller Blinds</h4><span>Master Bedroom · Day & Night Lavender</span></div>
      </div>
      <div class="gallery__item">
        <img src="images/9.png" alt="Charcoal Grey Day & Night Roller Blind" class="gallery__photo" />
        <div class="gallery__caption"><h4>Roller Blinds</h4><span>Home Office · Day & Night Charcoal</span></div>
      </div>
    </div>
  </section>

  <!-- PROCESS TEASER -->
  <section class="process section">
    <div class="container">
      <div class="section-header reveal">
        <span class="section-label">How It Works</span>
        <h2 class="section-title">From Consultation<br/>to Installation</h2>
      </div>
      <div class="process__steps">
        <div class="process__step reveal" data-delay="0">
          <div class="process__num">01</div>
          <div class="process__content">
            <h3>Consult</h3>
            <p>Free in-home visit. We listen, assess your space, and bring a full sample kit.</p>
          </div>
        </div>
        <div class="process__connector"></div>
        <div class="process__step reveal" data-delay="100">
          <div class="process__num">02</div>
          <div class="process__content">
            <h3>Measure</h3>
            <p>Millimetre-precise measurements and finalised design selection with a written quote.</p>
          </div>
        </div>
        <div class="process__connector"></div>
        <div class="process__step reveal" data-delay="200">
          <div class="process__num">03</div>
          <div class="process__content">
            <h3>Fabricate</h3>
            <p>Custom-made in our workshop from premium materials. 7–10 working day turnaround.</p>
          </div>
        </div>
        <div class="process__connector"></div>
        <div class="process__step reveal" data-delay="300">
          <div class="process__num">04</div>
          <div class="process__content">
            <h3>Install</h3>
            <p>Expert installation at your convenience. Clean, careful, satisfaction guaranteed.</p>
          </div>
        </div>
      </div>
      <div class="process__link reveal" data-delay="400">
        <a href="process.html" class="link-arrow">See Full Process <span>→</span></a>
      </div>
    </div>
  </section>

  <!-- TESTIMONIAL -->
  <section class="home-testimonial">
    <div class="container">
      <div class="home-testimonial__inner">
        <div class="home-testimonial__label-col reveal">
          <div class="home-testimonial__stars">★ ★ ★ ★ ★</div>
          <div class="home-testimonial__quote">"</div>
        </div>
        <div class="reveal" data-delay="80">
          <blockquote>Santi Blinds completely transformed our living room. The quality is extraordinary, the installation was impeccable, and the team was a pleasure to work with from first call to final fit.</blockquote>
          <cite>— Maria Santos, Makati</cite>
        </div>
      </div>
    </div>
  </section>

  <!-- WHY SANTI -->
  <section class="why-strip section">
    <div class="container">
      <div class="section-header reveal">
        <span class="section-label">Why Choose Santi</span>
        <h2 class="section-title">Built to Last,<br/>Designed to Impress</h2>
      </div>
      <div class="why__grid">
        <div class="why-item reveal" data-delay="0">
          <span class="why-item__icon">✦</span>
          <h4>Custom Made</h4>
          <p>Every blind is made to your exact window measurements — no off-the-shelf compromises.</p>
        </div>
        <div class="why-item reveal" data-delay="100">
          <span class="why-item__icon">✦</span>
          <h4>Premium Materials</h4>
          <p>Durable, quality-tested fabrics, aluminium, and hardware from trusted suppliers.</p>
        </div>
        <div class="why-item reveal" data-delay="200">
          <span class="why-item__icon">✦</span>
          <h4>Expert Installation</h4>
          <p>Trained installers ensure every blind is fitted perfectly, safely, and beautifully.</p>
        </div>
        <div class="why-item reveal" data-delay="300">
          <span class="why-item__icon">✦</span>
          <h4>2-Year Warranty</h4>
          <p>Every product comes backed by our two-year craftsmanship warranty.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA STRIP -->
  <section class="cta-strip">
    <div class="container">
      <div class="cta-strip__inner reveal">
        <div class="cta-strip__text">
          <h2>Ready to transform your space?</h2>
          <p>Request a free in-home consultation — no obligation.</p>
        </div>
        <a href="contact.html" class="btn btn--primary">Get a Free Quote</a>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container">
      <div class="footer__top">
        <div class="footer__brand">
          <span class="footer__logo">
            <img src="images/SantiLogoNoBg.png" alt="Santi Blinds logo" class="footer__logo-img" />
            <span class="footer__logo-text">SANTI <em>BLINDS</em></span>
          </span>
          <p>Window coverings designed for light, made for life.</p>
          <a
            href="https://www.google.com/maps/dir/?api=1&destination=Santi+Cafe+541+Km.16+MacArthur+Highway+Valenzuela+Metro+Manila+1444&travelmode=driving"
            target="_blank"
            rel="noopener"
            class="footer__map-btn"
            aria-label="Get directions to Santi Blinds showroom"
          >
            <svg class="footer__map-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M10 2C7.24 2 5 4.24 5 7c0 4.25 5 11 5 11s5-6.75 5-11c0-2.76-2.24-5-5-5zm0 6.75A1.75 1.75 0 1 1 10 5.25a1.75 1.75 0 0 1 0 3.5z" fill="currentColor"/>
            </svg>
            <span>541 Km.16 MacArthur Hwy, Valenzuela</span>
            <svg class="footer__map-arrow" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M2 10L10 2M10 2H4M10 2v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
        </div>
        <div class="footer__nav">
          <div>
            <h4>Company</h4>
            <a href="about.html">About</a>
            <a href="process.html">Process</a>
            <a href="gallery.html">Gallery</a>
          </div>
          <div>
            <h4>Products</h4>
            <a href="products.html">Roller Blinds</a>
            <a href="products.html">Venetian Blinds</a>
            <a href="products.html">Roman Blinds</a>
            <a href="products.html">Motorized</a>
          </div>
          <div>
            <h4>Contact</h4>
            <a href="contact.html">Get a Quote</a>
            <a href="mailto:info@santiblinds.com">Email Us</a>
            <a href="tel:+6328888000">Call Us</a>
          </div>
        </div>
      </div>
      <div class="footer__bottom">
        <p>© 2026 Santi Blinds. All rights reserved.</p>
        <p>Modern · Minimal · Refined · Timeless</p>
      </div>
    </div>
  </footer>

  <script src="javascript/script.js"></script>
</body>
</html>