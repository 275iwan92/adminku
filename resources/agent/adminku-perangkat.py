#!/usr/bin/env python3
# AdminKU - Pemeriksaan Perangkat (sekali jalan, atas persetujuan pengguna).
# Membaca: prosesor, memori, kartu grafis, disk, mainboard/BIOS, sistem operasi, software terpasang, patch/hotfix.
# TIDAK membaca: isi berkas, password, riwayat browser, atau data pribadi lain. Berhenti sendiri setelah mengirim laporan.
# Hanya memakai pustaka standar Python 3.7+. Token di bawah berlaku sekali dan kedaluwarsa dalam 30 menit.
import json
import os
import platform
import shutil
import subprocess
import sys
import urllib.request

URL = "__URL__"
SYSTEM = platform.system()


def run(cmd, timeout=60):
    try:
        return subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, shell=isinstance(cmd, str)).stdout
    except Exception:
        return ""


def ps(script):
    """Jalankan PowerShell dan kembalikan hasil JSON sebagai list (Windows)."""
    out = run(["powershell", "-NoProfile", "-NonInteractive", "-Command", script + " | ConvertTo-Json -Compress -Depth 3"], 90).strip()
    try:
        data = json.loads(out) if out else []
    except ValueError:
        return []
    return data if isinstance(data, list) else [data]


def gb(n):
    return round(float(n) / 1024 ** 3, 1) if n else None


def read(path):
    try:
        with open(path, encoding="utf-8", errors="ignore") as f:
            return f.read().strip()
    except OSError:
        return ""


def collect_windows():
    cpu = (ps("Get-CimInstance Win32_Processor | Select Name,NumberOfCores,NumberOfLogicalProcessors,MaxClockSpeed,L2CacheSize,L3CacheSize,Manufacturer") or [{}])[0]
    cs = (ps("Get-CimInstance Win32_ComputerSystem | Select Manufacturer,Model,TotalPhysicalMemory,Name") or [{}])[0]
    os_ = (ps("Get-CimInstance Win32_OperatingSystem | Select Caption,Version,OSArchitecture") or [{}])[0]
    board = (ps("Get-CimInstance Win32_BaseBoard | Select Manufacturer,Product") or [{}])[0]
    bios = (ps("Get-CimInstance Win32_BIOS | Select SMBIOSBIOSVersion,Manufacturer") or [{}])[0]
    mods = ps("Get-CimInstance Win32_PhysicalMemory | Select BankLabel,Capacity,Speed,Manufacturer,PartNumber")
    gpus = ps("Get-CimInstance Win32_VideoController | Select Name,AdapterRAM,DriverVersion")
    disks = ps("Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' | Select DeviceID,Size,FreeSpace")
    sw = ps("Get-ItemProperty HKLM:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*,HKLM:\\Software\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\* | Where-Object DisplayName | Select DisplayName,DisplayVersion")
    fixes = ps("Get-HotFix | Select HotFixID,InstalledOn,Description")
    return {
        "cpu": {"name": (cpu.get("Name") or "").strip(), "maker": cpu.get("Manufacturer"), "cores": cpu.get("NumberOfCores"), "threads": cpu.get("NumberOfLogicalProcessors"),
                "mhz": cpu.get("MaxClockSpeed"), "l2_kb": cpu.get("L2CacheSize"), "l3_kb": cpu.get("L3CacheSize")},
        "memory": {"total_gb": gb(cs.get("TotalPhysicalMemory")), "modules": [{"slot": m.get("BankLabel"), "size_gb": gb(m.get("Capacity")), "speed": m.get("Speed"), "maker": (m.get("Manufacturer") or "").strip(), "part": (m.get("PartNumber") or "").strip()} for m in mods]},
        "graphics": [{"name": g.get("Name"), "ram_mb": round(g["AdapterRAM"] / 1024 ** 2) if isinstance(g.get("AdapterRAM"), int) and g["AdapterRAM"] > 0 else None, "driver": g.get("DriverVersion")} for g in gpus],
        "storage": [{"name": d.get("DeviceID"), "total_gb": gb(d.get("Size")), "free_gb": gb(d.get("FreeSpace"))} for d in disks],
        "board": {"maker": board.get("Manufacturer"), "model": board.get("Product"), "bios": bios.get("SMBIOSBIOSVersion"), "bios_maker": bios.get("Manufacturer"), "system_maker": cs.get("Manufacturer"), "system_model": cs.get("Model")},
        "os": {"system": "Windows", "name": os_.get("Caption"), "version": os_.get("Version"), "arch": os_.get("OSArchitecture"), "hostname": cs.get("Name") or platform.node()},
        "software": sorted(({"name": s.get("DisplayName"), "version": s.get("DisplayVersion")} for s in sw), key=lambda s: (s["name"] or "").lower()),
        "patches": [{"id": f.get("HotFixID"), "installed": str((f.get("InstalledOn") or {}).get("DateTime", f.get("InstalledOn") or "")) if isinstance(f.get("InstalledOn"), dict) else str(f.get("InstalledOn") or ""), "desc": f.get("Description")} for f in fixes],
    }


