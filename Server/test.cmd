@echo off

call %~dp0tests\phpunit --bootstrap %~dp0router.php ^
     --colors=auto ^
     %~dp0tests
