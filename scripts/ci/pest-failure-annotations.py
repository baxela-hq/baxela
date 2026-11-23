#!/usr/bin/env python3
"""Turn a Pest/PHPUnit JUnit report into GitHub Actions error annotations.

The job log needs auth to read; annotations on a public repo don't. The
runner silently drops malformed or over-long workflow commands, so each
annotation is short, single-line, and carries a `file=`/`line=` property
only when both are well-formed.

Usage: pest-failure-annotations.py REPORT.xml RUN_LOG [REPO_PREFIX]

REPO_PREFIX is the job's working directory relative to the repository
root (e.g. `apps/backend`): Pest writes the `file` attribute relative to
it, while annotations resolve from the repository root.

Prints nothing when the report holds no failures; when the report is
missing or unparsable (pest died before writing it), falls back to the
last bytes of the run log.
"""
from __future__ import annotations

import html
import os
import re
import sys
import xml.etree.ElementTree as ET

MAX_ANNOTATIONS = 10
TAIL_BYTES = 300


def escape_data(text: str) -> str:
    return text.replace("%", "%25").replace("\r", "%0D").replace("\n", "%0A")


def escape_property(text: str) -> str:
    return (
        escape_data(text)
        .replace(":", "%3A")
        .replace(",", "%2C")
        .replace("[", "%5B")
        .replace("]", "%5D")
    )


def emit(message: str, path: str = "", line: str = "") -> None:
    properties = ""
    if re.fullmatch(r"[\w./-]+\.php", path):
        properties = f" file={escape_property(path)}"
        if line:
            properties += f",line={line}"
    print(f"::error{properties}::{escape_data(message[:220])}")


def test_path(file_attribute: str, repo_prefix: str) -> str:
    """`Modules/X/tests/YTest.php::it …` (working-dir relative) → repo relative."""
    path = file_attribute.split("::", 1)[0]
    workspace = os.environ.get("GITHUB_WORKSPACE", "")
    if workspace and path.startswith(workspace + "/"):
        return path[len(workspace) + 1 :]
    return f"{repo_prefix}/{path}" if repo_prefix and not path.startswith("/") else path


def failure_line(body: str, path: str) -> str:
    """Line of the deepest frame inside the test file itself, if any."""
    located = None
    for located_frame in re.finditer(r"(?m)^at .*" + re.escape(os.path.basename(path)) + r":(\d+)", body):
        located = located_frame
    return located.group(1) if located else ""


def summary(body: str, test_name: str) -> str:
    body = body.replace("\r", "")
    if body.startswith(test_name):
        body = body[len(test_name) :]
    lines = []
    for line in body.splitlines():
        stripped = line.strip()
        if stripped.startswith("at ") or not stripped:
            break
        lines.append(stripped)
    return " | ".join(lines[:3])[:220] or "failed"


def main(report: str, run_log: str, repo_prefix: str = "") -> int:
    failures = []
    try:
        for case in ET.parse(report).getroot().iter("testcase"):
            problem = case.find("failure")
            if problem is None:
                problem = case.find("error")
            if problem is None:
                continue
            body = html.unescape(f"{problem.text or ''} {problem.get('message') or ''}")
            path = test_path(case.get("file") or "", repo_prefix)
            test_class = (case.get("class") or case.get("classname") or "").rsplit("\\", 1)[-1]
            test_name = case.get("name") or ""
            failures.append((path, failure_line(body, path), f"{test_class} > {test_name} :: {summary(body, test_name)}"))
    except (OSError, ET.ParseError, SyntaxError):
        failures = []

    if failures:
        for path, line, message in failures[:MAX_ANNOTATIONS]:
            emit(message, path, line)
        if len(failures) > MAX_ANNOTATIONS:
            emit(f"... and {len(failures) - MAX_ANNOTATIONS} more failing tests (see the job log)")
        return 0

    with open(run_log, "rb") as handle:
        handle.seek(0, os.SEEK_END)
        handle.seek(max(0, handle.tell() - TAIL_BYTES))
        tail = handle.read().decode("utf-8", "replace")
    emit("pest failed without a usable JUnit report — log tail: " + " ".join(tail.split()))
    return 0


if __name__ == "__main__":
    sys.exit(main(*sys.argv[1:4]))
