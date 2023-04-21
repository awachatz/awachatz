<?php 
error_reporting(1);
$servername = "localhost";
$username = "anywhereanycity_awachatz";
$password = "KhGB=gUW$0A}";
$dbname = "anywhereanycity_awachatz";
$conn = connection($servername,$username,$password,$dbname);
$sql = "SELECT * FROM  users";
$result = $conn->query($sql);

if ($result->num_rows > 0) 
{
	
	while($row = $result->fetch_assoc()) 
	{
		
		
		$email 						= $row['email'];
		$username 					= $row['email'];
		$first_name  				= $row['first_name'];
		$last_name  				= $row['last_name'];
		$phone  					= $row['phone'];
		$is_verified_email  		= 'Y';
		$login_type  				= 'N';
		$status  					= 'A';
		$user_type  				= 'U';
		$join_date   				= date('Y-m-d h:i:s');
	
	 	//Login Aws support
		$dbname 	= 'anywhereanycity_aws_support';  
		$conn2      = connection($servername,'anywhereanycity_anywhereanycity_aws_support','mM$^2aWw4f#y',$dbname);
		$sql 		= "SELECT * FROM site_user where email ='$email'";
		$result2 	= $conn2->query($sql);
	     
	if ($result2->num_rows == 0) {
		
		$password_site  = 'Common123!';
		
		 $sql = "INSERT INTO  site_user (first_name,last_name,username,email,pass,is_verified_email,phone,login_type,status,user_type,join_date)
				VALUES ('$first_name','$last_name','$username','$email','$password_site','$is_verified_email','$phone','$login_type','$status','$user_type','$join_date')";
		$conn2->query($sql); 
		$last_id = mysqli_insert_id($conn2);	
		$newPassword = md5($last_id.$password_site);	

        $sql = "UPDATE site_user SET pass='$newPassword' WHERE id=$last_id";
        $conn2->query($sql); 		 
	}


    
	

	
	
	

	
	
 }



} else {
  echo "0 results";
}
//$conn->close();


function connection($servername,$username,$password,$dbname){
	return $conn = new mysqli($servername, $username, $password, $dbname); 
}

?>