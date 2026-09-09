#!/usr/bin/env python3
"""Exercise the actual shell header assertions with LF/CRLF and wrong values."""
import pathlib
import re
import subprocess

root = pathlib.Path(__file__).resolve().parents[1]
checks = patterns = 0
for relative in ['scripts/dev-acceptance.sh', 'scripts/subscription-webhook-https-proof.sh']:
    for match in re.finditer(r"grep\s+-[A-Za-z]+\s+'([^']+)'", (root / relative).read_text()):
        pattern = match.group(1)
        if pattern.startswith('^Allow:'):
            valid, invalid = 'Allow: POST', ['Allow: GET', 'Allow: POST, GET']
        elif pattern.startswith('^Location:'):
            valid, invalid = 'Location: /', ['Location: /different', 'Location: https://example.test/']
        elif pattern.startswith('^X-RED-Archived-Articles:'):
            valid, invalid = 'X-RED-Archived-Articles: 2', ['X-RED-Archived-Articles: 12', 'X-RED-Archived-Articles: 0']
        else:
            continue
        patterns += 1
        for body, expected in [(valid + '\n', True), (valid + '\r\n', True)] + [(value + '\r\n', False) for value in invalid]:
            result = subprocess.run(['grep', '-Eqi', '--', pattern], input=body, text=True)
            assert result.returncode in (0, 1), 'Header assertion regex is invalid'
            assert (result.returncode == 0) == expected, f'Header assertion is not portable: {relative}: {pattern}'
            checks += 1
assert patterns == 9, 'Expected Allow, logout and archive assertions must all be exercised'
print(f'HTTP header assertions passed: {checks} LF/CRLF and wrong-value cases from {patterns} actual shell assertions.')
