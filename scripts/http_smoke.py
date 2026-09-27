"""Real HTTP checks with CSRF enabled; the server must run with APP_ENV=local."""
import http.cookiejar
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = "http://127.0.0.1:8765"
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, data=None):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        with client.open(BASE + path, data=payload, timeout=10) as response:
            return response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.headers

for attempt in range(50):
    try:
        if request('/up')[0] == 200:
            break
    except urllib.error.URLError:
        pass
    time.sleep(0.2)
else:
    raise AssertionError('Server did not become healthy')

status, page, headers = request('/register')
assert status == 200, (status, page[:300])
assert headers['X-Content-Type-Options'] == 'nosniff'
assert 'no-store' in headers['Cache-Control']
token = re.search(r'name="_token" value="([^"]+)"', page).group(1)
form = {'name': 'Smoke reader', 'email': f'{uuid.uuid4()}@example.test', 'password': 'SmokePassword1234', 'password_confirmation': 'SmokePassword1234', 'is_admin': '1'}
assert request('/register', form)[0] == 419, 'Missing CSRF token was accepted'
form['_token'] = token
status, page, headers = request('/register', form)
assert status == 200 and 'Smoke reader' in page, (status, page[:300])
assert request('/studio')[0] == 403, 'Public signup elevated the member role'
token = re.search(r'name="_token" value="([^"]+)"', page).group(1)
assert request('/checkout', {'_token': token})[0] == 503
status, page, _ = request('/logout', {'_token': token})
assert status == 200 and 'Sign in' in page
start = time.perf_counter()
for _ in range(25):
    assert request('/')[0] == 200
elapsed = time.perf_counter() - start
assert elapsed < 15, f'25 sequential reads exceeded smoke budget: {elapsed:.3f}s'
print(f'PASS: HTTP signup, role boundary, CSRF, session logout, disabled checkout; 25 reads {elapsed:.3f}s')
