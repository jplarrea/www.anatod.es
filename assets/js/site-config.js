(function (root, factory) {
  'use strict';
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.AnatodSiteConfig = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const blockedKeys = new Set(['__proto__', 'constructor', 'prototype']);
  const own = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
  const isObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);

  function normalizeHostname(hostname) {
    return String(hostname || '').toLowerCase().replace(/\.+$/, '').replace(/^www\./, '');
  }

  function mergeConfig(base, override) {
    const result = {};
    for (const source of [base, override]) {
      if (!isObject(source)) continue;
      Object.keys(source).forEach(function (key) {
        if (blockedKeys.has(key)) return;
        const value = source[key];
        result[key] = isObject(value)
          ? mergeConfig(isObject(result[key]) ? result[key] : {}, value)
          : Array.isArray(value) ? value.slice() : value;
      });
    }
    return result;
  }

  function isLocalHostname(hostname) {
    return ['localhost', '127.0.0.1', '::1', '[::1]'].includes(normalizeHostname(hostname));
  }

  function validateConfig(config) {
    const requireValue = (condition, message) => {
      if (!condition) throw new Error('Configuración del sitio: ' + message);
    };
    requireValue(typeof config.lang === 'string' && /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i.test(config.lang), 'idioma no válido.');
    requireValue(typeof config.locale === 'string', 'falta locale.');
    new Intl.NumberFormat(config.locale);
    requireValue(isObject(config.contact), 'falta contacto.');
    requireValue(typeof config.contact.number === 'string' && /^[1-9][0-9]{6,14}$/.test(config.contact.number), 'el teléfono debe contener solo dígitos con prefijo internacional.');
    ['display', 'label', 'message'].forEach(key => requireValue(typeof config.contact[key] === 'string' && config.contact[key].trim(), 'falta contact.' + key + '.'));
    ['texts', 'links', 'images', 'messages'].forEach(function (group) {
      requireValue(isObject(config[group]), 'falta ' + group + '.');
      Object.values(config[group]).forEach(value => requireValue(typeof value === 'string', group + ' debe contener textos.'));
    });
    ['requiredName', 'requiredCompany', 'requestName', 'requestCompany', 'requestEmail', 'requestCountry', 'requestService', 'requestMessage'].forEach(function (key) {
      requireValue(typeof config.messages[key] === 'string', 'falta messages.' + key + '.');
    });
    requireValue(isObject(config.pricing) && isObject(config.pricing.currencies) && isObject(config.pricing.rates), 'faltan tarifas.');
    const currencies = Object.keys(config.pricing.currencies);
    requireValue(currencies.length > 0 && currencies.every(code => /^[A-Z]{3}$/.test(code)), 'monedas no válidas.');
    requireValue(currencies.every(code => typeof config.pricing.currencies[code] === 'string'), 'faltan nombres de monedas.');
    requireValue(currencies.includes(config.currency), 'moneda del dominio no disponible.');
    requireValue(Object.keys(config.pricing.rates).length > 0, 'faltan importes.');
    Object.values(config.pricing.rates).forEach(function (rate) {
      requireValue(isObject(rate) && currencies.every(code => typeof rate[code] === 'number' && Number.isFinite(rate[code]) && rate[code] >= 0), 'cada tarifa debe tener un importe numérico para cada moneda.');
    });
    return config;
  }

  function resolveConfig(raw, location) {
    if (!isObject(raw) || raw.version !== 1 || !isObject(raw.defaults) || !isObject(raw.domains)) {
      throw new Error('Formato de configuración no válido.');
    }
    const fallback = normalizeHostname(raw.defaultDomain);
    if (!own(raw.domains, fallback) || !isObject(raw.domains[fallback])) {
      throw new Error('El dominio predeterminado no está configurado.');
    }
    const hostname = normalizeHostname(location.hostname);
    let domain = own(raw.domains, hostname) ? hostname : fallback;
    let previewDomain = null;
    if (isLocalHostname(hostname)) {
      const requested = normalizeHostname(new URLSearchParams(location.search || '').get('site'));
      const localDefault = normalizeHostname(raw.localDomain);
      domain = own(raw.domains, requested) ? requested : own(raw.domains, localDefault) ? localDefault : fallback;
      previewDomain = domain;
    }
    const config = mergeConfig(raw.defaults, raw.domains[domain]);
    // One price list applies worldwide. Regional overrides only choose the
    // displayed currency and number format; they cannot replace the rates.
    config.pricing = isObject(raw.pricing) ? mergeConfig({}, raw.pricing) : raw.pricing;
    config.domain = domain;
    config.previewDomain = previewDomain;
    return validateConfig(config);
  }

  function resourceURL(value, baseURL, image) {
    const url = new URL(value, baseURL);
    const permitted = image ? ['http:', 'https:', 'file:'] : ['http:', 'https:', 'file:', 'mailto:', 'tel:'];
    if (!permitted.includes(url.protocol)) throw new Error('Enlace no permitido en la configuración.');
    return url;
  }

  function resolveLink(value, baseURL, pageURL) {
    return resourceURL(value, /^[#?]/.test(value) ? pageURL : baseURL, false);
  }

  function applyConfig(config, doc, baseURL) {
    const attributes = {
      'data-copy': null,
      'data-copy-placeholder': 'placeholder',
      'data-copy-alt': 'alt',
      'data-copy-aria-label': 'aria-label',
      'data-copy-content': 'content'
    };
    // Validate all target values before changing the page.
    Object.keys(attributes).forEach(function (binding) {
      doc.querySelectorAll('[' + binding + ']').forEach(function (element) {
        const key = element.getAttribute(binding);
        if (!own(config.texts, key)) throw new Error('Falta el texto ' + key + '.');
      });
    });
    ['link', 'image'].forEach(function (kind) {
      doc.querySelectorAll('[data-' + kind + ']').forEach(function (element) {
        const key = element.getAttribute('data-' + kind);
        const values = kind === 'link' ? config.links : config.images;
        if (!own(values, key)) throw new Error('Falta ' + kind + ': ' + key + '.');
        if (kind === 'image') resourceURL(values[key], baseURL, true);
        else resolveLink(values[key], baseURL, doc.baseURI);
      });
    });
    doc.querySelectorAll('[data-price-key]').forEach(function (element) {
      if (!own(config.pricing.rates, element.dataset.priceKey)) throw new Error('Falta una tarifa de la página.');
    });

    doc.documentElement.lang = config.lang;
    doc.documentElement.dataset.market = config.market || '';
    doc.documentElement.dataset.siteDomain = config.domain;
    Object.entries(attributes).forEach(function ([binding, attribute]) {
      doc.querySelectorAll('[' + binding + ']').forEach(function (element) {
        const value = config.texts[element.getAttribute(binding)];
        if (attribute) element.setAttribute(attribute, value);
        else element.textContent = value;
      });
    });
    doc.querySelectorAll('[data-link]').forEach(function (element) {
      element.href = resolveLink(config.links[element.dataset.link], baseURL, doc.baseURI).href;
    });
    doc.querySelectorAll('[data-image]').forEach(function (element) {
      element.setAttribute(element.tagName === 'LINK' ? 'href' : 'src', resourceURL(config.images[element.dataset.image], baseURL, true).href);
    });
    // The preview parameter never overrides a production domain.
    if (config.previewDomain) {
      doc.querySelectorAll('a[href]').forEach(function (anchor) {
        const url = new URL(anchor.getAttribute('href'), doc.baseURI);
        if (url.origin !== new URL(doc.baseURI).origin || !/\/(?:index\.html|software\.html)?$/.test(url.pathname)) return;
        url.searchParams.set('site', config.previewDomain);
        anchor.href = url.href;
      });
    }
  }

  async function load(doc, location) {
    const script = doc.querySelector('script[src$="site-config.js"], script[src*="site-config.js?"]');
    const scriptURL = script ? script.src : new URL('assets/js/site-config.js', doc.baseURI).href;
    const configURL = new URL('../config/site.json', scriptURL);
    const response = await fetch(configURL, { cache: 'no-store', credentials: 'same-origin' });
    if (!response.ok) throw new Error('No se pudo cargar site.json (' + response.status + ').');
    const config = resolveConfig(await response.json(), location);
    applyConfig(config, doc, new URL('../../', configURL));
    return config;
  }

  return { normalizeHostname, mergeConfig, resolveConfig, resolveLink, applyConfig, load };
});
