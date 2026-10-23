#!/usr/bin/env python3
"""
project_index.py - ساخت فایل راهنما/ایندکس برای یک پروژه PHP ماژولار (بدون فریم‌ورک)

خروجی: PROJECT_INDEX.md (و اختیاری JSON) شامل:
  - ساختار پوشه‌ها و تعداد خط هر ماژول
  - namespaceها، کلاس‌ها و وابستگی هر فایل (use / new / extends / X:: / include)
  - وابستگی بین ماژول‌ها (چه کسی به چه کسی وابسته است)
  - نگاشت مدل -> جدول دیتابیس + جدول‌هایی که در SQL خام/کوئری‌بیلدر استفاده شدن

استفاده:
  python project_index.py /path/to/project
  python project_index.py . --modules-dir app/Modules --schema database.sql --json
  python project_index.py . --ignore tests,docs --depth 4 --out INDEX.md

فقط به کتابخانه استاندارد پایتون (۳.۸+) نیاز داره.
"""
import argparse
import json
import os
import re
import sys
from collections import Counter, defaultdict

DEFAULT_IGNORE = {
    "vendor", "node_modules", ".git", ".idea", ".vscode", "storage", "cache",
    "logs", "log", "uploads", "tmp", "temp", "__pycache__", "dist", "build",
}
PHP_EXT = {".php", ".phtml", ".inc"}
COUNT_EXT = PHP_EXT | {".js", ".ts", ".jsx", ".tsx", ".css", ".scss", ".html",
                       ".twig", ".sql", ".json", ".sh", ".py"}
MODULE_DIR_CANDIDATES = [
    "app/Modules", "app/modules", "Modules", "modules",
    "src/Modules", "src/modules", "app", "src",
]
SQL_STOP = {"select", "set", "where", "values", "dual", "as", "on", "and", "or",
            "information_schema", "if", "not", "exists", "table", "the"}

# ---------------------------------------------------------------- cleaning
TOKEN = re.compile(
    r"""(?P<s>'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")"""
    r"""|(?P<c>/\*.*?\*/|//[^\n]*|\#(?!\[)[^\n]*)""",
    re.S,
)


def clean(src, keep_strings):
    def rep(m):
        if m.group("s") is not None:
            return m.group("s") if keep_strings else '""'
        return re.sub(r"[^\n]", "", m.group("c"))
    return TOKEN.sub(rep, src)


def read_text(path):
    for enc in ("utf-8", "utf-8-sig", "cp1256", "latin-1"):
        try:
            with open(path, encoding=enc) as f:
                return f.read()
        except (UnicodeDecodeError, OSError):
            continue
    return ""


# ---------------------------------------------------------------- regexes
RE_NS = re.compile(r"(?m)^\s*namespace\s+([\w\\]+)\s*[;{]")
RE_DECL = re.compile(
    r"(?m)^\s*(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)"
    r"(?:\s*:\s*\w+)?"
    r"(?:\s+extends\s+([\w\\]+(?:\s*,\s*[\w\\]+)*))?"
    r"(?:\s+implements\s+([\w\\\s,]+?))?\s*\{"
)
RE_USE_TOP = re.compile(r"(?m)^use\s+(?!function\b|const\b)([^;]+);")
RE_USE_TRAIT = re.compile(r"(?m)^[ \t]+use\s+([\w\\,\s]+?)\s*[;{]")
RE_REQ = re.compile(
    r"\b(?:require|include)(?:_once)?\b\s*\(?\s*([^;]+?)\)?\s*;")
RE_NEW = re.compile(r"\bnew\s+([\\\w]+)")
RE_STATIC = re.compile(r"(?<![\w$>])([\\]?[A-Z][\w\\]*)::")
RE_INSTANCEOF = re.compile(r"\binstanceof\s+([\\\w]+)")
RE_CATCH = re.compile(r"\bcatch\s*\(\s*([\\\w|\s]+?)\s*\$")

RE_TABLE_PROP = re.compile(
    r"\$(?:table|tableName|_table|table_name)\s*=\s*['\"`]([\w.]+)['\"`]")
RE_TABLE_CONST = re.compile(
    r"\bconst\s+(?:TABLE|TABLE_NAME|TBL)\s*=\s*['\"`]([\w.]+)['\"`]")
