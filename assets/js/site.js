(function () {
  'use strict';

  const storageKey = 'anatod-theme';
  const root = document.documentElement;
  const colourScheme = window.matchMedia('(prefers-color-scheme: dark)');
  let themePreference = null;
  let siteConfig = null;

  function message(key, fallback) {
    return siteConfig && Object.prototype.hasOwnProperty.call(siteConfig.messages, key)
      ? siteConfig.messages[key] : fallback;
  }

  try {
    const savedTheme = window.localStorage.getItem(storageKey);
    if (savedTheme === 'light' || savedTheme === 'dark') {
      themePreference = savedTheme;
    }
  } catch (error) {
    // Theme selection also works when the browser blocks local storage.
  }

  function applyTheme(theme) {
    root.dataset.bsTheme = theme;
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
      const isDark = theme === 'dark';
      toggle.setAttribute('aria-pressed', String(isDark));
      toggle.setAttribute('aria-label', isDark ? message('themeLight', 'Cambiar a modo claro') : message('themeDark', 'Cambiar a modo oscuro'));
    }
  }

  function observeMediaQuery(query, callback) {
    if (typeof query.addEventListener === 'function') {
      query.addEventListener('change', callback);
    } else if (typeof query.addListener === 'function') {
      query.addListener(callback);
    }
  }

  applyTheme(themePreference || (colourScheme.matches ? 'dark' : 'light'));

  observeMediaQuery(colourScheme, function (event) {
    if (!themePreference) {
      applyTheme(event.matches ? 'dark' : 'light');
    }
  });

  function initialise() {
    const themeToggle = document.getElementById('theme-toggle');
    applyTheme(root.dataset.bsTheme);
    if (themeToggle) {
      themeToggle.addEventListener('click', function () {
        themePreference = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
        applyTheme(themePreference);
        try {
          window.localStorage.setItem(storageKey, themePreference);
        } catch (error) {
          // Keep the chosen theme for this page even without persistent storage.
        }
      });
    }

    let refreshMenuLabels = function () {};
    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.getElementById('main-nav');
    if (menuToggle && mainNav) {
      function setMenuOpen(isOpen) {
        mainNav.classList.toggle('is-open', isOpen);
        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? message('menuClose', 'Cerrar menú') : message('menuOpen', 'Abrir menú'));
      }

      refreshMenuLabels = function () { setMenuOpen(menuToggle.getAttribute('aria-expanded') === 'true'); };
      setMenuOpen(false);
      menuToggle.addEventListener('click', function () {
        setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
      });

      mainNav.addEventListener('click', function (event) {
        if (event.target instanceof Element && event.target.closest('a')) {
          setMenuOpen(false);
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
          setMenuOpen(false);
          menuToggle.focus();
        }
      });

      const desktopViewport = window.matchMedia('(min-width: 992px)');
      observeMediaQuery(desktopViewport, function (event) {
        if (event.matches) {
          setMenuOpen(false);
        }
      });
    }

    const configStatus = document.getElementById('site-config-status');
    const configRetry = document.getElementById('site-config-retry');
    let configuring = false;

    async function configureSite() {
      if (configuring || siteConfig) return;
      configuring = true;
      if (configRetry) configRetry.disabled = true;
      document.documentElement.dataset.configStatus = 'loading';
      try {
        const config = await window.anatodSiteConfig.load(document, window.location);
        initialiseRegionalContent(config);
        siteConfig = config;
        applyTheme(root.dataset.bsTheme);
        refreshMenuLabels();
        if (configStatus) configStatus.hidden = true;
        if (configRetry) configRetry.hidden = true;
        document.documentElement.dataset.configStatus = 'ready';
      } catch (error) {
        document.documentElement.dataset.configStatus = 'error';
        if (configStatus) {
          configStatus.textContent = 'No pudimos cargar la información de esta web. Vuelve a intentarlo.';
          configStatus.hidden = false;
        }
        if (configRetry) {
          configRetry.hidden = false;
          configRetry.disabled = false;
        }
        console.warn('No se pudo cargar la configuración de anatod.', error);
      } finally {
        configuring = false;
      }
    }

    if (configRetry) configRetry.addEventListener('click', configureSite);
    configureSite();

    const currentYear = document.getElementById('current-year');
    if (currentYear) {
      currentYear.textContent = String(new Date().getFullYear());
    }
  }

  function initialiseRegionalContent(config) {
    // Prices use only the currency configured for this domain.
    document.querySelectorAll('[data-price-key]').forEach(function (price) {
      const amount = config.pricing.rates[price.dataset.priceKey][config.currency];
      const decimals = price.dataset.priceDecimals === '2' ? 2 : 0;
      price.textContent = new Intl.NumberFormat(config.locale, {
        style: 'currency',
        currency: config.currency,
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      }).format(amount);
      price.hidden = false;
    });

    // The host determines the destination. The visitor's country is only context.
    const contact = config.contact;
    const phone = document.getElementById('contact-phone');
    const regionLabel = document.getElementById('contact-region');
    const whatsapp = document.getElementById('contact-whatsapp');
    const countryField = document.getElementById('contact-country');
    if (phone) {
      phone.href = 'tel:+' + contact.number;
      const numberLabel = phone.querySelector('[data-phone-number]');
      if (numberLabel) numberLabel.textContent = contact.display;
      phone.hidden = false;
    }
    if (regionLabel) regionLabel.textContent = contact.label;
    if (whatsapp) {
      whatsapp.href = 'https://wa.me/' + contact.number + '?text=' + encodeURIComponent(contact.message);
      whatsapp.hidden = false;
    }
    if (countryField) {
      countryField.defaultValue = config.country || '';
      countryField.value = config.country || '';
    }

    const form = document.getElementById('contact-form');
    const result = document.getElementById('form-result');
    const request = document.getElementById('whatsapp-request');
    if (!form || !result || !request) return;
    const fields = {
      name: document.getElementById('contact-name'),
      company: document.getElementById('contact-company'),
      email: document.getElementById('contact-email'),
      service: document.getElementById('contact-service'),
      message: document.getElementById('contact-message')
    };
    if (!Object.values(fields).every(Boolean)) return;

    function clearRequest() {
      result.hidden = true;
      request.removeAttribute('href');
    }
    [fields.name, fields.company].forEach(function (field) {
      field.addEventListener('input', function () { field.setCustomValidity(''); });
    });
    form.addEventListener('input', clearRequest);
    form.addEventListener('change', clearRequest);
    form.addEventListener('reset', function () {
      clearRequest();
      fields.name.setCustomValidity('');
      fields.company.setCustomValidity('');
    });
    clearRequest();

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      fields.name.setCustomValidity(fields.name.value.trim() ? '' : config.messages.requiredName);
      fields.company.setCustomValidity(fields.company.value.trim() ? '' : config.messages.requiredCompany);
      if (!form.reportValidity()) return;

      const lines = [
        contact.message,
        '',
        config.messages.requestName + ': ' + fields.name.value.trim(),
        config.messages.requestCompany + ': ' + fields.company.value.trim(),
        config.messages.requestEmail + ': ' + fields.email.value.trim()
      ];
      if (countryField && countryField.value.trim()) {
        lines.push(config.messages.requestCountry + ': ' + countryField.value.trim());
      }
      if (fields.service.value.trim()) {
        const selected = fields.service.selectedOptions[0];
        lines.push(config.messages.requestService + ': ' + selected.textContent.trim());
      }
      if (fields.message.value.trim()) {
        lines.push('', config.messages.requestMessage + ': ' + fields.message.value.trim());
      }
      request.href = 'https://wa.me/' + contact.number + '?text=' + encodeURIComponent(lines.join('\n'));
      request.target = '_blank';
      request.rel = 'noopener noreferrer';
      result.querySelector('[data-form-result-message]').textContent = config.texts['form.ready'];
      result.hidden = false;
    });
    document.getElementById('prepare-request').disabled = false;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise, { once: true });
  } else {
    initialise();
  }
})();