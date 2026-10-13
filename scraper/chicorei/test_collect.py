import json
import unittest
from collect import discover, extract, robots_policy


class CollectorTests(unittest.TestCase):
    def test_product_preserves_price_and_unknown_stock(self):
        data = {"@type": "Product", "name": "Exemplo", "sku": "123",
                "offers": {"@type": "Offer", "price": 39.9, "priceCurrency": "BRL",
                           "availability": "https://schema.org/InStock"}}
        document = '<script type="application/ld+json">' + json.dumps(data) + '</script>'
        product = extract(document, "https://chicorei.com/camiseta/exemplo-123.html")
        self.assertEqual(product["price"], "39.90")
        self.assertIsNone(product["stock"])
        self.assertEqual(product["variants"], [])
        self.assertEqual(product["source_id"], "123")

    def test_ambiguous_offers_and_missing_product(self):
        data = {"@graph": [{"@type": "Product", "name": "Exemplo",
                           "offers": [{"price": 10}, {"price": 20}]}]}
        product = extract('<script type="application/ld+json">' + json.dumps(data) + '</script>', "https://chicorei.com/x.html")
        self.assertIsNone(product["price"])
        self.assertIsNone(extract('<script type="application/ld+json">invalid</script>', "https://chicorei.com/"))

    def test_robots_wildcard_exception_and_domain(self):
        allowed, delay = robots_policy("User-agent: *\nCrawl-delay: 5\nDisallow: /*.php\nAllow: /sitemap-xml.php\nDisallow: /perfil/\n")
        self.assertEqual(delay, 5)
        self.assertFalse(allowed("https://chicorei.com/perfil/cliente"))
        self.assertFalse(allowed("https://chicorei.com/test.php?x=1"))
        self.assertFalse(allowed("https://example.test/test.html"))
        self.assertTrue(allowed("https://chicorei.com/sitemap-xml.php"))
        self.assertTrue(allowed("https://chicorei.com/camiseta/exemplo-123.html"))

    def test_discovery_deduplicates_only_product_paths(self):
        url = "https://chicorei.com/camiseta/exemplo-123.html"
        self.assertEqual(discover(url + " " + url.replace("/", r"\/") + " https://other.test/x-1.html"), [url])


if __name__ == "__main__":
    unittest.main()