RE_TABLE_FUNC = re.compile(
    r"function\s+(?:getTable|table|tableName|getTableName)\s*\([^)]*\)\s*"
    r"(?::\s*\??\w+)?\s*\{\s*return\s*['\"`]([\w.]+)['\"`]")

RE_SQL = [
    re.compile(r"\bINSERT\s+(?:IGNORE\s+)?INTO\s+[`\"]?(\w+)", re.I),
    re.compile(r"\bUPDATE\s+[`\"]?(\w+)[`\"]?\s+SET\b", re.I),
    re.compile(r"\bDELETE\s+FROM\s+[`\"]?(\w+)", re.I),
    re.compile(r"\bREPLACE\s+INTO\s+[`\"]?(\w+)", re.I),
]
RE_JOIN = re.compile(r"\bJOIN\s+[`\"]?(\w+)", re.I)
NON_MODEL_SUFFIX = re.compile(
    r"(Controller|Service|Middleware|Exception|Helper|Verifier|Test|TestCase|"
    r"Handler|Normalizer|Validator|Migrator|Limiter|Scope|Gateway)$")
RE_SQL_FROM = re.compile(r"\bFROM\s+[`\"]?(\w+)", re.I)
RE_BUILDER = re.compile(
    r"(?:->|::)(?:table|from|into|join|leftJoin|rightJoin|innerJoin|insertInto)"
    r"\(\s*['\"`](\w+)['\"`]")


def snake(name):
    s = re.sub(r"(.)([A-Z][a-z]+)", r"\1_\2", name)
    return re.sub(r"([a-z0-9])([A-Z])", r"\1_\2", s).lower()


def guess_table(cls):
    base = re.sub(r"Model$", "", cls) or cls
    s = snake(base)
    if s.endswith("y") and len(s) > 1 and s[-2] not in "aeiou":
        return s[:-1] + "ies"
    if s.endswith("s"):
        return s
    return s + "s"


# ---------------------------------------------------------------- scanning
def find_modules_dir(root, forced):
    if forced:
        p = os.path.join(root, forced)
        return forced.strip("/\\") if os.path.isdir(p) else None
    for c in MODULE_DIR_CANDIDATES:
        p = os.path.join(root, c)
        if os.path.isdir(p) and any(
                os.path.isdir(os.path.join(p, d)) for d in os.listdir(p)):
            return c
    return None


def module_of(rel, modules_dir):
    parts = rel.replace("\\", "/").split("/")
    if modules_dir:
        md = modules_dir.replace("\\", "/").split("/")
        if parts[:len(md)] == md and len(parts) > len(md) + 1:
            return parts[len(md)], True
    if len(parts) == 1:
        return "[root]", False
    if parts[0] == "app" and len(parts) > 2:
        return "[app/" + parts[1] + "]", False
    return "[" + parts[0] + "]", False


def collect_files(root, ignore, skip=()):
    out = []
    for dp, dns, fns in os.walk(root):
        dns[:] = sorted(d for d in dns if d not in ignore and not d.startswith(".git"))
        for fn in sorted(fns):
            if os.path.splitext(fn)[1].lower() in COUNT_EXT:
                full = os.path.join(dp, fn)
                if os.path.abspath(full) in skip:
                    continue
                out.append((full, os.path.relpath(full, root).replace("\\", "/")))
    return out


def parse_imports(text):
    imports = {}
    for m in RE_USE_TOP.finditer(text):
        stmt = m.group(1).strip()
        grp = re.match(r"([\w\\]+)\\\{(.+)\}$", stmt, re.S)
        items = []
        if grp:
            prefix = grp.group(1)
            for part in grp.group(2).split(","):
                part = part.strip()
                if part:
                    items.append(prefix + "\\" + part)
        else:
            items = [p.strip() for p in stmt.split(",") if p.strip()]
        for it in items:
            mm = re.match(r"\\?([\w\\]+)(?:\s+as\s+(\w+))?$", it, re.I)
            if mm:
                full = mm.group(1)
                alias = mm.group(2) or full.split("\\")[-1]
                imports[alias] = full
    return imports


def resolve(name, ns, imports):
    name = name.strip()
    if name.startswith("\\"):
        return name.lstrip("\\")
    first, _, rest = name.partition("\\")
    if first in imports:
        return imports[first] + ("\\" + rest if rest else "")
    return (ns + "\\" if ns else "") + name


