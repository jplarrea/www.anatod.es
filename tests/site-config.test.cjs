'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {
  normalizeHostname,
  mergeConfig,
  resolveConfig,
  resolveLink
} = require('../assets/js/site-config.js');

function fixture() {
  return {
    version: 1,
    defaultDomain: 'anatod.com',
    localDomain: 'anatod.es',
    pricing: {
      currencies: { EUR: 'Euros', USD: 'Dólares estadounidenses' },
      rates: { tier5000: { EUR: 1350, USD: 1500 } }
    },
    defaults: {
      lang: 'es',
      locale: 'es-ES',
      currency: 'USD',
      contact: {
        number: '14044965122',
        display: '+1 404 496-5122',
        label: 'Atención comercial · Global',
        message: 'Hola, me gustaría conocer Anatod.'
      },
      texts: {
        'hero.title': 'Software de gestión para ISP',
        'hero.description': 'Internet, TV, telefonía y suscripciones.',
        'contact.note': 'Equipo propio.'
      },
      links: { privacy: 'https://www.anatod.com/privacy-policy/' },
      images: { team: 'assets/img/equipo-anatod.jpg' },
      messages: {
        requiredName: 'Introduce tu nombre.',
        requiredCompany: 'Introduce el nombre de tu empresa.',
        requestName: 'Nombre',
        requestCompany: 'Empresa',
        requestEmail: 'Email',
        requestCountry: 'País',
        requestService: 'Servicio',
        requestMessage: 'Mensaje'
      }
    },
    domains: {
      'anatod.es': {
        market: 'ES', country: 'España', currency: 'EUR', locale: 'es-ES',
        contact: {
          number: '34683227840', display: '+34 683 22 78 40',
          label: 'Atención comercial · España'
        },
        texts: { 'hero.description': 'Gestión para operadores de España.' }
      },
      'anatod.com.mx': {
        market: 'MX', country: 'México', currency: 'USD', locale: 'es-MX',
        contact: {
          number: '525654000698', display: '+52 56 5400 0698',
          label: 'Atención comercial · México'
        },
        texts: {}
      },
      'anatod.com.ar': {
        market: 'AR', country: 'Argentina', currency: 'USD', locale: 'es-AR',
        contact: {
          number: '5492915373762', display: '+54 9 291 5373762',
          label: 'Atención comercial · Argentina'
        },
        texts: {}
      },
      'anatod.com': {
        market: 'GLOBAL', country: 'Global', currency: 'USD', locale: 'es',
        texts: {}
      }
    }
  };
}

const profiles = [
  ['anatod.es', 'ES', '34683227840', 'EUR'],
  ['anatod.com.mx', 'MX', '525654000698', 'USD'],
  ['anatod.com.ar', 'AR', '5492915373762', 'USD'],
  ['anatod.com', 'GLOBAL', '14044965122', 'USD']
];

for (const [domain, market, number, currency] of profiles) {
  for (const hostname of [domain, 'www.' + domain]) {
    test('selects the correct contact for ' + hostname, () => {
      const result = resolveConfig(fixture(), { hostname, search: '' });
      assert.equal(result.domain, domain);
      assert.equal(result.market, market);
      assert.equal(result.contact.number, number);
      assert.equal(result.currency, currency);
      assert.equal(result.previewDomain, null);
    });
  }
}

test('normalizes uppercase, www and a trailing DNS dot', () => {
  assert.equal(normalizeHostname('WWW.ANATOD.COM.MX.'), 'anatod.com.mx');
  const result = resolveConfig(fixture(), { hostname: 'WWW.ANATOD.COM.MX.', search: '' });
  assert.equal(result.market, 'MX');
});

for (const hostname of ['unknown.example', 'anatod.es.example', 'notanatod.es', 'www.anatod.com.mx.example']) {
  test('uses the global profile for the unrecognized hostname ' + hostname, () => {
    const result = resolveConfig(fixture(), { hostname, search: '' });
    assert.equal(result.domain, 'anatod.com');
    assert.equal(result.market, 'GLOBAL');
    assert.equal(result.contact.number, '14044965122');
    assert.equal(result.previewDomain, null);
  });
}

for (const hostname of ['localhost', '127.0.0.1', '::1']) {
  test('uses the configured local profile on ' + hostname, () => {
    const result = resolveConfig(fixture(), { hostname, search: '' });
    assert.equal(result.domain, 'anatod.es');
    assert.equal(result.market, 'ES');
  });

  test('allows explicit domain preview only on ' + hostname, () => {
    const result = resolveConfig(fixture(), { hostname, search: '?site=www.anatod.com.mx' });
    assert.equal(result.domain, 'anatod.com.mx');
    assert.equal(result.previewDomain, 'anatod.com.mx');
    assert.equal(result.market, 'MX');
    assert.equal(result.contact.number, '525654000698');
  });
}

