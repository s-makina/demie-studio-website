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

  // 6. Services: collapse past the initial limit, reveal on demand
  document.querySelectorAll('[data-services-collapsible]').forEach((list) => {
    if (!list.id) return;
    const toggle = document.querySelector('[data-services-toggle][aria-controls="' + list.id + '"]');
    if (!toggle) return;

    const limit = parseInt(list.getAttribute('data-limit'), 10) || 0;
    const cards = Array.prototype.slice.call(list.querySelectorAll('.service-card'));
    if (!limit || cards.length <= limit) return;

    const extra = cards.slice(limit);
    const wrap = toggle.closest('[data-services-toggle-wrap]');

    const setExpanded = (expanded) => {
      extra.forEach((card) => { card.hidden = !expanded; });
      list.classList.toggle('is-collapsed', !expanded);
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      toggle.textContent = expanded
        ? toggle.getAttribute('data-label-less')
        : toggle.getAttribute('data-label-more');
      if (wrap) wrap.hidden = false;
    };

    setExpanded(false);
    toggle.addEventListener('click', () => {
      setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
    });
  });

  // 7. Services page: filter the Slotfy catalogue by category
  const serviceFilter = document.querySelector('[data-service-filter]');
  if (serviceFilter) {
    const sections = Array.prototype.slice.call(document.querySelectorAll('[data-service-section]'));
    serviceFilter.addEventListener('change', () => {
      const value = serviceFilter.value;
      sections.forEach((section) => {
        const show = !value || section.getAttribute('data-category-id') === value;
        section.hidden = !show;
        if (show) {
          section.querySelectorAll('.reveal-on-scroll').forEach((el) => el.classList.add('is-revealed'));
        }
      });
    });
  }

  // 8. Services page: lead capture (date checker, package dialog, guide form)
  const leadConfig = window.demieLead || {};
  const leadStrings = leadConfig.strings || {};
  const leadAjax = leadConfig.ajaxUrl || '';

  const resetLeadScope = (scope) => {
    if (!scope) return;
    const form = scope.querySelector('[data-demie-lead-form]');
    const success = scope.querySelector('[data-lead-success]');
    const error = scope.querySelector('[data-lead-error]');
    if (form) {
      form.reset();
      form.hidden = false;
    }
    if (success) success.hidden = true;
    if (error) {
      error.hidden = true;
      error.textContent = '';
    }
  };

  const syncLeadPackage = (form, id, name, date) => {
    if (!form) return;
    const setValue = (field, value) => {
      const input = form.querySelector('[name="' + field + '"]');
      if (input) input.value = value || '';
    };
    setValue('package', name);
    setValue('package_id', id);
    setValue('wedding_date', date);

    const summary = form.querySelector('[data-lead-summary]');
    if (!summary) return;
    const parts = [];
    if (date) {
      const parsed = new Date(date + 'T00:00:00');
      if (!isNaN(parsed.getTime())) {
        parts.push(parsed.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }));
      }
    }
    if (name) parts.push(name);
    if (parts.length) {
      summary.textContent = parts.join(' · ');
      summary.classList.remove('hidden');
    } else {
      summary.textContent = '';
      summary.classList.add('hidden');
    }
  };

  // 8a. AJAX form posts (admin-ajax.php returns { success, data })
  const postLeadForm = (form) => {
    const body = new FormData(form);
    body.set('action', form.querySelector('[name="action"]') ? form.querySelector('[name="action"]').value : 'demie_lead');
    return fetch(leadAjax, {
      method: 'POST',
      credentials: 'same-origin',
      body,
    }).then((response) => response.json());
  };

  document.querySelectorAll('[data-demie-lead-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!leadAjax) {
        form.submit();
        return;
      }

      const scope = form.closest('[data-lead-scope]');
      const error = scope ? scope.querySelector('[data-lead-error]') : null;
      const success = scope ? scope.querySelector('[data-lead-success]') : null;
      const successMessage = scope ? scope.querySelector('[data-lead-success-message]') : null;
      const submit = form.querySelector('button[type="submit"]');

      const fail = (message, field) => {
        if (error) {
          error.textContent = message;
          error.hidden = false;
        }
        if (field && typeof field.focus === 'function') field.focus();
      };

      let firstInvalid = null;
      form.querySelectorAll('[required]').forEach((field) => {
        if (firstInvalid) return;
        const empty = field.type === 'checkbox' ? !field.checked : !field.value.trim();
        if (empty) firstInvalid = field;
      });
      if (firstInvalid) {
        fail(
          firstInvalid.type === 'checkbox'
            ? (leadStrings.consentRequired || 'Please tick the consent box so we know we may contact you.')
            : (leadStrings.nameRequired || 'Please add your name and WhatsApp number.'),
          firstInvalid
        );
        return;
      }

      const label = submit ? submit.textContent : '';
      if (submit) {
        submit.disabled = true;
        submit.textContent = leadStrings.sending || 'Sending…';
      }
      if (error) error.hidden = true;

      postLeadForm(form)
        .then((json) => {
          if (json && json.success) {
            if (successMessage) successMessage.textContent = (json.data && json.data.message) || 'Thank you — we will reply on WhatsApp shortly.';
            form.hidden = true;
            if (success) success.hidden = false;
          } else {
            fail((json && json.data && json.data.message) || leadStrings.sendFailed || 'Something went wrong. Please try again.');
          }
        })
        .catch(() => {
          fail(leadStrings.networkError || 'We could not reach the studio. Check your connection and try again.');
        })
        .finally(() => {
          if (submit) {
            submit.disabled = false;
            submit.textContent = label;
          }
        });
    });
  });

  // 8b. Date checker: availability first, contact step second
  document.querySelectorAll('[data-demie-date-form]').forEach((form) => {
    const scope = form.parentElement;
    const result = scope ? scope.querySelector('[data-date-result]') : null;
    const card = result ? result.querySelector('[data-date-card]') : null;
    const message = result ? result.querySelector('[data-date-message]') : null;
    const statusLabel = result ? result.querySelector('[data-date-status-label]') : null;
    const contact = result ? result.querySelector('[data-date-contact]') : null;
    const error = form.querySelector('[data-date-error]');
    const submit = form.querySelector('[data-date-submit]');
    const submitLabel = form.querySelector('[data-date-submit-label]');
    const dateInput = form.querySelector('[name="date"]');
    const serviceSelect = form.querySelector('[name="service_id"]');
    const leadForm = contact ? contact.querySelector('[data-demie-lead-form]') : null;

    const selectedName = () => {
      if (!serviceSelect || !serviceSelect.options.length) return '';
      const option = serviceSelect.options[serviceSelect.selectedIndex];
      return option ? (option.getAttribute('data-name') || option.textContent.trim()) : '';
    };

    const sync = () => {
      syncLeadPackage(leadForm, serviceSelect ? serviceSelect.value : '', selectedName(), dateInput ? dateInput.value : '');
    };

    if (serviceSelect) serviceSelect.addEventListener('change', sync);
    if (dateInput) dateInput.addEventListener('change', sync);
    sync();

    if (!form || !result) return;

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (error) error.hidden = true;

      const date = dateInput ? dateInput.value : '';
      const serviceId = serviceSelect ? serviceSelect.value : '';
      if (!date) {
        if (error) {
          error.textContent = leadStrings.chooseDate || 'Choose your wedding date first.';
          error.hidden = false;
        }
        if (dateInput) dateInput.focus();
        return;
      }
      if (!serviceId) {
        if (error) {
          error.textContent = leadStrings.choosePackage || 'Choose a package so we can check the right calendar.';
          error.hidden = false;
        }
        return;
      }

      const label = submitLabel ? submitLabel.textContent : '';
      if (submit) submit.disabled = true;
      if (submitLabel) submitLabel.textContent = leadStrings.checking || 'Checking the live calendar…';

      const body = new FormData();
      body.set('action', 'demie_availability');
      body.set('nonce', leadConfig.availabilityNonce || '');
      body.set('service_id', serviceId);
      body.set('date', date);

      const showResult = (status, text) => {
        result.hidden = false;
        if (card) card.setAttribute('data-status', status);
        if (message) message.textContent = text;
        if (statusLabel) {
          statusLabel.textContent = status === 'available'
            ? (leadStrings.statusAvailable || 'Date looks open')
            : status === 'unavailable'
              ? (leadStrings.statusUnavailable || 'Date already taken')
              : (leadStrings.statusUnknown || 'We will confirm manually');
        }
        if (contact) {
          resetLeadScope(contact.querySelector('[data-lead-scope]'));
          contact.hidden = false;
        }
        sync();
        if (contact) {
          const nameField = contact.querySelector('[name="name"]');
          if (nameField) nameField.focus({ preventScroll: true });
        }
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      };

      fetch(leadAjax, { method: 'POST', credentials: 'same-origin', body })
        .then((response) => response.json())
        .then((json) => {
          if (json && json.success && json.data) {
            showResult(json.data.status || 'unknown', json.data.message || '');
          } else {
            const text = (json && json.data && json.data.message) || leadStrings.checkFailed || 'We could not check the calendar just now.';
            showResult('unknown', text);
          }
        })
        .catch(() => {
          showResult('unknown', leadStrings.checkFailed || 'We could not check the calendar just now. Leave your WhatsApp and we will confirm your date.');
        })
        .finally(() => {
          if (submit) submit.disabled = false;
          if (submitLabel) submitLabel.textContent = label;
        });
    });
  });

  // 8c. Shared package dialog
  const dialog = document.querySelector('[data-lead-dialog]');
  if (dialog && typeof dialog.showModal === 'function') {
    const dialogForm = dialog.querySelector('[data-demie-lead-form]');
    const dialogSummary = dialog.querySelector('[data-lead-summary]');

    const closeDialog = () => {
      if (typeof dialog.close === 'function') dialog.close();
      document.body.classList.remove('demie-dialog-open');
    };

    document.querySelectorAll('[data-lead-open]').forEach((button) => {
      button.addEventListener('click', () => {
        resetLeadScope(dialog);
        if (dialogForm) {
          const setValue = (field, value) => {
            const input = dialogForm.querySelector('[name="' + field + '"]');
            if (input) input.value = value || '';
          };
          setValue('package', button.getAttribute('data-package') || '');
          setValue('package_id', button.getAttribute('data-package-id') || '');
          setValue('source', button.getAttribute('data-source') || 'services-card');
          setValue('intent', button.getAttribute('data-intent') || 'package');
        }
        if (dialogSummary) {
          const summary = button.getAttribute('data-summary') || button.getAttribute('data-package') || '';
          dialogSummary.textContent = summary;
          dialogSummary.classList.toggle('hidden', !summary);
        }
        dialog.showModal();
        document.body.classList.add('demie-dialog-open');
        const first = dialog.querySelector('input[name="name"]');
        if (first) first.focus({ preventScroll: true });
      });
    });

    dialog.querySelectorAll('[data-lead-close]').forEach((button) => {
      button.addEventListener('click', closeDialog);
    });

    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) closeDialog();
    });

    dialog.addEventListener('close', () => {
      document.body.classList.remove('demie-dialog-open');
    });
  }

  // 8d. Mobile sticky date prompt
  const stickyCta = document.querySelector('[data-sticky-cta]');
  if (stickyCta) {
    const toggleSticky = () => {
      const show = window.scrollY > 640 && !document.body.classList.contains('demie-dialog-open');
      stickyCta.classList.toggle('is-visible', show);
    };
    window.addEventListener('scroll', toggleSticky, { passive: true });
    toggleSticky();
  }
});
