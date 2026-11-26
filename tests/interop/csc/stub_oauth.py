# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Opt-in external CSC service-stub proof. Not user authorization or production signing."""
import base64, os, secrets, sys, urllib.parse, requests
from pathlib import Path

BASE='https://trust-driver-stub-hash-signing.cleverbase.com'
CLIENT_ID=os.environ.get('LIBRESIGN_CSC_STUB_CLIENT_ID', '')
CLIENT_SECRET=os.environ.get('LIBRESIGN_CSC_STUB_CLIENT_SECRET', '')
REDIRECT='https://example.org/csc-local-qa-return'
OUT=Path(os.environ.get('LIBRESIGN_CSC_INTEROP_DIR',''))
if not CLIENT_ID or not CLIENT_SECRET or not os.environ.get('LIBRESIGN_CSC_INTEROP_DIR') or not OUT.is_dir():
    sys.exit('Set CSC stub client variables and LIBRESIGN_CSC_INTEROP_DIR to an existing private scratch directory')
os.umask(0o077)
if len(sys.argv) != 2 or sys.argv[1] not in {'certificate','sign'}:
    sys.exit('Usage: python3 stub_oauth.py certificate|sign')
s=requests.Session()
def authorize(scope, **extra):
    state=secrets.token_urlsafe(18)
    p={'response_type':'code','client_id':CLIENT_ID,'redirect_uri':REDIRECT,'scope':scope,'state':state, **extra}
    r=s.get(BASE+'/oauth2/authorize',params=p,allow_redirects=False,timeout=12)
    target=urllib.parse.urlparse(r.headers.get('Location',''))
    if target.scheme!='https' or target.netloc!='example.org' or target.path!='/csc-local-qa-return':
        raise RuntimeError('OAuth stub redirect target did not match configured callback')
    query=urllib.parse.parse_qs(target.query)
    print('authorize scope',scope,'http',r.status_code,'redirect params',sorted(query.keys()))
    if r.status_code not in (302,303) or query.get('state')!=[state] or not query.get('code'):
        raise RuntimeError('Expected CSC test-stub OAuth callback, no codes logged')
    token=s.post(BASE+'/oauth2/token',auth=(CLIENT_ID,CLIENT_SECRET),data={'grant_type':'authorization_code','code':query['code'][0],'client_id':CLIENT_ID,'redirect_uri':REDIRECT},allow_redirects=False,timeout=12)
    print('token exchange scope',scope,'http',token.status_code, 'error', token.json().get('error') if not token.ok else 'none')
    token.raise_for_status()
    j=token.json()
    if not j.get('access_token'): raise RuntimeError('missing access token')
    return j['access_token']

def post(endpoint,payload,service):
    resp=s.post(BASE+'/csc/v1/'+endpoint,json=payload,headers={'Authorization':'Bearer '+service},allow_redirects=False,timeout=12)
    print('CSC',endpoint,'http',resp.status_code)
    if not resp.ok: print('CSC error fields:', sorted(resp.json().keys()))
    resp.raise_for_status()
    return resp.json()

infoResponse=s.post(BASE+'/csc/v1/info',json={},allow_redirects=False,timeout=12)
infoResponse.raise_for_status()
info=infoResponse.json()
print('Provider name:',info.get('name'),'protocol:',info.get('specs'))
service=authorize('service')
items=post('credentials/list',{},service)
ids=items.get('credentialIDs') or []
if not isinstance(ids,list) or len(ids)>32:raise RuntimeError('invalid CSC test credential list')
print('Credentials available:',len(ids))
compatible=[]
for candidate in ids:
    if not isinstance(candidate,str) or not candidate:raise RuntimeError('invalid credential ID')
    info=post('credentials/info',{'credentialID':candidate,'certificates':'chain'},service)
    algos=(info.get('key') or {}).get('algo',[])
    if '1.2.840.113549.1.1.11' in algos:
        compatible.append((candidate,info))
if len(compatible)!=1:raise RuntimeError('test fixture has no unique RSA SHA-256 signing credential')
credential,certificate=compatible[0]
print('Selected RSA SHA-256 certificate from provider response')
first=certificate['cert']['certificates'][0]
der=base64.b64decode(first, validate=True)
pem='-----BEGIN CERTIFICATE-----\n'+'\n'.join(base64.b64encode(der).decode()[i:i+64] for i in range(0,len(base64.b64encode(der)),64))+'\n-----END CERTIFICATE-----\n'
(OUT/'provider-certificate.pem').write_text(pem)
if sys.argv[1]=='certificate':
    print('PASS: live CSC v1 RSA certificate fetched, no signature operation submitted')
    raise SystemExit(0)
digest=(OUT/'prepare-hash.bin').read_bytes()
if len(digest) != 32: raise RuntimeError('Expected SHA-256 PDF signed-attributes hash')
sad=authorize('credential',credentialID=credential,numSignatures=1,hash=base64.urlsafe_b64encode(digest).decode())
sigResponse=post('signatures/signHash',{'credentialID':credential,'SAD':sad,'hash':[base64.b64encode(digest).decode()],'hashAlgo':'2.16.840.1.101.3.4.2.1','signAlgo':'1.2.840.113549.1.1.1'},service)
signatures=sigResponse.get('signatures') or []
if len(signatures)!=1:raise RuntimeError('Expected exactly one signature')
signature=base64.b64decode(signatures[0])

(OUT/'provider-signature.bin').write_bytes(signature)
print('PASS: CSC provider returned one RSA signature for prepared CMS digest (PDF proof verifies it separately)')
