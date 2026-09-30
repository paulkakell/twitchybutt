#!/usr/bin/env python3
"""Create a redacted, opt-in support snapshot from allowlisted environment metadata."""
from __future__ import annotations
import json, os, platform, sys
from pathlib import Path

ALLOW=("APP_ENV","APP_URL","DB_CONNECTION","CACHE_STORE","SESSION_DRIVER","QUEUE_CONNECTION",
       "CMS_PAYMENTS_ENABLED","CMS_RESTRICTED_PUBLISHING_ENABLED")
SECRET_MARKERS=("KEY","PASSWORD","TOKEN","SECRET","CREDENTIAL","PRIVATE")
def main() -> int:
    data={"schema":1,"version":Path("VERSION").read_text().strip(),"runtime":{"python":platform.python_version(),"platform":platform.system()},"configuration":{}}
    for name in ALLOW:
        value=os.getenv(name)
        if value is not None: data["configuration"][name]=value
    encoded=json.dumps(data,sort_keys=True,indent=2)
    upper=encoded.upper()
    if any(marker in upper for marker in SECRET_MARKERS):
        print("Refusing diagnostic export: secret-like field detected.",file=sys.stderr); return 2
    print(encoded); return 0
if __name__=="__main__": raise SystemExit(main())
