import json, os, subprocess, sys, unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
def run(script, env):
    merged={"PATH":os.environ.get("PATH",""), **env}
    return subprocess.run([sys.executable,str(ROOT/"scripts"/script)],cwd=ROOT,env=merged,text=True,capture_output=True)
class OperationsToolingTest(unittest.TestCase):
    def test_preflight_accepts_safe_preview_configuration(self):
        r=run("deployment_preflight.py",{"APP_ENV":"staging","APP_URL":"https://creator.example","APP_DEBUG":"false","APP_KEY":"fixture","DB_CONNECTION":"pgsql","CMS_PAYMENTS_ENABLED":"false","CMS_RESTRICTED_PUBLISHING_ENABLED":"false"})
        self.assertEqual(0,r.returncode,r.stderr); self.assertNotIn("fixture",r.stdout+r.stderr)
    def test_preflight_rejects_unsafe_and_unfinished_capabilities(self):
        r=run("deployment_preflight.py",{"APP_ENV":"production","APP_URL":"http://creator.example","APP_DEBUG":"true","APP_KEY":"fixture","DB_CONNECTION":"mysql","CMS_PAYMENTS_ENABLED":"true"})
        self.assertEqual(2,r.returncode); self.assertIn("HTTPS",r.stderr); self.assertNotIn("fixture",r.stdout+r.stderr)
    def test_diagnostics_are_allowlisted(self):
        r=run("support_diagnostics.py",{"APP_ENV":"staging","APP_URL":"https://creator.example","APP_KEY":"do-not-leak","DB_PASSWORD":"do-not-leak","DB_CONNECTION":"sqlite"})
        self.assertEqual(0,r.returncode,r.stderr); payload=json.loads(r.stdout)
        self.assertNotIn("APP_KEY",payload["configuration"]); self.assertNotIn("DB_PASSWORD",payload["configuration"]); self.assertNotIn("do-not-leak",r.stdout)
if __name__=="__main__": unittest.main()
