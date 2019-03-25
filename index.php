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
		$page_source='';
		function test_input($data) {
			$data = trim($data);
			//$data = stripslashes($data);
			$data = htmlspecialchars($data);
			return $data;
		}
		
		function valid_id($data) {
			preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11}).*/', $data, $matches);
			//var_dump($matches);
			echo "Video ID: " . $matches[1] . "<br>";
			if (!empty($matches[1])) {
				return $matches[1];
			} else {
				return false;
			}
		}
		
		function valid_url($data) {
			//check if the url leads to an actual video
			$url = "https://www.youtube.com/watch?v=$data";
			echo "Checking " . $url . "<br>";
			global $page_source;
			$page_source = file_get_contents($url);
			//echo "Source: " . "$page_source";
			if (!preg_match("/'VIDEO_ID': \"$data\"/", $page_source)) {
				return false;
			}
			return $url;
		}
		
		function decode_url_array(&$arr_of_urls){
			foreach($arr_of_urls as &$value) {
				$value = urldecode($value);
			}
			unset($value);
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
				//valid_url also stores the youtube page's source into $page_source
				$youtubelink = valid_url($youtubeid);
			} else {
				die('invalid video id');
			}
			if ($youtubelink) {
				echo "Valid Youtube URL: " . $youtubelink . "<br>";
			} else {
				die('unable to find video');
			}

			//need to parse out all of the available download links from $page_source
			//this gets a non-distinct array of available itags
			//preg_match_all('/itag=\K[0-9]*/', $page_source, $itags);
			//echo '<pre>'; print_r($itags); echo '</pre>';
			
			//use regex /url=\K(?:.*?)\\\/ to get urls
			//use regex /itag=\K[0-9]*/ to get itags
			//$download_links[0] is an array of encoded urls
			//$itags is an array of itags corresponding to urls
			
			if (preg_match_all('/url=\K(?:.*?)(?=\\\|",)/', $page_source, $download_links)) {
				echo 'Successfully retrieved download links <br>';
			} else {
				echo 'Failed to retrieve download links <br>';
			}
			
			//echo '<pre>'; print_r($download_links); echo '</pre>';
			decode_url_array($download_links[0]);
                        echo '<pre>'; print_r($download_links); echo '</pre>';
			$itags = [];
			foreach($download_links[0] as $key => $value) {
				preg_match('/itag=\K[0-9]*/', $value, $temp);
				$itags[$key] = $temp[0];
			}
			echo '<pre>'; print_r($itags); echo '</pre>';
			//echo $page_source;
			
			//now we have the links and the itags which give information on each download.
			//we need to display the information on the site.
			
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
