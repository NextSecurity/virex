<?php

/* * ***************************************** */
/* Norman SampleShare Client Framework        */
/* Version 1.10                               */
/* Created by Trygve Brox - Norman ASA - 2010 */
/* * ***************************************** */
/// check to see if passphrase is changed
if (GPG_PASSPHRASE == 'your_passphrase')
    die('Please change you gpg passphrase [file: samplseshare.php, line:8]' . "\n");

/// [END] check - after you change your passphrase you can also remove the above line
class SampleShareObject {

    public $from_date_utc;
    public $to_date_utc;
    public $md5list;
    public $md5count;
    public $metadata;
    public $curl_dl_speed;
    public $curl_dl_time;
    public $curl_dl_size;
    public $curl_dl_size_bytes;
    public $total_download_time;
    public $total_download_files;
    public $total_download_size;
    public $total_download_size_bytes;
    public $total_download_avg_speed;
    private $curl_output;
    private $max_blocksize;
    private $ch;
    private $http_user;
    private $http_pass;
    private $http_url;
    private $download_directory;
    private $dl_stated_time;
    private $sample_callback;
    private $cleanfiles_callback;
    private $compression;

    function __construct() {
        echo "Sampleshare Object initialized\r\n";
        $this->ch = curl_init();
    }

    function __destruct() {
        curl_close($this->ch);
    }

    public function set_max_blocksize($size) {
        if ($size > 134217728) {
            echo "Setting blocksize to the maximum value of 134217728 bytes/128MB";
            $this->max_blocksize = 134217728;
        } else if ($size < 10485760) {
            echo "Setting blocksize to the minimum value of 10485760 bytes/10MB";
            $this->max_blocksize = 10485760;
        } else {
            $this->max_blocksize = $size;
        }
    }

    public function set_download_directory($dir) {
        if ($dir == "")
            die("Error! empty variable specified");
        if (!is_dir($dir))
            die("Error! $dir is not a directory");
        $this->download_directory = $dir;
    }

    private function download_block($block, $blocksize, $files_to_dl, $clean) {
        if ($blocksize > 1024 * 1024)
            $blocksize = sprintf("%0.2fMB", $blocksize / 1024 / 1024);
        else
            $blocksize = sprintf("%0.2fKB", $blocksize / 1024);
        echo "Downloading $files_to_dl files ($blocksize decrypted).. ";

        if ($clean == true) {
            $suffix = "CLEANFILE";
            $clean_option = "true";
            $callback = $this->cleanfiles_callback;
        } else {
            $suffix = "sample";
            $clean_option = "false";
            $callback = $this->sample_callback;
        }

        $post_data = "md5list=$block";
        if ($this->compression != "")
            $compression = "&compression=$this->compression";
        $this->curl_download("?action=getfile_by_list&clean=$clean_option&user=$this->http_user$compression", $post_data);
        echo "Ok!\r\nDownloaded $this->curl_dl_size bytes in $this->curl_dl_time seconds at $this->curl_dl_speed\r\n";

        $offset = 0;

        while ($offset < $this->curl_dl_size_bytes) {
            $file_len = substr($this->curl_output, $offset, 10);

            $offset+=10;
            $md5 = substr($this->curl_output, $offset, 32);

            $offset+=32;
            $file_contents = substr($this->curl_output, $offset, $file_len);

            $offset+=$file_len;

            $first_part = substr($file_contents, 0, 10);
            if ($first_part == "ERROR! => ") {
                echo "Error returned from server: $file_contents\r\n";
                continue;
            }

            $plaintext = "$this->download_directory/" . $md5 . "_" . $suffix;
            $encrypted = $plaintext . ".gpg";

            echo "Decrypting $suffix with MD5 $md5 - Filesize is $file_len bytes.. ";

            if (file_exists($plaintext))
                unlink($plaintext);
            if (file_exists($encrypted))
                unlink($encrypted);

            $fout = fopen($encrypted, "w");
            if (!$fout)
                die("Error creating $encrypted!");
            fputs($fout, $file_contents);
            fclose($fout);

            unset($file_contents);


            $this->decrypt($encrypted, $plaintext);
            if ($this->compression != "")
                $this->decompress($plaintext);

            $file_md5 = strtoupper(md5_file($plaintext));
            if (strcasecmp($file_md5, $md5))
                die("Error! MD5 mismatch on $plaintext (Expected: $md5 -> Actual: $file_md5)");

            echo "Done!\r\n";

            if ($callback != "") {
                call_user_func($callback, $md5);
            }

            $this->total_download_files++;
            $this->total_download_size_bytes+=$file_len;
        }

        echo "$this->total_download_files of $this->md5count files downloaded\r\n\r\n";
    }

