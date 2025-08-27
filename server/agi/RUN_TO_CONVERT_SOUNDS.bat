@echo off
setlocal EnableDelayedExpansion
color 0A
title Sound Converter to 8kHz WAV
echo.
echo ===============================================
echo        SOUND CONVERTER TO 8kHz WAV
echo ===============================================
echo.

REM Check if FFmpeg is available
echo Checking for FFmpeg...
ffmpeg -version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ERROR: FFmpeg not found!
    echo.
    echo Please install FFmpeg first:
    echo - Download: https://ffmpeg.org/download.html
    echo - Or run: winget install ffmpeg
    echo.
    pause
    exit /b 1
)
echo FFmpeg found ✓
echo.

REM Create output directory
if not exist "sounds_wav" mkdir "sounds_wav"
echo Created sounds_wav directory
echo.

REM Convert main sounds folder
echo Converting main sounds folder...
set /a main_count=0
for %%f in (sounds\*.mp3) do (
    set /a main_count+=1
    echo   Converting: %%~nxf
    ffmpeg -y -i "%%f" -ar 8000 -ac 1 -acodec pcm_s16le "sounds_wav\%%~nf.wav" >nul 2>&1
)
echo   Converted %main_count% files from main folder
echo.

REM Convert iqtaxi subfolder if exists
set /a sub_count=0
if exist "sounds\iqtaxi" (
    echo Converting iqtaxi subfolder...
    if not exist "sounds_wav\iqtaxi" mkdir "sounds_wav\iqtaxi"
    for %%f in (sounds\iqtaxi\*.mp3) do (
        set /a sub_count+=1
        echo   Converting: iqtaxi\%%~nxf
        ffmpeg -y -i "%%f" -ar 8000 -ac 1 -acodec pcm_s16le "sounds_wav\iqtaxi\%%~nf.wav" >nul 2>&1
    )
    echo   Converted %sub_count% files from iqtaxi folder
) else (
    echo No iqtaxi subfolder found
)

echo.
set /a total_count=%main_count%+%sub_count%
echo ===============================================
echo        CONVERSION COMPLETED!
echo ===============================================
echo Total files converted: %total_count%
echo Output location: sounds_wav\
echo Format: 8kHz mono WAV (perfect for telephony)
echo.
echo All done! You can now use the WAV files.
echo.
pause