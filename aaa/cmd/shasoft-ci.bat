rem @call "%~dp0run-commands.bat" "%~dp0shasoft-ci.php" %*

@rem @echo off
@cls
set CI_COMMANDS_PATH=%~dp0~\Z
@set CI_COMMANDS_EXT=bat
@if EXIST %CI_COMMANDS_PATH% rmdir /S /Q %CI_COMMANDS_PATH%
@mkdir %CI_COMMANDS_PATH%
@rem ********************************************
php "%~dp0shasoft-ci-prepare.php" 0 %*
@if %errorlevel% neq 0 exit /b %errorlevel%
php "%~dp0run-commands.php" 0 "%~dp0shasoft-ci.php"
@if %errorlevel% neq 0 exit /b %errorlevel%
@IF EXIST %CI_COMMANDS_PATH%\0.%CI_COMMANDS_EXT% @call %CI_COMMANDS_PATH%\0.%CI_COMMANDS_EXT%