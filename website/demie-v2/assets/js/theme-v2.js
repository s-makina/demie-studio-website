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
  let isMenuOpen = false;
  const toggleMenu = () => {
    if (!mobileMenu) return;
    isMenuOpen = !isMenuOpen;
    if (isMenuOpen) {
      mobileMenu.classList.remove('opacity-0', 'pointer-events-none');
      mobileMenu.classList.add('opacity-100', 'pointer-events-auto');
      if (menuIcon) menuIcon.classList.add('hidden');
      if (closeIcon) closeIcon.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    } else {
      mobileMenu.classList.add('opacity-0', 'pointer-events-none');
      mobileMenu.classList.remove('opacity-100', 'pointer-events-auto');
      if (menuIcon) menuIcon.classList.remove('hidden');
      if (closeIcon) closeIcon.classList.add('hidden');
      document.body.style.overflow = '';
    }
  };
  if (mobileBtn) mobileBtn.addEventListener('click', toggleMenu);
  document.querySelectorAll('.mobile-nav-link').forEach((link) => {
    link.addEventListener('click', () => { if (isMenuOpen) toggleMenu(); });
  });

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
