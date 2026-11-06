#!/usr/bin/env python3
"""Regenerate docs/module-dependency-graph.html from the codegraph index.

The committed HTML page is the template: this script rebuilds the codegraph
index for the repo, aggregates module-level dependencies, and patches the
embedded graph data (var DATA = ...) and the generated date in place.
Hand-edit the page's chrome freely; never hand-edit its DATA line.

Requires the codegraph CLI: pnpm add -g @colbymchenry/codegraph
"""

import datetime
import json
import re
import shutil
import sqlite3
import subprocess
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
HTML_PATH = ROOT / "docs" / "module-dependency-graph.html"
DB_PATH = ROOT / ".codegraph" / "codegraph.db"

EDGE_KINDS = ("imports", "calls", "references", "extends", "instantiates", "implements")


def rebuild_index() -> None:
    if shutil.which("codegraph") is None:
        sys.exit(
            "codegraph CLI not found. Install it once with:\n"
            "  pnpm add -g @colbymchenry/codegraph\n"
            "then re-run this script."
        )
    if DB_PATH.exists():
        subprocess.run(["codegraph", "index", str(ROOT)], check=True)
    else:
        subprocess.run(["codegraph", "init", "-y", str(ROOT)], check=True)


def bucket(path: str) -> tuple[str, str]:
    """Map a file path to its (app, module) dependency-graph node."""
    parts = path.split("/")
    if len(parts) < 3 or parts[0] != "apps":
        return ("root", "root")
    app = parts[1]
    if app == "backend":
        if len(parts) > 3 and parts[2] == "Modules":
            return ("backend", parts[3])
        return ("backend", "backend-base")
    if app == "admin":
        if len(parts) > 4 and parts[2] == "src" and parts[3] == "features":
            return ("admin", parts[4])
        return ("admin", "admin-shell")
    if app == "storefront":
        sub = parts[2] if len(parts) > 3 else "root"
        return ("storefront", sub if sub in ("app", "components", "lib", "context") else "other")
    return ("root", app or "root")


def build_graph() -> dict:
    con = sqlite3.connect(f"file:{DB_PATH}?mode=ro", uri=True)
    cur = con.cursor()

    nodes = dict(cur.execute("SELECT id, file_path FROM nodes"))
    counts: defaultdict[tuple[str, str], int] = defaultdict(int)
    for path in nodes.values():
        counts[bucket(path)] += 1

    link_w: defaultdict[tuple[tuple[str, str], tuple[str, str]], int] = defaultdict(int)
    link_kinds: defaultdict[tuple[tuple[str, str], tuple[str, str]], set] = defaultdict(set)
    for kind, src, tgt in cur.execute("SELECT kind, source, target FROM edges"):
        if kind not in EDGE_KINDS or src not in nodes or tgt not in nodes:
            continue
        a, b = bucket(nodes[src]), bucket(nodes[tgt])
        if a == b or a == ("root", "root"):
            continue
        link_w[(a, b)] += 1
        link_kinds[(a, b)].add(kind)
    con.close()

    linked = {b for pair in link_w for b in pair}
    kept = sorted(linked | {b for b, c in counts.items() if c >= 50})
    graph_nodes = [
        {"id": f"{app}:{mod}", "app": app, "mod": mod, "symbols": counts[(app, mod)]}
        for app, mod in kept
        if counts[(app, mod)] > 0
    ]
    ids = {n["id"] for n in graph_nodes}
    graph_links = [
        {"source": f"{a[0]}:{a[1]}", "target": f"{b[0]}:{b[1]}", "value": w,
         "kinds": len(link_kinds[(a, b)])}
        for (a, b), w in link_w.items()
        if f"{a[0]}:{a[1]}" in ids and f"{b[0]}:{b[1]}" in ids
    ]
    return {"nodes": graph_nodes, "links": graph_links}


def patch_html(data: dict) -> None:
    html = HTML_PATH.read_text(encoding="utf-8")
    payload = json.dumps(data, separators=(",", ":"))

    html, n_data = re.subn(
        r"^(\s*var DATA = ).*(;)$",
        lambda m: m.group(1) + payload + m.group(2),
        html, count=1, flags=re.M,
    )
    today = datetime.date.today().isoformat()
    html, n_date = re.subn(
        r"generated \d{4}-\d{2}-\d{2}\.",
        f"generated {today}.",
        html, count=1,
    )
    if n_data != 1 or n_date != 1:
        sys.exit(
            f"Unexpected page layout (DATA patches: {n_data}, date patches: {n_date}). "
            "Was docs/module-dependency-graph.html restructured? Update this script."
        )
    HTML_PATH.write_text(html, encoding="utf-8")


def main() -> None:
    rebuild_index()
    data = build_graph()
    patch_html(data)
    top = max(data["links"], key=lambda l: l["value"], default=None)
    print(f"Updated {HTML_PATH.relative_to(ROOT)}")
    print(f"  {len(data['nodes'])} modules, {len(data['links'])} cross-module links")
    if top:
        print(f"  strongest: {top['source']} -> {top['target']} ({top['value']} refs)")
    print("Commit with: docs(monorepo): regenerate module dependency graph")


if __name__ == "__main__":
    main()
