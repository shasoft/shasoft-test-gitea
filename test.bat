rem @@@@
git config --global user.email "you@example.com"
git config --global user.name "shasoft"
  
set myPath=%~dp0strorage
rd /S /Q %myPath%
mkdir %myPath%
cd %myPath%
git init

echo "123">1.txt
git add .
git commit -a -m "111"

echo "456">1.txt
git add .
git commit -a -m "222"

cd %~dp0