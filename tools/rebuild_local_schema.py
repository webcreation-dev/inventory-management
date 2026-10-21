"""Generate a recovery schema from shipped models; not an upstream SQL replacement."""
import re,json
from pathlib import Path
schema={}
models={}
for p in list(Path('Modules').rglob('Models/*.php'))+list(Path('app/Models').rglob('*.php')):
 s=p.read_text(); cls=p.stem
 if 'extends Model' not in s: continue
 m=re.search(r'protected\s+\$table\s*=\s*[\'\"]([^\'\"]+)',s)
 table=m[1] if m else re.sub(r'(?<!^)(?=[A-Z])','_',cls).lower()+'s'
 if not m and table.endswith('ys') and not table.endswith(('days','keys')): table=table[:-2]+'ies'
 if 'Stub' in cls: continue
 fields=set()
 for related,explicit in re.findall(r'belongsTo\((\w+)::class(?:,\s*[\'\"]([^\'\"]+)[\'\"])?',s):
  fields.add(explicit or re.sub(r'(?<!^)(?=[A-Z])','_',related).lower()+'_id')
 m=re.search(r'\$fillable\s*=\s*\[(.*?)\];',s,re.S)
 if m: fields.update(re.findall(r"'([a-zA-Z_][a-zA-Z_0-9]*)'",m[1]))
 fields.update(re.findall(r"'([a-zA-Z_][a-zA-Z_0-9]*)'\s*=>",s[s.find('protected $casts'):]) if 'protected $casts' in s else [])
 for root in [Path('Modules'),Path('app')]:
  for controller in [p for suffix in ['Controller','Service','Request'] for p in root.rglob(cls+suffix+'.php')]:
   body=controller.read_text()
   fields.update(re.findall(r"'([a-z_][a-z_0-9]*)'\s*=>",body))
   fields.update(re.findall(r'\$'+cls[0].lower()+cls[1:]+r'->([a-z_][a-z_0-9]*)',body))
   fields.update(re.findall(r"->(?:where|whereDate|whereIn|sum|orderBy|pluck)\(\s*'([a-z_][a-z_0-9]*)'",body))
 fields.update(['del_status','company_id','user_id'])
 schema.setdefault(table,set()).update(fields); models[cls]=table
sources=[p.read_text() for root in ['app','Modules','resources','database/seeders'] for p in Path(root).rglob('*.php')]
for s in sources:
 for table,field in re.findall(r'\b([a-z_]+)\.([a-z_][a-z_0-9]*)\b',s):
  if table in schema: schema[table].add(field)
 for cls,body in re.findall(r'(\w+)::(?:create|insert|updateOrCreate)\(\[(.*?)\]\s*\)',s,re.S):
  if cls in models: schema[models[cls]].update(re.findall(r"'([a-z_][a-z_0-9]*)'\s*=>",body))
 # Company is guarded rather than fillable. Recover fields read and written by controllers.
 schema['companies'].update(re.findall(r'\$(?:company|companyInfo|company_info)->([a-z_][a-z_0-9]*)\b(?!\s*\()',s))
schema.setdefault('time_zones',set()).update(['country_code','zone_name','del_status'])
for fields in schema.values(): fields.difference_update(['id','created_at','updated_at','encrypted_id'])
Path('database/local-recovery-schema.json').write_text(json.dumps({t:sorted(f) for t,f in sorted(schema.items())},indent=2)+'\n')
print('Recovered',len(schema),'table definitions')
