branch=$1

cp ./ProtectedDocs/connectionDummy.php ./ProtectedDocs/connection.php
git fetch origin
git checkout $branch
rm ./ProtectedDocs/connection.php
cp ./ProtectedDocs/connectionLive.php ./ProtectedDocs/connection.php
