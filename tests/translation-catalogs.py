"""Comprueba los catálogos sin ejecutar PHP."""
import html
import json
import re
import runpy
from collections import Counter
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MARKERS = re.compile(r'ZXQSEG|ZXQTOKEN|QZX[A-F0-9]{12}XZQ')
PLACEHOLDERS = re.compile(r'%[A-Za-z_][A-Za-z0-9_]*%|%(?:[0-9]+\$)?[sd]')
URLS = re.compile(r'https?://[^\s<>"\']+|[\w.+\-]+@[\w.\-]+')
CODE = re.compile(r'<(pre|script|style)\b[^>]*>[\s\S]*?</\1>', re.I)


class Structure(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.tags = []

    def handle_starttag(self, tag, attrs):
        self.tags.append(('open', tag, [(key, value) for key, value in attrs
                                       if key not in ['alt', 'title', 'aria-label']]))

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        self.tags[-1] = ('self', *self.tags[-1][1:])

    def handle_endtag(self, tag):
        self.tags.append(('close', tag))


def read(path):
    def unique_keys(pairs):
        result = {}
        for key, value in pairs:
            assert key not in result, f'Clave duplicada: {key[:80]}'
            result[key] = value
        return result
    return json.loads(path.read_text(), object_pairs_hook=unique_keys)


extractor = runpy.run_path(str(ROOT / 'tools/extract-translations.py'))
source = set(extractor['extract']())
for lang in ['en', 'eu']:
    target = read(ROOT / 'languages' / (lang + '.json'))
    assert source <= target.keys(), f'{lang}: claves incompatibles'
    articles = 0
    for original, translated in target.items():
        assert isinstance(translated, str) and translated.strip(), f'{lang}: valor vacío'
        assert not MARKERS.search(translated), f'{lang}: marcador residual'
        assert sorted(PLACEHOLDERS.findall(original)) == sorted(PLACEHOLDERS.findall(translated)), original[:80]
        assert Counter(URLS.findall(html.unescape(original))) == Counter(URLS.findall(html.unescape(translated))), original[:80]
        assert [m[0] for m in CODE.finditer(original)] == [m[0] for m in CODE.finditer(translated)], f'{lang}: código alterado'
        if re.search(r'<(?:p|div|strong|figure|ul|h[1-6]|blockquote)\b', original):
            articles += 1
            before, after = Structure(), Structure()
            before.feed(original)
            after.feed(translated)
            assert before.tags == after.tags, f'{lang}: estructura HTML alterada: {original[:80]}'
    print(f'{lang}: {len(target)} entradas completas; {articles} artículos HTML; claves, marcadores, enlaces y código OK')
