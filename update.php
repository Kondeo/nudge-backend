<?
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

if($_GET["git"] == "pull"){
	exec("./pull.sh");
	echo "Pulled master!";
}else if($_GET["git"] == "clone"){
	exec("./clone.sh");
	echo "Cloned master!";
}else if($_GET["git"] == "checkout" && isset($_GET["name"])){
	exec("./checkout.sh " . $_GET["name"]);
	echo "Switched to branch " . $_GET["name"];
}else{
	echo "Specify ?git=(pull|push|[checkout&name=BRANCH])";
}

?>