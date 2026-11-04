#!/usr/bin/env python3
# AdminKU Pemeriksa: pembantu kecil, dipasang sekali di Windows. Berjalan di latar belakang dan HANYA mendengarkan di komputer ini (127.0.0.1).
# Saat tombol "Periksa Komputer Ini" di AdminKU diklik, halaman memanggilnya; ia membaca spesifikasi (adminku-perangkat.py: prosesor, memori, grafis, disk,
# mainboard, OS, software, patch) dan mengirimnya ke AdminKU yang memanggil. Tidak membaca isi berkas, password, atau riwayat browser.
# Keamanan: situs yang boleh memanggil harus disetujui pengguna lewat dialog (sekali per situs); alamat laporan harus di situs yang sama dan di jalur /agent/report/.
# Pasang: klik dua kali. Hapus: jalankan dengan --hapus.
import ctypes
import importlib.util
import platform  # noqa: F401  platform, shutil, urllib.request dipakai adminku-perangkat.py yang dimuat sebagai berkas data;
import shutil  # noqa: F401  PyInstaller hanya melihat impor di berkas ini, jadi harus disebut di sini agar ikut terbungkus
import urllib.request  # noqa: F401
import json
import os
import socket
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse

PORT = 47831
APP = "AdminKU Pemeriksa"
RUN_KEY = r"Software\Microsoft\Windows\CurrentVersion\Run"
CONFIG = os.path.join(os.environ.get("APPDATA", os.path.expanduser("~")), "AdminKU-Pemeriksa", "izin.json")
BASE = getattr(sys, "_MEIPASS", os.path.dirname(os.path.abspath(__file__)))


def ask(text, yes_no=True):
    """Dialog Windows (selalu di atas). True bila Ya/OK."""
    return ctypes.windll.user32.MessageBoxW(0, text, APP, (0x24 if yes_no else 0x40) | 0x40000) in (1, 6)


def load_allowed():
    try:
        with open(CONFIG, encoding="utf-8") as f:
            return set(json.load(f))
    except (OSError, ValueError):
        return set()


def save_allowed(origins):
    os.makedirs(os.path.dirname(CONFIG), exist_ok=True)
    with open(CONFIG, "w", encoding="utf-8") as f:
        json.dump(sorted(origins), f)


def origin_of(url):
    u = urlparse(url or "")
    return "%s://%s" % (u.scheme, u.netloc) if u.scheme in ("http", "https") and u.netloc else ""


def report_url_ok(origin, url):
    """Alamat laporan harus di situs yang memanggil dan di jalur /agent/report/ (mencegah situs lain menerima data komputer ini)."""
    return bool(origin) and origin_of(url) == origin and urlparse(url).path.startswith("/agent/report/")


def site_allowed(origin):
    allowed = load_allowed()
    if origin in allowed:
        return True
    if ask("Izinkan %s memeriksa spesifikasi komputer ini (prosesor, memori, grafis, disk, software)?" % origin):
        save_allowed(allowed | {origin})
        return True
    return False


def log(text):
    os.makedirs(os.path.dirname(CONFIG), exist_ok=True)
    with open(os.path.join(os.path.dirname(CONFIG), "log.txt"), "a", encoding="utf-8") as f:
        f.write(time.strftime("%Y-%m-%d %H:%M:%S ") + text + chr(10))


def run_scan(url):
    try:
        log("mulai " + url.split("/agent/")[0])
        spec = importlib.util.spec_from_file_location("perangkat", os.path.join(BASE, "adminku-perangkat.py"))
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        mod.send(url, mod.collect())
        log("selesai, laporan terkirim")
    except BaseException as e:  # pemeriksaan gagal: catat dan beri tahu pengguna, jangan diam
        log("GAGAL %r" % (e,))
        ask("Pemeriksaan gagal: %s" % e, yes_no=False)


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def reply(self, code, body, origin="*"):
        data = json.dumps(body).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Access-Control-Allow-Origin", origin)
        self.send_header("Access-Control-Allow-Private-Network", "true")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_OPTIONS(self):
        self.send_response(204)
        self.send_header("Access-Control-Allow-Origin", self.headers.get("Origin", "*"))
        self.send_header("Access-Control-Allow-Methods", "GET, POST")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.send_header("Access-Control-Allow-Private-Network", "true")
        self.end_headers()

    def do_GET(self):
        if self.path == "/ping":
            return self.reply(200, {"app": "adminku-pemeriksa", "v": 1})
        self.reply(404, {"error": "tidak ada"})

    def do_POST(self):
        origin = self.headers.get("Origin", "")
        if self.path != "/scan" or not origin:
            return self.reply(404, {"error": "tidak ada"})
        try:
            size = int(self.headers.get("Content-Length", 0))
            url = json.loads(self.rfile.read(size) if 0 < size <= 4096 else b"{}").get("url", "")
        except ValueError:
            return self.reply(400, {"error": "permintaan tidak dikenal"}, origin)
        if not report_url_ok(origin, url):
            return self.reply(400, {"error": "alamat laporan tidak sesuai"}, origin)
        if not site_allowed(origin):
            return self.reply(403, {"error": "situs belum diizinkan"}, origin)
        threading.Thread(target=run_scan, args=(url,), daemon=True).start()
        self.reply(202, {"started": True}, origin)


def make_server(port=PORT):
    return ThreadingHTTPServer(("127.0.0.1", port), Handler)


def me():
    return [sys.executable] if getattr(sys, "frozen", False) else [sys.executable, os.path.abspath(__file__)]


def port_busy():
    with socket.socket() as s:
        return s.connect_ex(("127.0.0.1", PORT)) == 0


def install():
    import winreg
    if port_busy():
        return ask("AdminKU Pemeriksa sudah terpasang dan berjalan.", yes_no=False)
    if not ask("Pasang AdminKU Pemeriksa?\n\nIa berjalan di latar belakang dan hanya memeriksa komputer ini ketika Anda menekan tombol Periksa di AdminKU, dan hanya untuk situs yang Anda izinkan. Hapus kapan saja dengan menjalankan berkasnya dengan --hapus."):
        return
    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE) as k:
        winreg.SetValueEx(k, APP, 0, winreg.REG_SZ, subprocess.list2cmdline(me() + ["--serve"]))
    subprocess.Popen(me() + ["--serve"], creationflags=0x08000000 | 0x00000008, close_fds=True)   # tanpa jendela, lepas dari proses ini
    ask("Terpasang. Kembali ke AdminKU lalu klik Periksa Komputer Ini.", yes_no=False)


def uninstall():
    import winreg
    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE) as k:
            winreg.DeleteValue(k, APP)
    except OSError:
        pass
    try:
        os.remove(CONFIG)
    except OSError:
        pass
    ask("Dihapus dari pemulaian Windows dan izin situs dibersihkan. Proses yang sedang berjalan berhenti saat komputer dimatikan atau lewat Task Manager.", yes_no=False)


if __name__ == "__main__":
    if "--serve" in sys.argv:
        make_server().serve_forever()
    elif "--hapus" in sys.argv:
        uninstall()
    else:
        install()