    public function set_sample_callback($function) {
        if (function_exists($function)) {
            $this->sample_callback = $function;
        } else {
            die("Invalid function specified\r\n");
        }
    }

    public function set_cleanfiles_callback($function) {
        if (function_exists($function)) {
            $this->cleanfiles_callback = $function;
        } else {
            die("Invalid function specified\r\n");
        }
    }

    public function get_metadata() {
        if ($this->from_date_utc == "")
            die("Error! From-date not set");
        if ($this->to_date_utc == "")
            die("Error! To-date not set");
        if ($this->http_user == "")
            die("Error! Username not set");
        if ($this->http_pass == "")
            die("Error! Password not set");
        if ($this->http_url == "")
            die("Error! URL not set");
        if ($this->download_directory == "")
            die("Error! Download-directory not set");
        if (!is_dir($this->download_directory))
            die("Error! $this->download_directory is not a directory");

        $this->metadata = "";

        $plaintext = "metadata.xml";
        $encrypted = $plaintext . ".gpg";

        if (file_exists($plaintext))
            unlink($plaintext);
        if (file_exists($encrypted))
            unlink($encrypted);

        $this->curl_download("?action=getmetadata&user=$this->http_user&from=$this->from_date_utc&to=$this->to_date_utc");

        $fout = fopen($encrypted, "w");
        if (!$fout)
            die("Unable to create $encrypted!");
        fputs($fout, $this->curl_output);
        fclose($fout);

        $this->decrypt($encrypted, $plaintext);
        $contents = file_get_contents($plaintext);

        $first_part = substr($contents, 0, 10);
        if ($first_part == "ERROR! => ") {
            if (strstr($contents, "No metadata found")) {
                echo "No metadata found\r\n";
                return 0;
            } else {
                echo "Error returned from server: $contents\r\n";
                return 0;
            }
        } else {
            $this->metadata = $contents;
            return 1;
        }
    }

    private function check_vars_file() {
        if ($this->http_user == "")
            die("Error! Username not set");
        if ($this->http_pass == "")
            die("Error! Password not set");
        if ($this->http_url == "")
            die("Error! URL not set");
        if ($this->download_directory == "")
            die("Error! Download-directory not set");
        if (!is_dir($this->download_directory))
            die("Error! $this->download_directory is not a directory");
    }

    public function set_compression($compression) {
        $this->compression = $compression;
        echo "Requesting compression type $compression.\r\n";
    }

    public function is_supported_compression($compression) {
        if (strstr($this->supported_compression, $compression . "\r\n"))
            return true;
        else
            return false;
    }

    public function get_supported_compression() {
        $this->curl_download("?action=get_supported_compression&user=$this->http_user");
        $arr = explode("\r\n", $this->curl_output);
        $this->supported_compression = $this->curl_output;
        echo "Supported compression methods:";
        foreach ($arr as $compression_method) {
            echo $compression_method . " ";
        }
        echo "\r\n";
    }

