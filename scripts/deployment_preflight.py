#!/usr/bin/env python3
"""Fail-closed deployment preflight without reading or printing secret values."""
from __future__ import annotations
import os, re, sys
from urllib.parse import urlparse

def flag(name: str) -> bool:
    return os.getenv(name, "").strip().lower() in {"1","true","yes","on"}

def main() -> int:
    errors=[]
    env=os.getenv("APP_ENV","").strip()
    url=os.getenv("APP_URL","").strip()
    parsed=urlparse(url)
    if env not in {"production","staging"}: errors.append("APP_ENV must be production or staging.")
    if parsed.scheme != "https" or not parsed.hostname: errors.append("APP_URL must be an absolute HTTPS origin.")
    if parsed.username or parsed.password or parsed.query or parsed.fragment: errors.append("APP_URL must not contain credentials, query, or fragment.")
    if parsed.port not in (None,443): errors.append("APP_URL must use the default HTTPS port.")
    if flag("APP_DEBUG"): errors.append("APP_DEBUG must be false.")
    if flag("CMS_PAYMENTS_ENABLED"): errors.append("CMS_PAYMENTS_ENABLED must remain false for 00.09.00.")
    if flag("CMS_RESTRICTED_PUBLISHING_ENABLED"): errors.append("CMS_RESTRICTED_PUBLISHING_ENABLED must remain false for 00.09.00.")
    if not os.getenv("APP_KEY","").strip(): errors.append("APP_KEY must be configured.")
    db=os.getenv("DB_CONNECTION","").strip()
    if db not in {"sqlite","pgsql"}: errors.append("DB_CONNECTION must be sqlite or pgsql.")
    for line in errors: print(f"ERROR: {line}", file=sys.stderr)
    if errors: return 2
    print("Deployment preflight passed. Secret values were not printed.")
    return 0
if __name__ == "__main__": raise SystemExit(main())