test('query parameters cannot change the contact on a public domain', () => {
  const result = resolveConfig(fixture(), {
    hostname: 'www.anatod.es', search: '?site=www.anatod.com.mx'
  });
  assert.equal(result.market, 'ES');
  assert.equal(result.contact.number, '34683227840');
  assert.equal(result.previewDomain, null);
});

test('currency and preview parameters cannot override the currency of a public domain', () => {
  for (const [domain, , , currency] of profiles) {
    const otherCurrency = currency === 'EUR' ? 'USD' : 'EUR';
    const otherDomain = currency === 'EUR' ? 'anatod.com.mx' : 'anatod.es';
    const result = resolveConfig(fixture(), {
      hostname: 'www.' + domain,
      search: '?currency=' + otherCurrency + '&site=' + otherDomain
    });
    assert.equal(result.domain, domain);
    assert.equal(result.currency, currency, domain);
    assert.equal(result.previewDomain, null);
  }
});

test('a local preview uses the chosen domain currency and ignores currency query parameters', () => {
  const result = resolveConfig(fixture(), {
    hostname: '127.0.0.1', search: '?site=anatod.com.mx&currency=EUR'
  });
  assert.equal(result.domain, 'anatod.com.mx');
  assert.equal(result.currency, 'USD');
});

test('a domain can use another configured currency with published rates', () => {
  const raw = fixture();
  raw.domains['anatod.es'].currency = 'USD';
  const spain = resolveConfig(raw, { hostname: 'www.anatod.es', search: '' });
  assert.equal(spain.currency, 'USD');
  assert.equal(spain.pricing.rates.tier5000[spain.currency], 1500);

  raw.pricing.currencies.GBP = 'Libras esterlinas';
  raw.pricing.rates.tier5000.GBP = 1200;
  raw.domains['anatod.com.mx'].currency = 'GBP';
  const mexico = resolveConfig(raw, { hostname: 'www.anatod.com.mx', search: '' });
  assert.equal(mexico.currency, 'GBP');
  assert.equal(mexico.pricing.rates.tier5000[mexico.currency], 1200);
});

test('a local-looking public hostname cannot enable preview', () => {
  const result = resolveConfig(fixture(), {
    hostname: 'localhost.example', search: '?site=www.anatod.com.mx'
  });
  assert.equal(result.market, 'GLOBAL');
  assert.equal(result.previewDomain, null);
});

test('inherits common text, overrides selected text and preserves an intentional empty string', () => {
  const raw = fixture();
  raw.domains['anatod.es'].texts['contact.note'] = '';
  const result = resolveConfig(raw, { hostname: 'anatod.es', search: '' });
  assert.equal(result.texts['hero.title'], raw.defaults.texts['hero.title']);
  assert.equal(result.texts['hero.description'], 'Gestión para operadores de España.');
  assert.equal(result.texts['contact.note'], '');
  assert.equal(result.contact.message, raw.defaults.contact.message);
});

test('merges nested configuration without mutating either input', () => {
  const base = { texts: { title: 'Shared', note: 'Note' }, nested: { amount: 1 } };
  const override = { texts: { title: 'Local' }, nested: { enabled: true } };
  const beforeBase = structuredClone(base);
  const beforeOverride = structuredClone(override);
  const merged = mergeConfig(base, override);
  assert.deepEqual(merged, {
    texts: { title: 'Local', note: 'Note' }, nested: { amount: 1, enabled: true }
  });
  merged.texts.note = 'Changed after merge';
  assert.deepEqual(base, beforeBase);
  assert.deepEqual(override, beforeOverride);
});

test('resolving and then editing one profile leaves source and other profiles untouched', () => {
  const raw = fixture();
  const original = structuredClone(raw);
  const result = resolveConfig(raw, { hostname: 'anatod.es', search: '' });
  result.texts['hero.title'] = 'Edited result';
  result.pricing.rates.tier5000.EUR = 999;
  assert.deepEqual(raw, original);
  const mexico = resolveConfig(raw, { hostname: 'anatod.com.mx', search: '' });
  assert.equal(mexico.texts['hero.title'], 'Software de gestión para ISP');
  assert.equal(mexico.pricing.rates.tier5000.EUR, 1350);
});

test('all four domains share the complete global price list', () => {
  const raw = fixture();
  for (const [hostname] of profiles) {
    const result = resolveConfig(raw, { hostname, search: '' });
    assert.deepEqual(result.pricing, raw.pricing, hostname);
  }
});

