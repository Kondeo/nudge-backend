branch=$1

git fetch origin
git checkout $branch
rm ./ProtectedDocs/connection.php
cp ./ProtectedDocs/connectionLive.php ./ProtectedDocs/connection.php
