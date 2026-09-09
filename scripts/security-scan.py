#!/usr/bin/env python3
"""Scan tracked source only; never traverse local workbooks or operator state."""
import json
import pathlib
import shutil
import subprocess
import tempfile
import sys

root = pathlib.Path(__file__).resolve().parents[1]
files = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root).decode().split('\0')
blocked_suffixes = {'.xlsx', '.xls', '.ods', '.pem', '.key', '.p12', '.pfx', '.bak', '.log'}
with tempfile.TemporaryDirectory(prefix='redcms-source-scan-') as temp:
    stage = pathlib.Path(temp) / "source"
    stage.mkdir()
    for name in filter(None, files):
        path = pathlib.Path(name)
        if path.parts[0] in {'deployments', '.codex'}:
            continue  # export-ignored operator material; do not read it
        if path.suffix.lower() in blocked_suffixes or path.name.startswith('.env') or (path.name.startswith('config.local.') and path.name != 'config.local.example.php'):
            sys.exit('Refused: private file is tracked; inspect filename inventory locally.')
        source = root / path
        if source.is_symlink():
            sys.exit('Refused: symlink in tracked source.')
        dest = stage / path
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, dest)
    report = pathlib.Path(temp) / 'redacted.json'
    result = subprocess.run(['gitleaks', 'dir', '--redact=100', '--no-banner', '--exit-code=1', '--report-format=json', '--report-path=' + str(report), '.'], cwd=stage, capture_output=True, text=True)
    if report.exists():
        findings = json.loads(report.read_text())
        for finding in findings:
            print(finding['RuleID'], finding['File'], finding['StartLine'])
        print('Current tracked-source findings:', len(findings))
    elif result.returncode:
        print('Scanner failed before producing a report; no clean result.')
    sys.exit(result.returncode)
