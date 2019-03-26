<html>
<head>
	<title>Youtube Video Saver</title>
<head>

<body>
	<h1>Save Youtube video to server:</h1><br>
	<form action="index2.php" method="post">URL: <input type="text" name="youtubeurl"><br>
	<input type="submit">
	</form>

	<?php
		//array of known itags
		$itag_array = json_decode(file_get_contents('./itags.json', true), true);
		//var_dump($itag_array);
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
		
		function is_video_unavailable(&$data) {
			//check if the video info (data) contains reason=Video+unavailable
			if (preg_match("/reason=Video unavailable/", $data)) {
				return true;
			}
			return false;
		}

		function is_age_restricted(&$data) {
			if (preg_match("/reason=Sign in to confirm your age/", $data)) {
                                return true;
                        }
                        return false;
		}
		
		function get_sts($video_embed_url) {
			$embed_page_source = file_get_contents($video_embed_url);
                        preg_match("/\"sts\":\K([0-9]*)/", $embed_page_source, $matches);
			return $matches[0];
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
			$video_info_url = "https://youtube.com/get_video_info?video_id=".$youtubeid."&el=detailpage&hl=en";
			$video_embed_url = "https://youtube.com/embed/".$youtubeid;
			$video_info = NULL;
			if ($youtubeid) {
				//use youtube get_info at this stage to get data. Need to try normal, then embed
				//first try to get info normally
				$video_info = urldecode(file_get_contents($video_info_url));
				//echo $video_info . '<br>';
				//check video existence
				if (is_video_unavailable($video_info)) {
					die('Video does not exist! <br>');
				} else {
					echo 'Video exists! <br>';
				}
				//check age restriction
				if (is_age_restricted($video_info)) {
					//follow age restriction procedures
					//first need to get sts from youtube/embed page
					$sts = get_sts($video_embed_url);
					echo 'sts: '.$sts.'<br>';
					$video_info_url = "https://youtube.com/get_video_info?video_id=".$youtubeid."&eurl=https://youtube.googleapis.com/v/".$youtubeid."&sts=".$sts;
					$video_info = urldecode(file_get_contents($video_info_url));
					//echo $video_info . '<br>';
				}
				//regex the itags and encoded urls, then json decode and url decode the urls
				echo 'Used JSON from: '.$video_info_url.'<br>';
				preg_match_all("/\"itag\":([0-9]*),\"url\":\"(.*?)\"/", $video_info, $download_info);
			} else {
				die('Invalid input!');
			}
			//echo '<pre>'; print_r($download_info); echo '</pre>';
			//we need to get rid of all of the \u0020 and then url decode again
			foreach ($download_info[2] as $key => $value) {
				//echo $value.'<br>';
				$download_info[2][$key] = preg_replace('/\\\u0026/','&',$value);
			}
			echo '<pre>'; print_r($download_info); echo '</pre>';

			//now all itags and corresponding download links are stored in $download_info[1] and $download_info[2]
			//the last step is to print out all download formats and corresponding clickable links
			foreach($download_info[1] as $itag) {
				echo 'itag = ' . $itag . ': ';
				foreach($itag_array[$itag] as $key => $value) {
					echo $key . ': ' . $value . ', ';
				}
				echo '<br>';
			}

			//it turns out finding out which itags mean what is difficult because the lists on github are outdated.
			//instead try getting stream information with another regular expression. itags are still useful for identifying streams.
			preg_match_all('/(?:"itag":([0-9]*),"url":"(.*?)","mimeType":"(.*?)","bitrate":([0-9]*),)(?:"width":([0-9]*),"height":([0-9]*))?/', $video_info, $raw_stream_info);
			echo '<pre>'; print_r($raw_stream_info); echo '</pre>';
			//$raw_stream_info holds arrays in order: all, itag, url, filetype and codec, bitrate, width, height
			
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
			$stmt->bind_param("s", $youtubeid);

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
