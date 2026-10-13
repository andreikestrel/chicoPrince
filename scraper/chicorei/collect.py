"""Coleta pequena de produtos públicos em JSON-LD, sem importar no banco."""
import argparse
import datetime as dt
import html
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parent
ORIGIN = "https://chicorei.com"
AGENT = "ChicoPrinceSample/0.1"
MAX_BYTES = 5 * 1024 * 1024


class StopCollection(Exception):
    pass


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise StopCollection("Redirecionamento recebido; revisar destino antes de continuar.")


class StructuredData(HTMLParser):
    def __init__(self):
        super().__init__()
        self.active = False
        self.parts = []
        self.nodes = []

    def handle_starttag(self, tag, attrs):
        if tag == "script" and dict(attrs).get("type", "").lower() == "application/ld+json":
            self.active = True
            self.parts = []

    def handle_data(self, data):
        if self.active:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.active:
            self.active = False
            try:
                self.nodes.extend(flatten(json.loads("".join(self.parts))))
            except (ValueError, TypeError):
                pass


def flatten(value):
    if isinstance(value, list):
        return [node for item in value for node in flatten(item)]
    if isinstance(value, dict):
        return [value] + flatten(value.get("@graph", []))
    return []


def typed(node, kind):
    value = node.get("@type")
    return value == kind or isinstance(value, list) and kind in value


def extract(document, url):
    parser = StructuredData()
    parser.feed(document)
    product = next((n for n in parser.nodes if typed(n, "Product")), None)
    if not product or not product.get("name"):
        return None
    offer = product.get("offers", {})
    if isinstance(offer, list):
        offer = offer[0] if len(offer) == 1 else {}
    if not isinstance(offer, dict):
        offer = {}
    price = offer.get("price")
    if price is not None and not re.fullmatch(r"\d+(?:\.\d{1,2})?", str(price)):
        price = None
    if price is not None:
        whole, _, fraction = str(price).partition(".")
        price = whole + "." + fraction.ljust(2, "0")
    crumbs = next((n for n in parser.nodes if typed(n, "BreadcrumbList")), {})
    categories = [c.get("name") for c in crumbs.get("itemListElement", [])
                  if isinstance(c, dict) and c.get("name")]
    image = product.get("image")
    if isinstance(image, list):
        image = image[0] if image else None
    if isinstance(image, dict):
        image = image.get("url")
    if not isinstance(image, str) or not image.startswith("https://"):
        image = None
    warnings = ["Estoque quantitativo não confirmado.", "Variações não extraídas."]
    if price is None:
        warnings.append("Preço ausente ou ambíguo.")
    return {
        "source": "chicorei", "source_url": url,
        "source_id": str(product["sku"]) if product.get("sku") else None,
        "fetched_at": dt.datetime.now(dt.timezone.utc).isoformat(),
        "name": product["name"], "description": product.get("description"),
        "image_url": image, "price": price, "currency": offer.get("priceCurrency"),
        "category_path": categories[1:-1], "stock": None,
        "availability": offer.get("availability"), "variants": [], "warnings": warnings,
    }


def discover(document):
    decoded = html.unescape(document.replace(r"\/", "/"))
    return list(dict.fromkeys(re.findall(
        r'https://chicorei\.com/[a-zA-Z0-9/_-]+-\d+\.html', decoded)))


def robots_policy(text):
    rules = []
    delay = 5.0
    agents = []
    directives = False
    for line in text.splitlines():
        line = line.split("#", 1)[0].strip()
        if ":" not in line:
            continue
        key, value = [part.strip() for part in line.split(":", 1)]
        key = key.lower()
        if key == "user-agent":
            if directives:
                agents = []
                directives = False
            agents.append(value.lower())
        else:
            directives = True
            if "*" not in agents and not any(a in AGENT.lower() for a in agents):
                continue
            if key in ("allow", "disallow") and value:
                rules.append((key, value))
            if key == "crawl-delay":
                delay = max(delay, float(value))

    def allowed(url):
        parsed = urllib.parse.urlsplit(url)
        if parsed.scheme != "https" or parsed.netloc != "chicorei.com":
            return False
        path = parsed.path + ("?" + parsed.query if parsed.query else "")
        matches = []
        for key, pattern in rules:
            expression = re.escape(pattern).replace(r"\*", ".*")
            if pattern.endswith("$"):
                expression = expression[:-2] + "$"
            if re.match(expression, path):
                matches.append((len(pattern.replace("*", "").rstrip("$")), key == "allow"))
        return max(matches)[1] if matches else True
    return allowed, delay


