"""Concurrent database budget regression. Disposable CI databases only; no login attempts."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = r'''
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv("CI") !== "true" || !app()->environment(["local", "testing"])) { exit(9); }
$data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($data["ready"], "ready");
$deadline = microtime(true) + 15;
while (!is_file($data["go"])) { if (microtime(true) > $deadline) { exit(8); } usleep(1000); }
try { echo app(App\Services\AttemptBudget::class)->consume($data["scopes"]) ? "allow" : "deny"; }
catch (Throwable) { echo "error"; exit(7); }
'''

def run_case(unique_accounts: bool) -> None:
    prefix = "ci-budget-" + str(uuid.uuid4())
    with tempfile.TemporaryDirectory(prefix="tb-budget-") as directory:
        root = Path(directory)
        processes = []
        try:
            for index in range(10):
                scopes = [[prefix + ":ip", 5, 60]]
                if unique_accounts:
                    scopes.append([prefix + ":account:" + str(index), 1, 60])
                process = subprocess.Popen(["php", "-r", PHP], cwd=ROOT, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                process.stdin.write(json.dumps({"scopes": scopes, "ready": str(root / str(index)), "go": str(root / "go")}))
                process.stdin.close()
                process.stdin = None
                processes.append(process)
            deadline = time.monotonic() + 12
            while len(list(root.iterdir())) < 10:
                if time.monotonic() > deadline or any(p.poll() is not None for p in processes):
                    raise RuntimeError("Budget workers did not reach the synchronization barrier")
                time.sleep(0.02)
            (root / "go").write_text("go")
            outputs = [p.communicate(timeout=20)[0].strip() for p in processes]
            if any(p.returncode != 0 for p in processes) or outputs.count("allow") != 5 or outputs.count("deny") != 5:
                raise RuntimeError("Concurrent budget did not allow exactly five of ten requests")
        finally:
            for process in processes:
                if process.poll() is None:
                    process.kill()
                process.wait()


def main() -> None:
    if os.environ.get("CI") != "true" or os.environ.get("APP_ENV") not in {"local", "testing"}:
        raise SystemExit("Use only an explicitly disposable CI database")
    started = time.monotonic()
    run_case(False)
    run_case(True)
    summary = {"status": "passed", "cases": 2, "workers_per_case": 10, "allowed_per_case": 5, "seconds": round(time.monotonic() - started, 3)}
    (ROOT / "build").mkdir(exist_ok=True)
    (ROOT / "build/attempt-budget.json").write_text(json.dumps(summary, indent=2) + "\n")
    print("PASS: synchronized database budgets permit exactly five of ten workers in both cases")


if __name__ == "__main__":
    main()