def analyze_php(path, rel):
    raw = read_text(path)
    with_str = clean(raw, True)
    no_str = clean(raw, False)
    ns_m = RE_NS.search(no_str)
    ns = ns_m.group(1) if ns_m else ""
    imports = parse_imports(no_str)

    decls = []
    for m in RE_DECL.finditer(no_str):
        ext = [e.strip() for e in (m.group(3) or "").split(",") if e.strip()]
        imp = [e.strip() for e in (m.group(4) or "").split(",") if e.strip()]
        decls.append({"kind": m.group(1), "name": m.group(2),
                      "extends": ext, "implements": imp})

    refs = set()
    for d in decls:
        for n in d["extends"] + d["implements"]:
            refs.add(resolve(n, ns, imports))
    for m in RE_USE_TRAIT.finditer(no_str):
        for n in re.split(r"[,\s]+", m.group(1).strip()):
            if n:
                refs.add(resolve(n, ns, imports))
    for rx in (RE_NEW, RE_STATIC, RE_INSTANCEOF):
        for m in rx.finditer(no_str):
            n = m.group(1)
            if n.lower() in ("self", "static", "parent"):
                continue
            refs.add(resolve(n, ns, imports))
    for m in RE_CATCH.finditer(no_str):
        for n in m.group(1).split("|"):
            refs.add(resolve(n.strip(), ns, imports))
    imported = set(imports.values())

    includes = []
    for m in RE_REQ.finditer(with_str):
        s = re.sub(r"\s+", " ", m.group(1)).strip()
        if len(s) < 120:
            includes.append(s)

    # tables
    declared = (RE_TABLE_PROP.findall(with_str) + RE_TABLE_CONST.findall(with_str)
                + RE_TABLE_FUNC.findall(with_str))
    cnt = Counter(RE_BUILDER.findall(with_str))
    for rx in RE_SQL:
        cnt.update(rx.findall(with_str))
    if re.search(r"\bSELECT\b", with_str, re.I):
        cnt.update(RE_SQL_FROM.findall(with_str))
    cnt = Counter({t: c for t, c in cnt.items() if t.lower() not in SQL_STOP})
    joins = {t for t in RE_JOIN.findall(with_str) if t.lower() not in SQL_STOP}
    used = set(cnt) | joins
    declared_set = list(dict.fromkeys(declared))

    # lines
    lines = raw.count("\n") + (1 if raw and not raw.endswith("\n") else 0)
    nonblank_raw = sum(1 for l in raw.splitlines() if l.strip())
    code = sum(1 for l in no_str.splitlines() if l.strip())

    is_model = bool(
        re.search(r"(^|/)models?(/|$)", rel, re.I)
        or re.search(r"Model\.php$", rel)
        or any(re.search(r"Model$", e.split("\\")[-1]) for d in decls for e in d["extends"])
    )
    return {
        "ns": ns, "decls": decls, "imports": imports, "imported": imported,
        "refs": refs, "includes": includes, "declared_tables": declared_set,
        "used_tables": used, "table_counts": cnt, "lines": lines, "code": code,
        "comments": max(nonblank_raw - code, 0),
        "blank": max(lines - nonblank_raw, 0), "is_model": is_model,
    }


