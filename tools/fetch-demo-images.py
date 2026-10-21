"""Download the reviewed selection using its recorded URLs and credits.
Stops on HTTP errors, including Wikimedia rate limits; do not retry in a loop.
"""
import json,urllib.request
from pathlib import Path
root=Path('public/demo-assets/electronics');root.mkdir(parents=True,exist_ok=True)
for key,entry in json.loads(Path('database/demo/image-attributions.json').read_text()).items():
    target=root/entry['file']
    if target.exists():
        print(key,'already present');continue
    url=entry.get('download',entry['original'])
    request=urllib.request.Request(url,headers={'User-Agent':'InventoryDemoSeeder/1.0 (attribution in image-attributions.json)'})
    target.write_bytes(urllib.request.urlopen(request,timeout=60).read())
    print(key,'downloaded')
