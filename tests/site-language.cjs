const assert = require('node:assert/strict');
const fs = require('node:fs');
const api = require('../assets/js/site-config.js');
const source = JSON.parse(fs.readFileSync('assets/config/site.json', 'utf8'));
const served = JSON.parse(fs.readFileSync('assets/config/site.min.json', 'utf8'));
const resolve = (raw, hostname, search = '') => api.resolveConfig(raw, { hostname, search });
for (const raw of [source, served]) {
  const original = JSON.stringify(raw);
  for (const host of ['anatod.eus', 'www.anatod.eus', 'WWW.ANATOD.EUS']) {
    const config = resolve(raw, host);
    const spanish = resolve(raw, 'anatod.es');
    assert.equal(config.domain, 'anatod.eus');
    assert.equal(config.lang, 'eu');
    assert.equal(config.currency, spanish.currency);
    assert.deepEqual(config.contact, spanish.contact);
    assert.deepEqual(config.pricing.rates, spanish.pricing.rates);
  }
  assert.equal(resolve(raw, 'localhost', '?site=anatod.eus').domain, 'anatod.eus');
  assert.equal(resolve(raw, 'anatod.com.ar', '?site=anatod.eus').domain, 'anatod.com.ar');
  assert.equal(JSON.stringify(raw), original, 'Resolver dominios no debe mutar la configuración original');
  for (const host of Object.keys(served.domains)) {
    const originalConfig = resolve(raw, host);
    const config = resolve(raw, host);
    api.applyLanguage(config, { lang: 'es', locale: 'es-ES', texts: {} });
    assert.equal(config.currency, originalConfig.currency);
    assert.equal(config.locale, host === 'anatod.eus' ? 'es-ES' : originalConfig.locale);
    assert.deepEqual(config.contact, originalConfig.contact);
  }
}
const config = resolve(served, 'anatod.eus');
const originalNumber = config.contact.number;
const originalCurrency = config.currency;
const english = api.applyLanguage(config, { lang: 'en', locale: 'en-GB', texts: {
  'Cambiar a modo oscuro': 'Switch to dark mode',
  'España': 'Spain',
  [config.contact.message]: 'Hello, I would like a software demo.',
  [config.texts['home.meta.title']]: 'anatod Spain · ISP management software',
}});
assert.equal(english.lang, 'en');
assert.equal(english.locale, 'en-GB');
assert.equal(english.texts['common.locale'], 'en_GB');
assert.equal(english.messages.themeDark, 'Switch to dark mode');
assert.equal(english.country, 'Spain');
assert.equal(english.contact.message, 'Hello, I would like a software demo.');
assert.equal(english.texts['home.meta.title'], 'anatod Spain · ISP management software');
assert.equal(english.contact.number, originalNumber);
assert.equal(english.currency, originalCurrency);
assert.equal(english.messages.menuClose, 'Cerrar menú', 'Textos ausentes conservan el original');
const spanishOnBasque = api.applyLanguage(resolve(served, 'anatod.eus'), { lang: 'es', locale: 'es-ES', texts: {} });
assert.equal(spanishOnBasque.lang, 'es');
assert.equal(spanishOnBasque.locale, 'es-ES');
assert.equal(spanishOnBasque.texts['common.locale'], 'es_ES');
const basque = api.applyLanguage(resolve(served, 'anatod.eus'), { lang: 'eu', locale: 'eu-ES', texts: {} });
assert.equal(basque.lang, 'eu');
assert.equal(basque.locale, 'eu-ES');
assert.equal(basque.texts['common.locale'], 'eu_ES');
assert.equal(api.applyLanguage(resolve(served, 'anatod.es'), { lang: 'bad', texts: {} }).lang, 'es-ES');
console.log('Idiomas, dominios, respaldo y configuración regional: OK');
