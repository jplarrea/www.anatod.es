const assert = require('node:assert/strict');
const fs = require('node:fs');
const api = require('../assets/js/site-config.js');
const source = JSON.parse(fs.readFileSync('assets/config/site.json', 'utf8'));
const served = JSON.parse(fs.readFileSync('assets/config/site.min.json', 'utf8'));
const settings = JSON.parse(fs.readFileSync('languages/settings.json', 'utf8'));
const resolve = (raw, hostname, search = '') => api.resolveConfig(raw, { hostname, search });
const payload = (lang, texts = {}, policy = settings) => ({
  lang,
  locale: policy.languages[lang]?.locale,
  languages: Object.keys(policy.languages),
  fixedDomains: policy.fixedDomains,
  multilingualDomain: policy.multilingualDomain,
  texts,
});
assert.deepEqual(settings.fixedDomains, {
  'anatod.eus': 'eu', 'anatod.es': 'es', 'anatod.com.ar': 'es', 'anatod.com.mx': 'es',
});
assert.equal(settings.languages.eu.domain, 'anatod.eus');
assert.equal(settings.languages.es.domain, 'anatod.com');
assert.equal(settings.languages.en.domain, 'anatod.com');
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
  // Cada dominio fijo rechaza los idiomas ajenos sin alterar sus textos.
  for (const [host, allowed] of Object.entries(settings.fixedDomains)) {
    for (const lang of Object.keys(settings.languages)) {
      const config = resolve(raw, host);
      const before = JSON.stringify(config);
      api.applyLanguage(config, payload(lang, { 'Abrir menú': 'translated menu' }));
      if (lang !== allowed) assert.equal(JSON.stringify(config), before, host + ': ' + lang);
      else {
        assert.equal(config.lang, allowed);
        assert.equal(config.messages.menuOpen, 'translated menu');
      }
    }
  }
  for (const host of ['anatod.es', 'anatod.com.ar', 'anatod.com.mx', 'anatod.com']) {
    const originalConfig = resolve(raw, host);
    const config = api.applyLanguage(resolve(raw, host), payload('es'));
    assert.equal(config.currency, originalConfig.currency);
    assert.equal(config.locale, originalConfig.locale);
    assert.deepEqual(config.contact, originalConfig.contact);
  }
}
const config = resolve(served, 'anatod.com');
const originalNumber = config.contact.number;
const originalCurrency = config.currency;
const english = api.applyLanguage(config, payload('en', {
  'Cambiar a modo oscuro': 'Switch to dark mode',
  [config.contact.message]: 'Hello, I would like a software demo.',
  [config.texts['home.meta.title']]: 'anatod · ISP management software',
}));
assert.equal(english.lang, 'en');
assert.equal(english.locale, 'en-GB');
assert.equal(english.texts['common.locale'], 'en_GB');
assert.equal(english.messages.themeDark, 'Switch to dark mode');
assert.equal(english.contact.message, 'Hello, I would like a software demo.');
assert.equal(english.texts['home.meta.title'], 'anatod · ISP management software');
assert.equal(english.contact.number, originalNumber);
assert.equal(english.currency, originalCurrency);
assert.equal(english.messages.menuClose, 'Cerrar menú');
const globalBasque = resolve(served, 'anatod.com');
const beforeBasque = JSON.stringify(globalBasque);
api.applyLanguage(globalBasque, payload('eu'));
assert.equal(JSON.stringify(globalBasque), beforeBasque, '.com no admite euskera');
const basque = api.applyLanguage(resolve(served, 'anatod.eus'), payload('eu'));
assert.equal(basque.lang, 'eu');
assert.equal(basque.locale, 'eu-ES');
assert.equal(basque.texts['common.locale'], 'eu_ES');
assert.equal(api.applyLanguage(resolve(served, 'anatod.com'), payload('bad')).lang, 'es');
// Un nuevo idioma se habilita agregándolo al registro de idiomas de .com.
const futureSettings = structuredClone(settings);
futureSettings.languages.fr = { name: 'Français', locale: 'fr-FR', domain: 'anatod.com' };
const french = api.applyLanguage(resolve(served, 'anatod.com'), payload('fr', { 'Abrir menú': 'Ouvrir le menu' }, futureSettings));
assert.equal(french.lang, 'fr');
assert.equal(french.locale, 'fr-FR');
assert.equal(french.messages.menuOpen, 'Ouvrir le menu');
for (const host of Object.keys(settings.fixedDomains)) {
  const config = resolve(served, host);
  const before = JSON.stringify(config);
  api.applyLanguage(config, payload('fr', {}, futureSettings));
  assert.equal(JSON.stringify(config), before);
}
console.log('Dominios con idioma fijo, .com multilingüe y futuros idiomas: OK');