test('defaults and domain overrides cannot replace global prices or currencies', () => {
  const raw = fixture();
  raw.defaults.pricing = {
    currencies: { ARS: 'Pesos argentinos' },
    rates: { tier5000: { EUR: 1, USD: 2 }, extra: { EUR: 3, USD: 4 } }
  };
  for (const [hostname] of profiles) {
    raw.domains[hostname].pricing = {
      currencies: { MXN: 'Pesos mexicanos' },
      rates: { tier5000: { EUR: -1, USD: 0 }, extra: { EUR: 5, USD: 6 } }
    };
    const result = resolveConfig(raw, { hostname, search: '' });
    assert.deepEqual(result.pricing, raw.pricing, hostname);
  }
});

test('regional or default rates cannot stand in for a missing global price list', () => {
  const raw = fixture();
  raw.defaults.pricing = raw.pricing;
  raw.domains['anatod.es'].pricing = raw.pricing;
  delete raw.pricing;
  assert.throws(() => resolveConfig(raw, { hostname: 'anatod.es', search: '' }), /faltan tarifas/);
});

test('ignores prototype pollution keys at every merge depth', () => {
  const malicious = JSON.parse('{"__proto__":{"polluted":true},"constructor":{"prototype":{"polluted":true}},"prototype":{"polluted":true},"texts":{"__proto__":{"polluted":true},"constructor":"bad","prototype":"bad","title":"Safe"}}');
  const merged = mergeConfig(malicious, malicious);
  assert.equal({}.polluted, undefined);
  assert.equal(merged.texts.title, 'Safe');
  for (const object of [merged, merged.texts]) {
    for (const key of ['__proto__', 'constructor', 'prototype']) {
      assert.equal(Object.hasOwn(object, key), false, key + ' must be ignored');
    }
  }
});

for (const raw of [null, [], {}, { version: 1, defaults: {}, domains: {} }]) {
  test('rejects malformed configuration ' + JSON.stringify(raw), () => {
    assert.throws(() => resolveConfig(raw, { hostname: 'anatod.es', search: '' }));
  });
}

test('rejects an invalid contact number in the selected domain', () => {
  const raw = fixture();
  raw.domains['anatod.es'].contact.number = 'javascript:alert(1)';
  assert.throws(() => resolveConfig(raw, { hostname: 'anatod.es', search: '' }));
});

test('rejects a selected currency that has no configured pricing', () => {
  const raw = fixture();
  raw.domains['anatod.es'].currency = 'INVALID';
  assert.throws(() => resolveConfig(raw, { hostname: 'anatod.es', search: '' }));
});

test('rejects invalid prices in the global price list', () => {
  const raw = fixture();
  raw.pricing.rates.tier5000.EUR = -1;
  assert.throws(() => resolveConfig(raw, { hostname: 'anatod.es', search: '' }));
});

test('requires the messages used to prepare a contact request', () => {
  const keys = [
    'requiredName', 'requiredCompany', 'requestName',
    'requestCompany', 'requestEmail', 'requestCountry', 'requestService', 'requestMessage'
  ];
  for (const key of keys) {
    const raw = fixture();
    delete raw.defaults.messages[key];
    assert.throws(
      () => resolveConfig(raw, { hostname: 'anatod.es', search: '' }),
      'Missing messages.' + key + ' must fail before enabling the form'
    );
  }
});

test('a fragment link stays on software.html and retains the local preview', () => {
  const resolved = resolveLink(
    '#contacto',
    'http://127.0.0.1:8088/',
    'http://127.0.0.1:8088/software.html?site=anatod.com.mx'
  );
  assert.equal(resolved.href, 'http://127.0.0.1:8088/software.html?site=anatod.com.mx#contacto');
});

test('relative navigation resolves against the deployed site directory', () => {
  const resolved = resolveLink(
    'software.html#precios',
    'https://www.anatod.es/pilot/',
    'https://www.anatod.es/pilot/index.html'
  );
  assert.equal(resolved.href, 'https://www.anatod.es/pilot/software.html#precios');
});

test('query-only navigation retains the current page', () => {
  const resolved = resolveLink(
    '?site=anatod.com.ar',
    'http://localhost:8088/',
    'http://localhost:8088/software.html'
  );
  assert.equal(resolved.href, 'http://localhost:8088/software.html?site=anatod.com.ar');
});

test('configuration cannot supply a javascript link', () => {
  assert.throws(() => resolveLink('javascript:alert(1)', 'https://www.anatod.es/', 'https://www.anatod.es/software.html'));
});

