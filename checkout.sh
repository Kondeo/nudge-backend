branch=$1

git fetch origin
cp ./connection.php ./connectionBAK.php
git checkout $branch
rm ./connection.php
cp ./connectionBAK.php ./connection.php
