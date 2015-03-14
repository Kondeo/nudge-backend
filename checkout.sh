branch=$1

git fetch origin
git checkout $branch
rm ./connection.php
cp ./connectionLive.php ./connection.php