    public function decompress($compressed_sample) {
        // Add handlers for additional compression methods here
        $sample = $compressed_sample;
        if ($this->compression == "zlib") {
            $compressed_sample.=".gz";
            rename($sample, $compressed_sample);

            $gzin = gzopen($compressed_sample, 'rb');
            if (!$gzin)
                die("Error opening $compressed_sample!");

            $fout = fopen($sample, 'wb');
            if (!$fout)
                die("Error creating $sample!");

            while (!gzeof($gzin)) {
                fwrite($fout, gzread($gzin, 1024 * 512));
            }

            fclose($fout);
            gzclose($gzin);
            unlink($compressed_sample);

            return $sample;
        }
    }

    private function check_vars_list() {
        if ($this->from_date_utc == "")
            die("Error! From-date not set");
        if ($this->to_date_utc == "")
            die("Error! To-date not set");
        if ($this->http_user == "")
            die("Error! Username not set");
        if ($this->http_pass == "")
            die("Error! Password not set");
        if ($this->http_url == "")
            die("Error! URL not set");
        if ($this->download_directory == "")
            die("Error! Download-directory not set");
        if (!is_dir($this->download_directory))
            die("Error! $this->download_directory is not a directory");
        if ($this->max_blocksize == "")
            die("Error! max blocksize not set");
    }

	public function get_urls(){
		$this->check_vars_list();

		$plaintext = "urlslist.txt";
		$encrypted = $plaintext . ".gpg";

		if (file_exists($plaintext))
		unlink($plaintext);
		if (file_exists($encrypted))
		unlink($encrypted);

		$this->curl_download("?action=geturls&user=$this->http_user&from=$this->from_date_utc&to=$this->to_date_utc");

		$fout = fopen($encrypted, "w");
		if (!$fout)
		die("Unable to create $encrypted!");

		fputs($fout, $this->curl_output);
		fclose($fout);

		$this->decrypt($encrypted, $plaintext);
		$contents = file_get_contents($plaintext);

        if ($contents == "") {
            echo "No new URLs found\r\n";
            return 0;
        }
        
        return explode("\n", trim($contents));
    }
	
    public function get_file($md5) {
        $this->check_vars_file();
        if ($md5 == "")
            die("Error! Md5 empty");
        if (strlen($md5) != 32)
            die("Error! Invalid MD5");

        if ($this->dl_started_time == "")
            $this->dl_started_time = time();

        echo "Downloading $md5.. ";

        if ($this->compression != "")
            $compression = "&compression=$this->compression";
        $this->curl_download("?action=getfile&user=$this->http_user&md5=$md5$compression");

        $plaintext = "$this->download_directory/$md5";
        $encrypted = $plaintext . ".gpg";

        if (file_exists($plaintext))
            unlink($plaintext);
        if (file_exists($encrypted))
            unlink($encrypted);

        $fout = fopen($encrypted, "w");
        if (!$fout)
            die("Unable to create $encrypted!");
        fputs($fout, $this->curl_output);
        fclose($fout);

        $this->decrypt($encrypted, $plaintext);
        if ($this->compression != "")
            $this->decompress($plaintext);

        $sample_md5 = strtoupper(md5_file($plaintext));
        if (strcasecmp($sample_md5, $md5))
            die("Error! MD5 mismatch on $sample (Expected: $md5 -> Actual:$sample_md5)");

        echo "Ok! Downloaded $this->curl_dl_size in $this->curl_dl_time seconds at $this->curl_dl_speed\r\n";

        if ($this->sample_callback != "") {
            call_user_func($this->sample_callback, $md5);
        }

        $this->total_download_size_bytes+=$this->curl_dl_size_bytes;
        $this->total_download_files++;
        $this->dl_ended_time = time();
    }

