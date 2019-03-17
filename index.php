<html>
<head>
	<title>Youtube Video Saver</title>
<head>

<body>
	<h1>Save Youtube video to server:</h1><br>
	<form action="index.php" method="post">URL: <input type="text" name="youtubeurl"><br>
	<input type="submit">
	</form>

	<?php
		function test_input($data) {
			//$data = trim($data);
			//$data = stripslashes($data);
			//$data = htmlspecialchars($data);
			return $data;
		}
		function valid_id($data) {
			preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11}).*/', $data, $matches);
			//var_dump($matches);
			//echo "$data";
			if (!empty($matches[1])) {
				return $matches[1];
			} else {
				return false;
			}
		}
		function valid_url($data) {
			//check if the url leads to an actual video
			$url = "https://www.youtube.com/watch?v=$data";
			$page_source = file_get_contents($url);
			//echo "$page_source";
			if (!preg_match("/'VIDEO_ID': \"$data\"/", $page_source)) {
				return false;
			}
			return $url;
		}
		
		
		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$link = new mysqli("127.0.0.1", "root", "password");
                        if (!$link) {
                                die('Could not connect: ' . mysql_error());
                        }

			$youtubeurl = test_input($_POST["youtubeurl"]);
			echo "Input: " . $youtubeurl;
			echo "<br>";
			//validate url
			$youtubeid = valid_id($youtubeurl);
			if ($youtubeid) {
				$youtubelink = valid_url($youtubeid);
			} else {
				die('invalid video id');
			}
			if ($youtubelink) {
				echo "Valid Youtube URL: " . $youtubelink . "<br>";
			} else {
				die('unable to find video');
			}
			$link = new mysqli("127.0.0.1", "root", "password");
			$create_db = 'CREATE DATABASE IF NOT EXISTS youtubedb';
						
			if ($link->query($create_db)) {
				echo "Successfully created database <br>";
			} else {
				echo "Failed to create database <br>";
			}
			
			if ($link->select_db('youtubedb')) {
				echo "Successfully selected database <br>";
			} else {
				echo "Failed to select database <br>";
			}
			
			$create_table = 'CREATE TABLE IF NOT EXISTS videos (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			url VARCHAR(100)
			);';
			
			
			if ($link->query($create_table)) {
                                echo "Successfully created table <br>";
                        } else {
                                echo "Failed to create table <br>";
                        }
			
			$stmt = $link->prepare("INSERT INTO videos (url) VALUES (?)");
			$stmt->bind_param("s", $youtubelink);

			if ($stmt->execute()) {
				echo "Successfully saved url to table <br>";
			} else {
				echo "Failed to save url to table <br>";
			}
			
			$stmt->close();

			$result = $link->query("select id, url from videos");
			
			print("<table border=\"1\">");
			print("<tr>");
			print("<td>id</td>");
			print("<td>url</td>");
			print("<tr>");
			$row=array();
			while ($result && $row=$result->fetch_assoc()) {
				print("<tr>");
				print("<td>".$row["id"]."</td>");
				print("<td>".$row["url"]."</td>");
				print("</tr>");
			}
			print("</table>");
		}
	?>

</body>
</html>
