@REM Maven wrapper for Windows. See portal/mvnw for the design rationale.
@echo off
setlocal enabledelayedexpansion

set SCRIPT_DIR=%~dp0
set PROJECT_DIR=%SCRIPT_DIR%
set MVNW_PROPERTIES=%PROJECT_DIR%\.mvn\wrapper\maven-wrapper.properties

if not exist "%MVNW_PROPERTIES%" (
    echo ERROR: .mvn/wrapper/maven-wrapper.properties not found. Run from the portal\ directory. >&2
    exit /b 1
)

set WRAPPER_JAR_URL=
set DISTRIBUTION_URL=
for /f "tokens=1,* delims==" %%A in ('findstr /R "^wrapperUrl=" "%MVNW_PROPERTIES%"') do set WRAPPER_JAR_URL=%%B
for /f "tokens=1,* delims==" %%A in ('findstr /R "^distributionUrl=" "%MVNW_PROPERTIES%"') do set DISTRIBUTION_URL=%%B

if "!WRAPPER_JAR_URL!"=="" (
    echo ERROR: wrapperUrl must be set in .mvn/wrapper/maven-wrapper.properties >&2
    exit /b 1
)

set WRAPPER_JAR=%PROJECT_DIR%\.mvn\wrapper\maven-wrapper.jar

if exist "%WRAPPER_JAR%" (
    java -jar "%WRAPPER_JAR%" %*
    exit /b %errorlevel%
)

where mvn >nul 2>&1
if %errorlevel% equ 0 (
    mvn %*
    exit /b %errorlevel%
)

mkdir "%PROJECT_DIR%\.mvn\wrapper\dists" 2>nul
echo Downloading Maven wrapper jar from !WRAPPER_JAR_URL! >&2
curl -sS -L -o "%WRAPPER_JAR%" "!WRAPPER_JAR_URL!" 2>nul
if !errorlevel! neq 0 (
    echo ERROR: failed to download maven-wrapper.jar from !WRAPPER_JAR_URL! >&2
    exit /b 1
)
java -jar "%WRAPPER_JAR%" %*