    public function get_clean_file($md5) {
        check_vars_file();
        if ($md5 == "")
            die("Error! Md5 empty");
        if (strlen($md5) != 32)
            die("Error! Invalid MD5");

        if ($this->dl_started_time == "")
            $this->dl_started_time = time();

        echo "Downloading CLEANFILE $md5.. ";

        $this->curl_download("?action=getfile&clean=true&user=$this->http_user&md5=$md5");

        $plaintext = "$this->download_directory/$md5";
        $encrypted = $plaintext . ".gpg";

        if (file_exists($plaintext))
            unlink($plaintext);
        if (file_exists($encrypted))
            unlink($encrypted);

        $fout = fopen($encrypted, "w");
        if (!$fout)
            die("Unable to create $encrypted!");
        fputs($fout, $this->curl_output);
        fclose($fout);

        $this->decrypt($encrypted, $plaintext);

        $cleanfile_md5 = strtoupper(md5_file($plaintext));
        if (strcasecmp($cleanfile_md5, $md5))
            die("Error! MD5 mismatch on $encrypted (Expected: $md5 -> Actual: $cleanfile_md5)");

        echo "Ok! Downloaded $this->curl_dl_size in $this->curl_dl_time seconds at $this->curl_dl_speed\r\n";

        if ($this->cleanfile_callback != "") {
            call_user_func($this->cleanfile_callback, $md5);
        }

        $this->total_download_size_bytes+=$this->curl_dl_size_bytes;
        $this->total_download_files++;
        $this->dl_ended_time = time();
    }

    public function print_avg_speed() {
        $this->total_download_time = $this->dl_ended_time - $this->dl_started_time;
        @$avg_speed = $this->total_download_size_bytes / $this->total_download_time;
        if ($avg_speed > 1024 * 1024)
            $avg_speed = sprintf("%0.2fMB/sec", $avg_speed / 1024 / 1024);
        else
            $avg_speed = sprintf("%0.2fKB/sec", $avg_speed / 1024);

        if ($this->total_download_size_bytes > 1024 * 1024)
            $this->total_download_size = sprintf("%0.2fMB", $this->total_download_size_bytes / 1024 / 1024);
        else
            $this->total_download_size = sprintf("%0.2fKB", $this->total_download_size_bytes / 1024);

        echo "\r\nDownloaded $this->total_download_files files/$this->total_download_size in $this->total_download_time seconds at $avg_speed\r\n";
    }

    public function get_files_by_list() {
        $this->check_vars_list();
        if (!is_array($this->md5list)) {
            echo "No files to download";
            return;
        }

        $download_started_time = time();
        $this->total_download_size_bytes = 0;
        $this->total_download_files = 0;

        $block = "";
        $blocksize = 0;
        $files_to_dl = 0;
        foreach ($this->md5list as $key => $entry) {
            $md5 = $entry["md5"];
            $size = $entry["size"];
            $download = $entry["download"];
            if ($download != true)
                continue;

            if ($blocksize + $size > $this->max_blocksize) {
                /* Maximum blocksize reached. Download block */
                $this->download_block($block, $blocksize, $files_to_dl, false);

                $files_to_dl = 0;
                $blocksize = 0;
                $block = "";
            }
            $block.="$md5:";
            $blocksize+=$size;
            $files_to_dl++;
        }

        if ($files_to_dl > 0) {
            $this->download_block($block, $blocksize, $files_to_dl, false);
        }

        $this->total_download_time = time() - $download_started_time;
        @$this->total_download_avg_speed = sprintf("%0.2fKB/sec", ($this->total_download_size_bytes / $this->total_download_time) / 1024);
        if ($this->total_download_size_bytes > 1024 * 1024)
            $this->total_download_size = sprintf("%0.2fMB", $this->total_download_size_bytes / 1024 / 1024);
        else
            $this->total_download_size = sprintf("%0.2fKB", $this->total_download_size_bytes / 1024);
    }

