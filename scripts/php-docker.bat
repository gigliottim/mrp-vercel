@echo off
set CONTAINER=lemp-phpfpm

docker inspect %CONTAINER% >nul 2>&1
if errorlevel 1 (
	set CONTAINER=mrp-phpfpm
)

docker exec -i %CONTAINER% php %*