# ---------------------------------------------------------------- main
def main():
    ap = argparse.ArgumentParser(description="PHP modular project indexer")
    ap.add_argument("root", nargs="?", default=".")
    ap.add_argument("--modules-dir", help="مسیر پوشه ماژول‌ها نسبت به root (مثلاً app/Modules). پیش‌فرض: تشخیص خودکار")
    ap.add_argument("--out", default="PROJECT_INDEX.md")
    ap.add_argument("--json", action="store_true", help="خروجی JSON هم کنار فایل md ذخیره شود")
    ap.add_argument("--ignore", default="", help="پوشه‌های اضافی برای نادیده‌گرفتن، با کاما")
    ap.add_argument("--depth", type=int, default=3, help="عمق درخت پوشه‌ها در هر ماژول")
    ap.add_argument("--schema", help="فایل .sql اسکیمای دیتابیس برای تطبیق نام جدول‌ها")
    a = ap.parse_args()

    root = os.path.abspath(a.root)
    ignore = DEFAULT_IGNORE | {x.strip() for x in a.ignore.split(",") if x.strip()}
    modules_dir = find_modules_dir(root, a.modules_dir)
    skip = {os.path.abspath(__file__), os.path.abspath(a.out),
            os.path.abspath(os.path.splitext(a.out)[0] + ".json")}
    files = collect_files(root, ignore, skip)

    schema_tables = None
    if a.schema:
        sql = read_text(a.schema)
        schema_tables = {m.lower() for m in re.findall(
            r"CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`\"]?(\w+)", sql, re.I)}

    mods = defaultdict(lambda: {"is_module": False, "files": [], "lines": 0,
                                "code": 0, "comments": 0, "blank": 0,
                                "by_ext": Counter(), "namespaces": set()})
    fdata = {}
    class_index = {}  # fqcn -> (module, rel)

    for full, rel in files:
        mod, is_mod = module_of(rel, modules_dir)
        m = mods[mod]
        m["is_module"] = m["is_module"] or is_mod
        ext = os.path.splitext(rel)[1].lower()
        if ext in PHP_EXT:
            info = analyze_php(full, rel)
            for d in info["decls"]:
                class_index[(info["ns"] + "\\" if info["ns"] else "") + d["name"]] = (mod, rel)
            if info["ns"]:
                m["namespaces"].add(info["ns"])
        else:
            text = read_text(full)
            n = text.count("\n") + (1 if text and not text.endswith("\n") else 0)
            info = {"lines": n, "code": n, "comments": 0, "blank": 0, "decls": [],
                    "ns": "", "refs": set(), "imported": set(), "includes": [],
                    "declared_tables": [], "used_tables": set(),
                    "table_counts": Counter(), "is_model": False}
        info["module"] = mod
        info["rel"] = rel
        fdata[rel] = info
        m["files"].append(rel)
        for k in ("lines", "code", "comments", "blank"):
            m[k] += info[k]
        m["by_ext"][ext] += info["lines"]

    # resolve deps
    mod_edges = defaultdict(Counter)
    external = defaultdict(Counter)
    for rel, info in fdata.items():
        own = {(info["ns"] + "\\" if info["ns"] else "") + d["name"] for d in info["decls"]}
        internal, ext_ns = set(), set()
        for fq in info["refs"] | info["imported"]:
            if fq in own:
                continue
            if fq in class_index:
                internal.add(fq)
            elif fq in info["imported"]:
                ext_ns.add(fq)
        info["internal_deps"] = sorted(internal)
        info["external_deps"] = sorted(ext_ns)
        for fq in internal:
            tm = class_index[fq][0]
            if tm != info["module"]:
                mod_edges[info["module"]][tm] += 1
        for fq in ext_ns:
            external[info["module"]][".".join(fq.split("\\")[:2]).replace(".", "\\")] += 1

    # models & tables
    models = []
    table_files = defaultdict(set)
    for rel, info in fdata.items():
        for t in info["used_tables"]:
            table_files[t].add(rel)
        if info["decls"]:
            for d in info["decls"]:
                if d["kind"] != "class" or d["name"] in ("Model", "BaseModel", "AbstractModel"):
                    continue
                # مدل = ساختار کلاسیک، یا (اگه ساختار خاصی نداری) کلاسی داخل ماژول
                # که SQL/جدول داره، به Database وابسته‌ست و اسمش Service/Controller/... نیست
                inferred = (
                    mods[info["module"]]["is_module"]
                    and bool(info["used_tables"])
                    and not NON_MODEL_SUFFIX.search(d["name"])
                    and any(n.split("\\")[-1] in ("Database", "DB", "PDO")
                            for n in info["refs"] | info["imported"])
                )
                if not (info["is_model"] or inferred):
                    continue
                g = guess_table(d["name"])
                tc = info["table_counts"]
                if info["declared_tables"]:
                    tbl, src = info["declared_tables"][0], "declared"
                elif tc:
                    tbl, src = (g if g in tc else tc.most_common(1)[0][0]), "sql"
                else:
                    tbl, src = g, "guess"
                fq = (info["ns"] + "\\" if info["ns"] else "") + d["name"]
                models.append({"class": fq, "file": rel, "module": info["module"],
                               "table": tbl, "source": src,
                               "also": sorted(info["used_tables"] - {tbl}),
                               "in_schema": (tbl.lower() in schema_tables) if schema_tables is not None else None})
                table_files[tbl].add(rel)

    reverse_mod = defaultdict(Counter)
    for src, tg in mod_edges.items():
        for t, c in tg.items():
            reverse_mod[t][src] += c

    # ------------------------------------------------------------ markdown
    L = []
    w = L.append
    ordered = sorted(mods, key=lambda k: (not mods[k]["is_module"], k.lower()))
    tot = sum(mods[k]["lines"] for k in mods)
    w(f"# راهنمای پروژه (ایندکس خودکار)\n")
    w(f"- مسیر: `{root}`")
    w(f"- پوشه ماژول‌ها: `{modules_dir or 'تشخیص داده نشد (پوشه‌های سطح اول به‌عنوان بخش در نظر گرفته شدن)'}`")
    w(f"- تعداد فایل: **{len(files)}** | مجموع خط: **{tot:,}** | ماژول: **{sum(1 for k in mods if mods[k]['is_module'])}**")
    w("- بخش‌هایی که داخل `[ ]` هستن (مثل `[core]`) بیرون از پوشه ماژول‌ها هستن.\n")

    w("## ۱. خلاصه ماژول‌ها\n")
    w("| ماژول | فایل | کل خط | خط کد | کامنت | کلاس | مدل | جدول‌ها | وابسته به | مورد استفاده در |")
    w("|---|---:|---:|---:|---:|---:|---:|---:|---|---|")
    for k in ordered:
        m = mods[k]
        ncls = sum(len(fdata[r]["decls"]) for r in m["files"])
        mm = [x for x in models if x["module"] == k]
        tabs = {x["table"] for x in mm}
        for r in m["files"]:
            tabs |= fdata[r]["used_tables"]
        dep = ", ".join(f"{t}({c})" for t, c in mod_edges[k].most_common()) or "-"
        rev = ", ".join(f"{t}({c})" for t, c in reverse_mod[k].most_common()) or "-"
        w(f"| **{k}** | {len(m['files'])} | {m['lines']:,} | {m['code']:,} | {m['comments']:,} | {ncls} | {len(mm)} | {len(tabs)} | {dep} | {rev} |")
    w("\n> عدد داخل پرانتز = تعداد وابستگی‌های فایل‌به‌کلاس بین دو ماژول.\n")

    w("## ۲. نگاشت مدل ← جدول\n")
    if models:
        w("| ماژول | مدل (کلاس) | فایل | جدول اصلی | منبع | جدول‌های دیگر در همین فایل |" + (" در اسکیما |" if schema_tables is not None else ""))
        w("|---|---|---|---|---|---|" + ("---|" if schema_tables is not None else ""))
        labels = {"declared": "تعریف‌شده ($table)", "sql": "از کوئری‌ها", "guess": "حدس از اسم کلاس ⚠️"}
        for x in sorted(models, key=lambda z: (z["module"], z["class"])):
            extra = ""
            if schema_tables is not None:
                extra = " ✅ |" if x["in_schema"] else " ❌ |"
            also = ", ".join(f"`{t}`" for t in x["also"]) or "-"
            w(f"| {x['module']} | `{x['class']}` | `{x['file']}` | `{x['table']}` | {labels[x['source']]} | {also} |{extra}")
    else:
        w("هیچ مدلی پیدا نشد (مدل = فایل داخل پوشه‌ای با اسم Model(s)، یا فایل *Model.php، یا کلاسی که از *Model ارث می‌بره).")
    w("")

    w("## ۳. ایندکس معکوس جدول‌ها ← فایل‌ها\n")
    w("(شامل جدول‌هایی که در SQL خام یا query builder پیدا شدن)\n")
    for t in sorted(table_files):
        fl = sorted(table_files[t])
        tag = ""
        if schema_tables is not None and t.lower() not in schema_tables:
            tag = " ❌ در اسکیما نیست"
        w(f"- `{t}`{tag}: " + ", ".join(f"`{f}`" for f in fl[:12]) + (f" … (+{len(fl)-12})" if len(fl) > 12 else ""))
    w("")

    w("## ۴. جزئیات هر ماژول\n")
    for k in ordered:
        m = mods[k]
        w(f"### {k}\n")
        w(f"- فایل: {len(m['files'])} | کل خط: {m['lines']:,} | کد: {m['code']:,} | کامنت: {m['comments']:,} | خالی: {m['blank']:,}")
        w("- به تفکیک پسوند: " + ", ".join(f"`{e or 'بدون‌پسوند'}`={c:,}" for e, c in m["by_ext"].most_common()))
        if m["namespaces"]:
            w("- namespaceها: " + ", ".join(f"`{n}`" for n in sorted(m["namespaces"])))
        if mod_edges[k]:
            w("- وابسته به ماژول: " + ", ".join(f"**{t}**" for t in mod_edges[k]))
        if reverse_mod[k]:
            w("- مورد استفاده توسط: " + ", ".join(f"**{t}**" for t in reverse_mod[k]))
        if external[k]:
            w("- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): " + ", ".join(f"`{n}`" for n, _ in external[k].most_common(15)))

        # directory tree (by lines)
        base = (modules_dir + "/" + k) if m["is_module"] else (k.strip("[]") if k != "[root]" else "")
        dirs = defaultdict(lambda: [0, 0])
        for r in m["files"]:
            rr = r[len(base) + 1:] if base and r.startswith(base + "/") else r
            parts = rr.split("/")[:-1]
            for i in range(0, min(len(parts), a.depth) + 1):
                key = "/".join(parts[:i]) or "."
                dirs[key][0] += 1
                dirs[key][1] += fdata[r]["lines"]
        w("\n```")
        w(f"{k}/  ({dirs['.'][0]} files, {dirs['.'][1]:,} lines)" if "." in dirs else f"{k}/")
        for d in sorted(x for x in dirs if x != "."):
            depth = d.count("/") + 1
            w(f"{'  ' * depth}{d.split('/')[-1]}/  ({dirs[d][0]} files, {dirs[d][1]:,} lines)")
        w("```\n")

        w("| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |")
        w("|---|---:|---|---|---|")
        for r in sorted(m["files"]):
            info = fdata[r]
            short = r[len(base) + 1:] if base and r.startswith(base + "/") else r
            decl = "; ".join(
                f"{d['kind']} {d['name']}" + (f" ← {', '.join(e.split(chr(92))[-1] for e in d['extends'])}" if d["extends"] else "")
                for d in info["decls"]) or "-"
            deps = ", ".join(f"`{f.split(chr(92))[-1]}`" + (f"@{class_index[f][0]}" if class_index[f][0] != k else "")
                             for f in info["internal_deps"]) or "-"
            tabs = set(info["used_tables"]) | {x["table"] for x in models if x["file"] == r}
            tb = ", ".join(f"`{t}`" for t in sorted(tabs)) or "-"
            inc = ""
            if info["includes"]:
                inc = " ⟨include: " + " | ".join(info["includes"][:3]) + "⟩"
            w(f"| `{short}` | {info['lines']} | {decl}{inc} | {deps} | {tb} |".replace("\n", " "))
        w("")

    w("---\n")
    w("**محدودیت‌ها:** تحلیل با regex انجام شده (نه parser کامل PHP). جدول‌هایی که به‌صورت داینامیک ساخته میشن "
      "(مثل `{$this->table}`) یا داخل heredoc هستن شناسایی نمیشن. مواردی با ⚠️ حدسی‌ان و باید چک بشن.")

    with open(a.out, "w", encoding="utf-8") as f:
        f.write("\n".join(L) + "\n")

    if a.json:
        js = {
            "root": root, "modules_dir": modules_dir,
            "modules": {k: {"files": len(v["files"]), "lines": v["lines"], "code": v["code"],
                            "namespaces": sorted(v["namespaces"]),
                            "depends_on": dict(mod_edges[k]),
                            "used_by": dict(reverse_mod[k])} for k, v in mods.items()},
            "models": models,
            "tables": {t: sorted(fs) for t, fs in table_files.items()},
            "files": {r: {"module": i["module"], "lines": i["lines"], "namespace": i["ns"],
                          "classes": [d["name"] for d in i["decls"]],
                          "internal_deps": i["internal_deps"],
                          "external_deps": i["external_deps"],
                          "tables": sorted(i["used_tables"])} for r, i in fdata.items()},
        }
        with open(os.path.splitext(a.out)[0] + ".json", "w", encoding="utf-8") as f:
            json.dump(js, f, ensure_ascii=False, indent=2)

    print(f"✔ {a.out} ساخته شد — {len(files)} فایل، {tot:,} خط، {len(mods)} بخش، {len(models)} مدل")


if __name__ == "__main__":
    sys.exit(main())