    public function get_cleanfiles_by_list() {
        $this->check_vars_list();
        if (!is_array($this->md5list)) {
            echo "No files to download";
            return;
        }

        $download_started_time = time();
        $this->total_download_size_bytes = 0;
        $this->total_download_files = 0;

        $block = "";
        $blocksize = 0;
        $files_to_dl = 0;
        foreach ($this->md5list as $key => $entry) {
            $md5 = $entry["md5"];
            $size = $entry["size"];
            $download = $entry["download"];
            if ($download != true)
                continue;

            if ($blocksize + $size > $this->max_blocksize) {
                /* Maximum blocksize reached. Download block */
                echo "$blocksize $size $this->max_blocksize\r\n";
                $this->download_block($block, $blocksize, $files_to_dl, true);

                $files_to_dl = 0;
                $blocksize = 0;
                $block = "";
            }
            $block.="$md5:";
            $blocksize+=$size;
            $files_to_dl++;
        }

        if ($files_to_dl > 0) {
            $this->download_block($block, $blocksize, $files_to_dl, true);
        }

        $this->total_download_time = time() - $download_started_time;
        @$this->total_download_avg_speed = sprintf("%0.2fKB/sec", ($this->total_download_size_bytes / $this->total_download_time) / 1024);
        if ($this->total_download_size_bytes > 1024 * 1024)
            $this->total_download_size = sprintf("%0.2fMB", $this->total_download_size_bytes / 1024 / 1024);
        else
            $this->total_download_size = sprintf("%0.2fKB", $this->total_download_size_bytes / 1024);
    }

    private function curl_download($url, $post_data = NULL) {
        if ($this->http_user == "")
            die("HTTP-username not specified");
        if ($this->http_pass == "")
            die("HTTP-password not specified");

        if ($url == "")
            die("Error! URL is empty");
        
        $url = str_replace('//', "//$this->http_user:$this->http_pass@", $this->http_url) . $url;
        
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        if (HTTP_PROXY_ADDRESS) {
            curl_setopt($this->ch, CURLOPT_PROXY, HTTP_PROXY_ADDRESS);
            curl_setopt($this->ch, CURLOPT_PROXYPORT, HTTP_PROXY_PORT);
        }
        if ($post_data != "") {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_data);
        }

        $output = curl_exec($this->ch);
        if ($output === false) {
            die("Curl error: " . curl_error($this->ch));
        }

        $returncode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($returncode == "401") {
            die("Curl error: 401 - Authentication required! Incorrect HTTP username/password specified");
        }

        if (strlen($output) < 500) {
            if (strstr($output, "ERROR! => "))
                die("Server returned $output");
        }

        if ($output == "")
            die("Error! No data returned from server\r\n");

        $this->curl_output = $output;
        unset($output);

