document.addEventListener('DOMContentLoaded', () => {
  // 1. Navigation scroll state
  const mainNav = document.getElementById('mainNav');
  const handleScroll = () => {
    if (!mainNav) return;
    if (window.scrollY > 80) {
      mainNav.classList.add('bg-brand-deep/90', 'backdrop-blur-md', 'py-4', 'shadow-md');
      mainNav.classList.remove('py-6');
    } else {
      mainNav.classList.remove('bg-brand-deep/90', 'backdrop-blur-md', 'py-4', 'shadow-md');
      mainNav.classList.add('py-6');
    }
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  // 2. Mobile menu toggle
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  const menuIcon = document.getElementById('menuIcon');
  const closeIcon = document.getElementById('closeIcon');
  const firstMobileLink = mobileMenu ? mobileMenu.querySelector('.mobile-nav-link') : null;
  let isMenuOpen = false;

  const setMenu = (open, restoreFocus = false) => {
    if (!mobileMenu || isMenuOpen === open) return;
    isMenuOpen = open;

    mobileMenu.classList.toggle('is-open', open);
    mobileMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
      mobileMenu.removeAttribute('inert');
    } else {
      mobileMenu.setAttribute('inert', '');
    }
    if (mobileBtn) mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (menuIcon) menuIcon.classList.toggle('hidden', open);
    if (closeIcon) closeIcon.classList.toggle('hidden', !open);
    document.body.classList.toggle('menu-open', open);

    if (open) {
      // Let the overlay become visible before moving focus into it.
      window.setTimeout(() => {
        if (isMenuOpen && firstMobileLink) firstMobileLink.focus({ preventScroll: true });
      }, 60);
    } else if (restoreFocus && mobileBtn) {
      mobileBtn.focus();
    }
  };

  if (mobileBtn) mobileBtn.addEventListener('click', () => setMenu(!isMenuOpen));
  document.querySelectorAll('.mobile-nav-link').forEach((link) => {
    link.addEventListener('click', () => setMenu(false));
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isMenuOpen) setMenu(false, true);
  });
  const desktopNav = window.matchMedia('(min-width: 1024px)');
  const handleDesktopNav = (event) => { if (event.matches) setMenu(false); };
  if (typeof desktopNav.addEventListener === 'function') {
    desktopNav.addEventListener('change', handleDesktopNav);
  } else if (typeof desktopNav.addListener === 'function') {
    desktopNav.addListener(handleDesktopNav);
  }

  // 3. Reveal on scroll
  const revealElements = document.querySelectorAll('.reveal-on-scroll');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealElements.forEach((el) => observer.observe(el));
  } else {
    revealElements.forEach((el) => el.classList.add('is-revealed'));
  }

  // 5. Selected Wedding Portfolios carousel (project-carousel.html binding)
  const gallerySwiper = document.querySelector('.swiper-gallery-two');
  if (gallerySwiper && typeof Swiper === 'function') {
    new Swiper(gallerySwiper, {
      loop: true,
      autoplay: { delay: 3000 },
      speed: 1500,
      slidesPerView: 1,
      spaceBetween: 30,
      centeredSlides: false,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        992: { slidesPerView: 2, spaceBetween: 30, centeredSlides: false },
        1200: { slidesPerView: 2, spaceBetween: 85, centeredSlides: true },
      },
    });
  }

  // 4. Hero video autoplay fallback
  const heroVideo = document.getElementById('heroVideo');
  if (heroVideo) {
    const p = heroVideo.play();
    if (p && typeof p.catch === 'function') {
      p.catch(() => { /* poster remains */ });
    }
  }
});
