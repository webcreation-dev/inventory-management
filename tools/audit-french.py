"""Check literal translation keys and interpolation placeholders in the French catalogue."""
from pathlib import Path
import json
import re

catalogue = json.loads(Path('resources/lang/fr.json').read_text())
missing = set()
for root in ['app', 'Modules', 'resources/views']:
    for path in Path(root).rglob('*.php'):
        source = path.read_text(errors='ignore')
        for match in re.finditer(r'''__\(\s*(['"])((?:\\.|(?!\1).)*?)\1\s*[,)]''', source):
            key = match[2].replace("\\'", "'").replace('\\"', '"')
            if key not in catalogue and re.search('[A-Za-z]', key) and not any(c in key for c in 'éèêàçœîôù'):
                missing.add(key)
if missing:
    raise SystemExit('Missing French translations: ' + repr(sorted(missing)))
english = json.loads(Path('resources/lang/en.json').read_text())
for key, value in catalogue.items():
    parameters = lambda text: set(re.findall(r'(?<!\w):([a-zA-Z][a-zA-Z_0-9]*)', text))
    expected = parameters(key) or parameters(english.get(key, ''))
    if parameters(value) != expected:
        raise SystemExit('Interpolation mismatch: ' + key)
print(f'Literal translation coverage: OK ({len(catalogue)} French entries)')
