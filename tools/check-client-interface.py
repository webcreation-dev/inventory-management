import urllib.request,urllib.error,urllib.parse,http.cookiejar,re,json,os
base=os.environ.get('INVENTORY_TEST_URL','http://127.0.0.1:8000')
assert urllib.parse.urlparse(base).hostname in ['127.0.0.1','localhost'], 'Local test server required'
jar=http.cookiejar.CookieJar();op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def get(path):
 r=op.open(base+path)
 if path in ['/dashboard','/item/create','/stock']: assert urllib.parse.urlparse(r.url).path==path, r.url
 return r.read().decode()
s=get('/login');assert 'lang="fr"' in s
csrf=re.search('name="_token" value="([^"]+)',s)[1]
signed_in=op.open(base+'/login',urllib.parse.urlencode({'_token':csrf,'email':os.environ.get('INVENTORY_TEST_EMAIL','admin@doorsoft.co'),'password':os.environ.get('INVENTORY_TEST_PASSWORD','123456')}).encode()).read().decode()
enter=re.search(r'href="([^"]+/outlet/[^"/]+/enter)"',signed_in)
if enter: op.open(enter[1]).read()
s=get('/module-usage');assert 'Gestion des modules' in s
csrf=re.search('name="_token" value="([^"]+)',s)[1]
inputs=re.findall(r'<input\b[^>]+>',s,re.S);original={}
for tag in inputs:
 if 'type="checkbox"' in tag:
  name=re.search('name="([^"]+)"',tag)[1];original[name]='1' if re.search(r'\bchecked\b',tag) else '0'
assert original['menu_stock']=='1'
def save(values):
 payload={'_method':'PUT','_token':csrf,**values}
 op.open(base+'/module-usage',urllib.parse.urlencode(payload).encode()).read()
try:
 save({**original,'menu_stock':'0'})
 try:get('/stock');raise AssertionError('Disabled route still accessible')
 except urllib.error.HTTPError as e:assert e.code==403
 html=get('/module-usage');assert not re.search(r'<div>\s*Stock\s*</div>',html)
 assert 'Gestion des modules' in html
 search=json.loads(get('/search-menu-data'))
 for group in ['navigation','suggestions']:
  for items in search[group].values():
   assert all(urllib.parse.urlparse(item.get('url','')).path.split('/')[1:2] != ['stock'] for item in items)
 print('Stock disabled: menu hidden, direct route blocked, search entries removed')
finally:save(original)
assert 'Stock' in get('/stock');print('Stock re-enabled: accessible')
d=json.loads(get('/resources/lang/fr.json'));assert d['Save']=='Enregistrer';print('French UI dictionary endpoint: OK')
get('/dashboard');get('/item/create');print('French dashboard and item form: OK')