def collect_linux():
    info = read("/proc/cpuinfo")
    name = next((l.split(":", 1)[1].strip() for l in info.splitlines() if l.startswith("model name")), "")
    threads = sum(1 for l in info.splitlines() if l.startswith("processor"))
    mem_kb = next((int(l.split()[1]) for l in read("/proc/meminfo").splitlines() if l.startswith("MemTotal")), 0)
    dmi = lambda k: read("/sys/class/dmi/id/" + k)
    gpus = [{"name": l.split(": ", 1)[-1], "ram_mb": None, "driver": None} for l in run("lspci 2>/dev/null").splitlines() if "VGA" in l or "3D controller" in l]
    du = shutil.disk_usage("/")
    sw = [dict(zip(("name", "version"), l.split("\t"))) for l in run(["dpkg-query", "-W", "-f=${Package}\t${Version}\n"]).splitlines() if "\t" in l]
    osr = dict(l.split("=", 1) for l in read("/etc/os-release").splitlines() if "=" in l)
    return {
        "cpu": {"name": name, "cores": None, "threads": threads, "mhz": None},
        "memory": {"total_gb": gb(mem_kb * 1024), "modules": []},
        "graphics": gpus,
        "storage": [{"name": "/", "total_gb": gb(du.total), "free_gb": gb(du.free)}],
        "board": {"maker": dmi("board_vendor"), "model": dmi("board_name"), "bios": dmi("bios_version"), "bios_maker": dmi("bios_vendor"), "system_maker": dmi("sys_vendor"), "system_model": dmi("product_name")},
        "os": {"system": "Linux", "name": osr.get("PRETTY_NAME", "").strip('"'), "version": platform.release(), "arch": platform.machine(), "hostname": platform.node()},
        "software": sw,
        "patches": [],
    }


def collect_mac():
    def sp(t):
        try:
            return json.loads(run(["system_profiler", t, "-json"], 90))[t][0:]
        except Exception:
            return []
    hw = (sp("SPHardwareDataType") or [{}])[0]
    gpus = [{"name": g.get("sppci_model"), "ram_mb": None, "driver": None} for g in sp("SPDisplaysDataType")]
    du = shutil.disk_usage("/")
    mem = str(hw.get("physical_memory", "")).split()
    return {
        "cpu": {"name": hw.get("chip_type") or hw.get("cpu_type") or platform.processor(), "cores": hw.get("number_processors"), "threads": os.cpu_count(), "mhz": None},
        "memory": {"total_gb": float(mem[0]) if mem and mem[0].replace(".", "").isdigit() else None, "modules": []},
        "graphics": gpus,
        "storage": [{"name": "/", "total_gb": gb(du.total), "free_gb": gb(du.free)}],
        "board": {"maker": "Apple", "model": hw.get("machine_model"), "bios": hw.get("boot_rom_version"), "bios_maker": None, "system_maker": "Apple", "system_model": hw.get("machine_name")},
        "os": {"system": "Mac", "name": "macOS " + platform.mac_ver()[0], "version": platform.mac_ver()[0], "arch": platform.machine(), "hostname": platform.node()},
        "software": [], "patches": [],
    }


def main():
    print("AdminKU - Pemeriksaan Perangkat. Membaca spesifikasi, mohon tunggu (bisa 1 menit)...")
    collectors = {"Windows": collect_windows, "Linux": collect_linux, "Darwin": collect_mac}
    if SYSTEM not in collectors:
        sys.exit("Sistem operasi ini belum didukung (hanya Windows, Linux, Mac).")
    data = collectors[SYSTEM]()
    body = json.dumps(data).encode("utf-8")
    req = urllib.request.Request(URL, data=body, headers={"Content-Type": "application/json"}, method="POST")
    try:
        urllib.request.urlopen(req, timeout=60).read()
    except Exception as e:
        sys.exit("Gagal mengirim laporan: %s\nKembali ke AdminKU dan ulangi dari tombol More details." % e)
    print("Selesai. Laporan terkirim. Kembali ke AdminKU dan muat ulang layar Perangkat Saya.")


if __name__ == "__main__":
    main()
