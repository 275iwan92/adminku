"""Tes pembantu AdminKU Pemeriksa (resources/agent/pemeriksa.py): jalankan dengan `python -m unittest discover -s tests/agent`.
Menjaga: situs yang belum diizinkan ditolak, alamat laporan harus di situs yang sama, izin disimpan, pemeriksaan jalan di latar belakang."""
import http.client
import json
import os
import sys
import tempfile
import threading
import time
import unittest
from unittest import mock

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", "..", "resources", "agent"))
import pemeriksa as p  # noqa: E402

SITE = "http://adminku.test"
URL = SITE + "/agent/report/abc123"


class UrlRules(unittest.TestCase):
    def test_report_url_must_be_same_site_and_report_path(self):
        self.assertTrue(p.report_url_ok(SITE, URL))
        self.assertFalse(p.report_url_ok(SITE, "http://jahat.example/agent/report/abc"))   # situs lain
        self.assertFalse(p.report_url_ok(SITE, SITE + "/login"))                          # jalur lain
        self.assertFalse(p.report_url_ok("", URL))                                         # tanpa Origin
        self.assertFalse(p.report_url_ok(SITE, "http://adminku.test.jahat.example/agent/report/x"))

    def test_origin_of(self):
        self.assertEqual(p.origin_of("https://a.b:8080/x?y=1"), "https://a.b:8080")
        self.assertEqual(p.origin_of("file:///c:/x"), "")


class Server(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        patcher = mock.patch.object(p, "CONFIG", os.path.join(self.tmp, "izin.json"))
        patcher.start()
        self.addCleanup(patcher.stop)
        self.server = p.make_server(0)
        threading.Thread(target=self.server.serve_forever, daemon=True).start()
        self.addCleanup(self.server.shutdown)
        self.port = self.server.server_address[1]

    def call(self, method, path, body=None, origin=SITE):
        c = http.client.HTTPConnection("127.0.0.1", self.port)
        headers = {"Origin": origin} if origin else {}
        c.request(method, path, json.dumps(body) if body is not None else None, headers)
        r = c.getresponse()
        return r.status, json.loads(r.read() or b"{}"), r.getheader("Access-Control-Allow-Origin")

    def test_ping_is_open_for_detection(self):
        status, body, _ = self.call("GET", "/ping")
        self.assertEqual((status, body["app"]), (200, "adminku-pemeriksa"))

    def test_unknown_site_is_refused_when_user_says_no_and_nothing_runs(self):
        with mock.patch.object(p, "ask", return_value=False) as ask, mock.patch.object(p, "run_scan") as scan:
            status, _, _ = self.call("POST", "/scan", {"url": URL})
        self.assertEqual(status, 403)
        ask.assert_called_once()
        scan.assert_not_called()

    def test_site_approved_once_is_remembered_and_scan_runs_in_background(self):
        done = threading.Event()
        with mock.patch.object(p, "ask", return_value=True) as ask, mock.patch.object(p, "run_scan", side_effect=lambda u: done.set()):
            self.assertEqual(self.call("POST", "/scan", {"url": URL})[0], 202)
            self.assertEqual(self.call("POST", "/scan", {"url": URL})[0], 202)   # kedua kali tanpa dialog
        self.assertTrue(done.wait(2))
        self.assertEqual(ask.call_count, 1)
        self.assertEqual(p.load_allowed(), {SITE})

    def test_foreign_report_url_and_missing_origin_are_rejected_before_any_dialog(self):
        with mock.patch.object(p, "ask") as ask, mock.patch.object(p, "run_scan") as scan:
            self.assertEqual(self.call("POST", "/scan", {"url": "http://jahat.example/agent/report/x"})[0], 400)
            self.assertEqual(self.call("POST", "/scan", {"url": URL}, origin=None)[0], 404)
            self.assertEqual(self.call("POST", "/scan", {"bukan": "url"})[0], 400)
        ask.assert_not_called()
        scan.assert_not_called()


if __name__ == "__main__":
    unittest.main()