const siteDirectory = path.resolve(__dirname, '..');
const actualConfig = JSON.parse(fs.readFileSync(path.join(siteDirectory, 'assets/config/site.json'), 'utf8'));

test('the shipped global prices are identical across all country profiles', () => {
  assert.ok(actualConfig.pricing, 'Global prices belong at the root of site.json');
  assert.equal(Object.hasOwn(actualConfig.defaults, 'pricing'), false);
  for (const [hostname] of profiles) {
    assert.equal(Object.hasOwn(actualConfig.domains[hostname], 'pricing'), false);
    const result = resolveConfig(actualConfig, { hostname, search: '' });
    assert.deepEqual(result.pricing, actualConfig.pricing, hostname);
  }
});

for (const [domain, market, number, currency] of profiles) {
  test('the shipped JSON assigns the correct contact to ' + domain, () => {
    const result = resolveConfig(actualConfig, { hostname: 'www.' + domain, search: '' });
    assert.equal(result.domain, domain);
    assert.equal(result.market, market);
    assert.equal(result.contact.number, number);
    assert.equal(result.currency, currency);
  });
}

for (const page of ['index.html', 'software.html', 'about.html']) {
  test('all configurable bindings in ' + page + ' exist for every shipped domain', () => {
    const html = fs.readFileSync(path.join(siteDirectory, page), 'utf8');
    const bindings = [...html.matchAll(/\b(data-copy(?:-placeholder|-alt|-aria-label|-content)?|data-link|data-image|data-price-key)="([^"]+)"/g)];
    assert.ok(bindings.length > 0, 'Expected configuration bindings in ' + page);
    for (const domain of Object.keys(actualConfig.domains)) {
      const config = resolveConfig(actualConfig, { hostname: domain, search: '' });
      for (const [, attribute, key] of bindings) {
        const values = attribute === 'data-link' ? config.links
          : attribute === 'data-image' ? config.images
          : attribute === 'data-price-key' ? config.pricing.rates
          : config.texts;
        assert.ok(Object.hasOwn(values, key), page + ' / ' + domain + ': missing ' + attribute + '="' + key + '"');
      }
    }
  });
}

test('the shipped static pages contain no hard-coded regional contact destination', () => {
  for (const page of ['index.html', 'software.html', 'about.html']) {
    const html = fs.readFileSync(path.join(siteDirectory, page), 'utf8');
    assert.doesNotMatch(html, /(?:tel:\+?\d|https:\/\/wa\.me\/\d)/, page + ' must not show a contact before domain configuration loads');
  }
});

test('a missing JSON fails before applying content and remains retryable', async (t) => {
  const { load } = require('../assets/js/site-config.js');
  const doc = {
    baseURI: 'https://www.anatod.com.mx/software.html',
    querySelector: () => ({ src: 'https://www.anatod.com.mx/assets/js/site-config.js' }),
    querySelectorAll: () => { throw new Error('Content must not change on a failed request'); }
  };
  const fetchMock = t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 404 }));
  await assert.rejects(load(doc, { hostname: 'www.anatod.com.mx', search: '' }), /404/);
  assert.equal(String(fetchMock.mock.calls[0].arguments[0]), 'https://www.anatod.com.mx/assets/config/site.json');
  assert.equal(fetchMock.mock.calls[0].arguments[1].cache, 'no-store');
  await assert.rejects(load(doc, { hostname: 'www.anatod.com.mx', search: '' }), /404/);
  assert.equal(fetchMock.mock.calls.length, 2);
});

test('malformed JSON fails before applying any regional content', async (t) => {
  const { load } = require('../assets/js/site-config.js');
  const doc = {
    baseURI: 'https://www.anatod.es/software.html',
    querySelector: () => ({ src: 'https://www.anatod.es/assets/js/site-config.js' }),
    querySelectorAll: () => { throw new Error('Content must not change on malformed JSON'); }
  };
  t.mock.method(globalThis, 'fetch', async () => ({
    ok: true,
    json: async () => { throw new SyntaxError('Invalid JSON'); }
  }));
  await assert.rejects(load(doc, { hostname: 'www.anatod.es', search: '' }), SyntaxError);
});


const staticPages = ['index.html', 'software.html', 'about.html'];

