@echo off
rem Membuat pemasang AdminKU Pemeriksa (butuh Python 3 + pip). Hasil: storage\app\agent\AdminKU-Pemeriksa.exe (tidak ikut git).
rem Jalankan dari akar proyek: resources\agent\build.cmd
python -m pip install --quiet pyinstaller || exit /b 1
python -m PyInstaller --noconfirm --onefile --noconsole --name AdminKU-Pemeriksa --distpath storage\app\agent --workpath build\pemeriksa --specpath build\pemeriksa --add-data "%CD%\resources\agent\adminku-perangkat.py;." resources\agent\pemeriksa.py || exit /b 1
echo Selesai: storage\app\agent\AdminKU-Pemeriksa.exe