        $this->curl_dl_time = curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);
        $this->curl_dl_speed = sprintf("%0.2fKB/sec", curl_getinfo($this->ch, CURLINFO_SPEED_DOWNLOAD) / 1024);
        $this->curl_dl_size_bytes = curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD);
        $this->curl_dl_size = curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD);
        if ($this->curl_dl_size > 1024 * 1024)
            $this->curl_dl_size = sprintf("%0.2fMB", $this->curl_dl_size / 1024 / 1024);
        else
            $this->curl_dl_size = sprintf("%0.2fKB", $this->curl_dl_size / 1024);
    }

    public function get_list() {
        $this->check_vars_list();

        $plaintext = "md5list.txt";
        $encrypted = $plaintext . ".gpg";

        if (file_exists($plaintext))
            unlink($plaintext);
        if (file_exists($encrypted))
            unlink($encrypted);

        $this->curl_download("?action=getlist&user=$this->http_user&from=$this->from_date_utc&to=$this->to_date_utc");

        $fout = fopen($encrypted, "w");
        if (!$fout)
            die("Unable to create $encrypted!");

        fputs($fout, $this->curl_output);
        fclose($fout);

        $this->decrypt($encrypted, $plaintext);
        $contents = file_get_contents($plaintext);

        if ($contents == "") {
            echo "No new files found\r\n";
            return 0;
        }

        $list = explode("\r\n", $contents);
        $this->md5count = count($list);
        if ($list[$this->md5count - 1] == "") {
            unset($list[$this->md5count - 1]);
            $this->md5count--;
        }

        foreach ($list as $key => $entry) {
            if (!strstr($entry, ":"))
                die("Error: Entry $entry does not seem to contain an MD5 and a filesize");
            $arr = explode(":", $entry);
            $this->md5list[$key]["md5"] = $arr[0];
            $this->md5list[$key]["size"] = $arr[1];
            $this->md5list[$key]["download"] = false;
        }
        return 1;
    }

    public function get_cleanfiles_list() {
        $this->check_vars_list();

        $plaintext = "md5list.txt";
        $encrypted = $plaintext . ".gpg";

        if (file_exists($plaintext))
            unlink($plaintext);
        if (file_exists($encrypted))
            unlink($encrypted);

        $this->curl_download("?action=getlist&clean=true&user=$this->http_user&from=$this->from_date_utc&to=$this->to_date_utc");

        $fout = fopen($encrypted, "w");
        if (!$fout)
            die("Unable to create $encrypted!");

        fputs($fout, $this->curl_output);
        fclose($fout);

        $this->decrypt($encrypted, $plaintext);
        $contents = file_get_contents($plaintext);

        if ($contents == "") {
            echo "No new files found\r\n";
            return 0;
        }


        $list = explode("\r\n", $contents);
        $this->md5count = count($list);
        if ($list[$this->md5count - 1] == "") {
            unset($list[$this->md5count - 1]);
            $this->md5count--;
        }

        foreach ($list as $key => $entry) {
            if (!strstr($entry, ":"))
                die("Error: Entry $entry does not seem to contain an MD5 and a filesize");
            $arr = explode(":", $entry);
            $this->md5list[$key]["md5"] = $arr[0];
            $this->md5list[$key]["size"] = $arr[1];
            $this->md5list[$key]["download"] = false;
        }
        return 1;
    }

    function decrypt($encrypted, $plaintext) {
        $errorfile = "decrypt.error";
        if (file_exists($errorfile))
            unlink($errorfile);
//		exec("gpg --import /var/www/key_secret"); // uncomment this to import your secret key
        $command = GPG_PATH . " --skip-verify --batch --decrypt --passphrase=" . GPG_PASSPHRASE . " --quiet --no-mdc-warning $encrypted > $plaintext 2>$errorfile";
        $result = exec($command, $stdout, $error);
        if ($error != 0) {
            echo "Error during decryption. Errorcode: $error";
            if (file_exists($errorfile)) {
                $error_contents = file_get_contents($errorfile);
                if (strstr($error_contents, "no valid OpenPGP data found")) {
                    //if(filesize($encrypted) < 1000) 
                    $contents = "Server said: " . file_get_contents($encrypted);
                    die(". Downloaded data does not seem to be PGP'ed. \r\n$contents\r\n");
                } else {
                    die(". Log: \r\n$error_contents");
                }
            } else {
                die(". Errorlog empty");
            }
        }
        unlink($encrypted);
    }

    public function set_http_user($user, $pass) {
        if ($user == "")
            die("Empty username specified");
        if ($pass == "")
            die("Empty password specified");
        $this->http_user = $user;
        $this->http_pass = $pass;
    }

    public function set_url($url) {
        if ($url == "")
            die("Empty URL specified");
        $this->http_url = $url;
    }

    public function localtime_to_utc($localtime) {
        $localTimezone = new DateTimeZone(date_default_timezone_get());
        $utcTimezone = new DateTimeZone('UTC');
        $myDateTime = new DateTime($localtime, $localTimezone);
        $myDateTime->setTimezone($utcTimezone);
        return $myDateTime->format('Y-m-d H:i:s');
    }

    public function set_dates_localtime($from, $to) {
        $from = $this->localtime_to_utc($from);
        $to = $this->localtime_to_utc($to);
        echo "Requesting data shared between $from and $to (UTC)..\r\n";
        $this->from_date_utc = urlencode($from);
        $this->to_date_utc = urlencode($to);
    }

    public function set_dates_utc($from, $to) {
        echo "Requesting data shared between $from and $to..(UTC)\r\n";
        $this->from_date_utc = urlencode($from);
        $this->to_date_utc = urlencode($to);
    }

}