function tagAttributes(html) {
  return [...html.matchAll(/<([a-z][a-z0-9:-]*)\b([^<>]*)>/gi)].map(([, tag, source]) => {
    const attrs = Object.fromEntries([...source.matchAll(/([a-z][a-z0-9:-]*)\s*=\s*(?:"([^"]*)"|'([^']*)')/gi)]
      .map(([, name, doubleQuoted, singleQuoted]) => [name.toLowerCase(), doubleQuoted ?? singleQuoted]));
    return { tag: tag.toLowerCase(), attrs };
  });
}

function checkLocalReference(value, page, description) {
  if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(value)) return;
  const localURL = resolveLink(value, 'https://local.test/', 'https://local.test/' + page);
  const relativePath = decodeURIComponent(localURL.pathname).replace(/^\/+/, '') || 'index.html';
  const filePath = path.resolve(siteDirectory, relativePath);
  assert.ok(filePath.startsWith(siteDirectory + path.sep), description + ': must stay inside the site directory');
  assert.ok(fs.existsSync(filePath), description + ': missing local file ' + relativePath);
  assert.ok(fs.statSync(filePath).isFile(), description + ': expected a file ' + relativePath);
  if (localURL.hash && /\.html$/i.test(relativePath)) {
    const targetId = decodeURIComponent(localURL.hash.slice(1));
    const targetHtml = fs.readFileSync(filePath, 'utf8');
    const ids = tagAttributes(targetHtml).map(({ attrs }) => attrs.id).filter(Boolean);
    assert.ok(ids.includes(targetId), description + ': missing #' + targetId + ' in ' + relativePath);
  }
}

for (const page of staticPages) {
  test('local fallback links, anchors and assets resolve in ' + page, () => {
    const html = fs.readFileSync(path.join(siteDirectory, page), 'utf8');
    for (const { tag, attrs } of tagAttributes(html)) {
      for (const attribute of ['href', 'src']) {
        if (Object.hasOwn(attrs, attribute)) {
          checkLocalReference(attrs[attribute], page, page + ' / ' + tag + '[' + attribute + ']');
        }
      }
    }
  });

  test('configured navigation and images resolve from ' + page + ' for all domains', () => {
    const html = fs.readFileSync(path.join(siteDirectory, page), 'utf8');
    const tags = tagAttributes(html);
    for (const domain of Object.keys(actualConfig.domains)) {
      const config = resolveConfig(actualConfig, { hostname: domain, search: '' });
      for (const { attrs } of tags) {
        for (const [attribute, group] of [['data-link', 'links'], ['data-image', 'images']]) {
          if (!Object.hasOwn(attrs, attribute)) continue;
          const key = attrs[attribute];
          checkLocalReference(config[group][key], page, page + ' / ' + domain + ' / ' + group + '.' + key);
        }
      }
    }
  });
}

test('company links open the local About page for every domain', () => {
  for (const domain of Object.keys(actualConfig.domains)) {
    const config = resolveConfig(actualConfig, { hostname: domain, search: '' });
    assert.equal(config.links.company, 'about.html', domain + ' company link');
    assert.equal(config.links.about, 'about.html', domain + ' about link');
  }
});

test('the About page has configurable metadata and editorial text', () => {
  const html = fs.readFileSync(path.join(siteDirectory, 'about.html'), 'utf8');
  const tags = tagAttributes(html);
  assert.match(html, /data-page="about"/);
  assert.ok(tags.some(({ tag, attrs }) => tag === 'title' && attrs['data-copy'] === 'about.meta.title'));
  assert.ok(tags.some(({ tag, attrs }) => tag === 'meta' && attrs.name === 'description' && attrs['data-copy-content'] === 'about.meta.description'));
  const aboutKeys = tags.flatMap(({ attrs }) => Object.entries(attrs))
    .filter(([attribute, key]) => attribute.startsWith('data-copy') && key.startsWith('about.'))
    .map(([, key]) => key);
  assert.ok(aboutKeys.length > 10, 'Expected About-specific editorial bindings');
  for (const key of aboutKeys) {
    assert.equal(typeof actualConfig.defaults.texts[key], 'string', key);
  }
});

test('About copy can be customized for one domain without changing the other pages or countries', () => {
  const raw = structuredClone(actualConfig);
  raw.domains['anatod.es'].texts['about.meta.title'] = 'Conoce al equipo de Anatod España';
  const spain = resolveConfig(raw, { hostname: 'anatod.es', search: '' });
  const mexico = resolveConfig(raw, { hostname: 'anatod.com.mx', search: '' });
  assert.equal(spain.texts['about.meta.title'], 'Conoce al equipo de Anatod España');
  assert.equal(mexico.texts['about.meta.title'], actualConfig.defaults.texts['about.meta.title']);
  assert.equal(spain.texts['home.meta.title'], actualConfig.domains['anatod.es'].texts['home.meta.title'] ?? actualConfig.defaults.texts['home.meta.title']);
  assert.deepEqual(raw.pricing, actualConfig.pricing);
});