def main():
    args = argparse.ArgumentParser()
    args.add_argument("--limit", type=int, default=20)
    args.add_argument("--resume", type=Path, help="products.json de uma coleta anterior")
    args.add_argument("--output", type=Path, help="Arquivo adicional para integração local")
    options = args.parse_args()
    if not 1 <= options.limit <= 45:
        args.error("--limit deve estar entre 1 e 45")
    output = ROOT / "data"
    output.mkdir(exist_ok=True)
    stamp = dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    run = output / stamp
    run.mkdir()
    opener = urllib.request.build_opener(NoRedirect())
    last_request = 0.0
    delay = 5.0

    def fetch(url):
        nonlocal last_request
        time.sleep(max(0, last_request + delay - time.monotonic()))
        last_request = time.monotonic()
        request = urllib.request.Request(url, headers={"User-Agent": AGENT})
        try:
            with opener.open(request, timeout=20) as response:
                payload = response.read(MAX_BYTES + 1)
                if len(payload) > MAX_BYTES:
                    raise ValueError("Resposta ultrapassou 5 MiB")
                return payload.decode("utf-8", errors="replace")
        except urllib.error.HTTPError as error:
            if error.code in (401, 403, 429):
                raise StopCollection("HTTP " + str(error.code)) from error
            raise

    products, failures, visited = [], [], set()
    if options.resume:
        previous = json.loads(options.resume.read_text(encoding="utf-8"))
        if not isinstance(previous, list) or len(previous) > options.limit:
            args.error("Amostra anterior incompatível com o limite")
        for item in previous:
            if not isinstance(item, dict) or item.get("source") != "chicorei" or not item.get("name"):
                args.error("Registro anterior inválido")
            parsed = urllib.parse.urlsplit(item.get("source_url", ""))
            if parsed.scheme != "https" or parsed.netloc != "chicorei.com":
                args.error("Origem anterior inválida")
            if item["source_url"] in visited:
                args.error("URL duplicada na amostra anterior")
            visited.add(item["source_url"])
        products.extend(previous)
    stop_reason = None
    try:
        robots = fetch(ORIGIN + "/robots.txt")
        (run / "robots.txt").write_text(robots, encoding="utf-8")
        allowed, delay = robots_policy(robots)
        plan = json.loads((ROOT / "collection-plan.json").read_text(encoding="utf-8"))
        queue = list(plan["candidateProductUrls"])
        identities = {p["source_id"] or p["source_url"] for p in products}
        while queue and len(products) < options.limit and len(visited) < options.limit * 2:
            url = queue.pop(0)
            if url in visited:
                continue
            visited.add(url)
            if not allowed(url):
                failures.append({"url": url, "error": "Não permitido por robots/origem."})
                continue
            try:
                document = fetch(url)
                queue.extend(u for u in discover(document) if u not in visited and u not in queue)
                product = extract(document, url)
                if not product:
                    failures.append({"url": url, "error": "Product JSON-LD ausente."})
                    continue
                identity = product["source_id"] or url
                if identity in identities:
                    continue
                identities.add(identity)
                products.append(product)
                (run / "products.json").write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8")
                print(str(len(products)) + "/" + str(options.limit) + ": " + product["name"], flush=True)
            except StopCollection:
                raise
            except (urllib.error.URLError, ValueError, TimeoutError) as error:
                failures.append({"url": url, "error": str(error)})
    except (StopCollection, urllib.error.URLError, ValueError, TimeoutError) as error:
        stop_reason = str(error)
    finally:
        (run / "products.json").write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8")
        report = {"requested": options.limit, "collected": len(products), "visited": len(visited),
                  "failures": failures, "stop_reason": stop_reason, "database_written": False}
        (run / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        if options.output:
            options.output.write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(report, ensure_ascii=False), flush=True)
        print("Saída: " + str(run), flush=True)


if __name__ == "__main__":
    main()