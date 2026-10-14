"""Comprueba feeds, traducciones y contratos de plantillas sin ejecutar PHP."""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
pages = json.loads((ROOT / 'assets/config/seo.min.json').read_text())
assert len(pages) == 19
posts = json.loads((ROOT / 'blog/posts.min.json').read_text())
assert len({post['id'] for post in posts}) == len(posts)
assert all(isinstance(post['id'], int) and post['id'] > 0 for post in posts)
source_posts = json.loads((ROOT / 'blog/posts.json').read_text())
for item in source_posts + posts:
    hrefs = re.findall(r'<a\b[^>]*?\bhref\s*=\s*["\']([^"\']+)', item['content_html'], re.I)
    assert not any('/blog/event/' in href for href in hrefs), f'{item["id"]}: enlace de evento antiguo'
assert {post['id'] for post in source_posts} == {post['id'] for post in posts}
events = json.loads((ROOT / 'events/eventos.min.json').read_text())
source_events = json.loads((ROOT / 'events/eventos.json').read_text())
assert len({event['id'] for event in events}) == len(events)
assert all(isinstance(event['id'], int) and event['id'] > 0 for event in events)
assert {event['id'] for event in source_events} == {event['id'] for event in events}
for kind, items in [('blog', source_posts), ('events', source_events)]:
    routes = set()
    for item in items:
        prefix = '/blog/' + item['date'].replace('-', '/') + '/' if kind == 'blog' else '/evento/'
        url = prefix + str(item['id']) + '_' + item['slug'] + '/'
        assert url not in routes
        routes.add(url)
for url, page in pages.items():
    assert url.startswith('/') and (ROOT / page['script']).is_file()
    assert page['title'].strip() and page['description'].strip()
for language in ['en', 'eu']:
    source = json.loads((ROOT / 'languages' / (language + '.json')).read_text())
    for translation in source.values():
        hrefs = re.findall(r'<a\b[^>]*?\bhref\s*=\s*["\']([^"\']+)', translation, re.I)
        assert not any('/blog/event/' in href for href in hrefs), f'{language}: enlace de evento antiguo'
    served = json.loads((ROOT / 'languages' / (language + '.min.json')).read_text())
    # La carga usa el fuente como referencia aunque el generado altere claves
    # o cadenas HTML. Conserva claves adicionales de un despliegue min-only.
    served.update({text: translation for text, translation in source.items()
                   if isinstance(translation, str) and translation.strip()})
    for filename, fields in [('blog/posts.min.json', ['title', 'excerpt', 'content_html']),
                             ('events/eventos.min.json', ['title', 'detail'])]:
        for item in json.loads((ROOT / filename).read_text()):
            for field in fields:
                if 'id' in item:
                    kind = 'blog' if filename.startswith('blog/') else 'events'
                    stable_key = f'{kind}.{item["id"]}.{field}'
                    assert source.get(stable_key, '').strip(), f'{language}: falta {stable_key}'
                else:
                    assert served.get(item[field], '').strip(), f'{language}: {item["slug"]}, {field}'
    for page in pages.values():
        for field in ['title', 'description']:
            assert served.get(page[field], '').strip(), f'{language}: metadatos sin traducir'

for source in (ROOT / '_views').glob('*.html'):
    text = source.read_text()
    assert not re.search(r'<a\b[^>]*href="/(?!/)', text), f'{source.name}: enlace sin idioma'
    assert 'property="og:' not in text, f'{source.name}: metadatos sociales duplicados'
    # Las rutas de entrega siguen apuntando a los mismos artefactos minificados.
    if source.stem not in ['head', 'header', 'footer']:
        assert 'head.min.html' in text and 'header.min.html' in text and 'footer.min.html' in text

contact = (ROOT / '_views/contact.html').read_text()
software = (ROOT / '_views/software.html').read_text()
assert len(re.findall(r'<h1\b', contact)) == 1
assert 'aria-labelledby="contact-title"' in contact
assert 'id="contact-phone" hidden' not in contact and 'id="contact-whatsapp" hidden' not in contact
assert len(re.findall(r'data-price-key="[^"]+">', software)) == 8
assert 'data-price-key="tier5000" hidden' not in software
print('SEO: 19 páginas, traducciones de artículos/eventos, enlaces y HTML inicial: OK')
