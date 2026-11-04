@echo off
title AdminKU - Pemeriksaan Perangkat
echo AdminKU - Pemeriksaan Perangkat. Membaca spesifikasi komputer ini, mohon tunggu (bisa 1 menit)...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$s = Get-Content -LiteralPath '%~f0' -Raw; iex $s.Substring($s.LastIndexOf('#'+'PS#') + 4)"
echo.
pause
exit /b
#PS#
# Dijalankan sekali atas persetujuan pengguna. Membaca: prosesor, memori, kartu grafis, disk, mainboard/BIOS, sistem operasi, software terpasang, patch/hotfix.
# TIDAK membaca isi berkas, password, atau riwayat browser. Tanpa Python. Token di bawah berlaku sekali dan kedaluwarsa dalam 30 menit.
$ErrorActionPreference = 'SilentlyContinue'
$Url = '__URL__'
function G($n) { if ($n) { [math]::Round($n / 1GB, 1) } else { $null } }
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$cs = Get-CimInstance Win32_ComputerSystem
$os = Get-CimInstance Win32_OperatingSystem
$bb = Get-CimInstance Win32_BaseBoard
$bios = Get-CimInstance Win32_BIOS
$mods = @(Get-CimInstance Win32_PhysicalMemory | ForEach-Object { @{ slot = $_.BankLabel; size_gb = (G $_.Capacity); speed = $_.Speed; maker = ("$($_.Manufacturer)").Trim(); part = ("$($_.PartNumber)").Trim() } })
$gpus = @(Get-CimInstance Win32_VideoController | ForEach-Object { @{ name = $_.Name; ram_mb = $(if ($_.AdapterRAM -gt 0) { [int]($_.AdapterRAM / 1MB) } else { $null }); driver = $_.DriverVersion } })
$disks = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' | ForEach-Object { @{ name = $_.DeviceID; total_gb = (G $_.Size); free_gb = (G $_.FreeSpace) } })
$sw = @(Get-ItemProperty 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*', 'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*' | Where-Object { $_.DisplayName } | Sort-Object DisplayName | ForEach-Object { @{ name = $_.DisplayName; version = $_.DisplayVersion } })
$fx = @(Get-HotFix | ForEach-Object { @{ id = $_.HotFixID; installed = ("$($_.InstalledOn)"); desc = $_.Description } })
$data = @{
    cpu = @{ name = ("$($cpu.Name)").Trim(); maker = $cpu.Manufacturer; cores = $cpu.NumberOfCores; threads = $cpu.NumberOfLogicalProcessors; mhz = $cpu.MaxClockSpeed; mhz_now = $cpu.CurrentClockSpeed; l2_kb = $cpu.L2CacheSize; l3_kb = $cpu.L3CacheSize }
    memory = @{ total_gb = (G $cs.TotalPhysicalMemory); modules = $mods }
    graphics = $gpus
    storage = $disks
    board = @{ maker = $bb.Manufacturer; model = $bb.Product; bios = $bios.SMBIOSBIOSVersion; bios_maker = $bios.Manufacturer; system_maker = $cs.Manufacturer; system_model = $cs.Model }
    os = @{ system = 'Windows'; name = $os.Caption; version = $os.Version; arch = $os.OSArchitecture; hostname = $cs.Name }
    software = $sw
    patches = $fx
}
$json = $data | ConvertTo-Json -Depth 5 -Compress
try {
    Invoke-RestMethod -Uri $Url -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) -ContentType 'application/json' | Out-Null
    Write-Host 'Selesai. Laporan terkirim. Kembali ke AdminKU dan muat ulang layar More Details.'
} catch {
    Write-Host ('Gagal mengirim laporan: ' + $_.Exception.Message)
    Write-Host 'Kembali ke AdminKU dan ulangi dari tombol More Details.'
}
