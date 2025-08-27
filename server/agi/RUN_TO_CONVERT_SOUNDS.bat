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

REM Initialize counter
set /a total_count=0

REM Convert files in main sounds folder (if any)
set /a main_count=0
for %%f in (sounds\*.mp3) do (
    set /a main_count+=1
    set /a total_count+=1
    echo Converting: %%~nxf
    ffmpeg -y -i "%%f" -ar 8000 -ac 1 -acodec pcm_s16le "sounds_wav\%%~nf.wav" >nul 2>&1
)

if %main_count% gtr 0 (
    echo   Converted %main_count% files from main folder
    echo.
) else (
    echo No MP3 files found in main sounds folder
    echo.
)

REM Dynamically find all subfolders in sounds directory and convert their MP3 files
for /d %%D in (sounds\*) do (
    set "subfolder=%%~nxD"
    set /a sub_count=0
    
    REM Check if this subfolder has MP3 files
    for %%f in ("%%D\*.mp3") do (
        if !sub_count! equ 0 (
            echo Converting !subfolder! subfolder...
            if not exist "sounds_wav\!subfolder!" mkdir "sounds_wav\!subfolder!"
        )
        set /a sub_count+=1
        set /a total_count+=1
        echo   Converting: !subfolder!\%%~nxf
        ffmpeg -y -i "%%f" -ar 8000 -ac 1 -acodec pcm_s16le "sounds_wav\!subfolder!\%%~nf.wav" >nul 2>&1
    )
    
    if !sub_count! gtr 0 (
        echo   Converted !sub_count! files from !subfolder! folder
        echo.
    )
)

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