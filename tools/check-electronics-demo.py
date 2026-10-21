# Local authenticated integration checks; no data mutations.
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



manifest=json.load(open('storage/app/demo-electronics-report.json'))
assert 'Données de démonstration' in get('/dashboard')
for path in ['/item','/customer','/supplier','/expense','/purchase','/sale','/sale-return','/user']:
 request=urllib.request.Request(base+path+'?draw=1&start=0&length=10',headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest'})
 response=json.loads(op.open(request).read())
 assert response.get('data'),path
 print('Liste alimentée :',path)
stock_html=get('/stock')
csrf=re.search(r'<meta[^>]+name="csrf-token"[^>]+content="([^"]+)',stock_html)[1]
request=urllib.request.Request(base+'/stock/stock',data=urllib.parse.urlencode({'_token':csrf,'draw':1,'start':0,'length':10}).encode(),headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest'})
assert json.loads(op.open(request).read()).get('data')
params={'date_from':manifest['from'],'date_to':manifest['to'],'date':list(manifest['days'])[-2],'outlet_id':1,'draw':1,'start':0,'length':10,'item_id':manifest['ids']['items'][0],'customer_id':manifest['ids']['customers'][0],'supplier_id':manifest['ids']['suppliers'][0],'employee_id':manifest['ids']['users'][0]}
for report in ['register','z','daily-summary','sale','due-sale','stock','low-stock','employee-sale','customer-receive','product-profit','supplier-ledger','supplier-balance','customer-ledger','customer-balance','product-sale','detailed-sale','profit-loss','purchase','expense','sale-return','item-tracking','price-history','cash-flow']:
 request=urllib.request.Request(base+'/reports/'+report+'-report?'+urllib.parse.urlencode(params),headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest'})
 response=json.loads(op.open(request).read())
 assert response.get('success') is not False,(report,response)
 assert response.get('data') is not None,(report,response)
 print('Rapport disponible :',report)
