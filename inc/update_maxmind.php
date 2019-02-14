<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/*
if (! function_exists('cca_3weekly') ):
    function cca_3weekly( $schedules ) {
        $schedules['cca_3weekly'] = array( 'interval' => 3*604800, 'display' => __('Three Weeks') );
        return $schedules;
    }

    add_filter( 'cron_schedules', 'cca_3weekly'); 
endif;

if (defined('CCA_X_MAX_CRON')) add_action( CCA_X_MAX_CRON,  'cca_autoupdate_maxmind' );

if (! function_exists('cca_autoupdate_maxmind') ):
    function cca_do_update_maxmind() {
        if (!defined('CCA_MAXMIND_DATA_DIR')): define('CCA_MAXMIND_DATA_DIR', WP_CONTENT_DIR . '/cca_maxmind_data/'); endif;
        $cca_max_update = new CCAmaxmindUpdate();
        $cca_max_update->save_maxmind(TRUE);
        unset($cca_max_update);
    }
endif;
*/



class CCAmaxmindUpdate {
	protected $max_v2download_url = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz';
    protected $v2_gz = 'GeoLite2-Country.tar.gz';
	protected	$v2_tar = 'GeoLite2-Country.tar';
	protected	$v2_mmdb = CCA_MAX_FILENAME;
	protected $uploadedFile = '';
	protected $mmdbFile ='';
	protected $max_status = array();


  public function __construct() {
	  $this->uploadedFile = CCA_MAXMIND_DATA_DIR . $this->v2_gz;
      $this->mmdbFile = CCA_MAXMIND_DATA_DIR . $this->v2_mmdb;
      $this->max_status = get_option('cc_maxmind_status', array());
      if(empty( $this->max_status['v2_file_date'])) $this->max_status['v2_file_date'] = 0;
  }


  public function get_max_status() {
	  return $this->max_status;
  }


  // return permissions of a directory or file as a 4 character "octal" string
  protected function return_permissions($item) {
    clearstatcache(true, $item);
    $item_perms = @fileperms($item);
  	return empty($item_perms) ? '' : substr(sprintf('%o', $item_perms), -4);	
  }


  protected function dirCreated($theDir){
    // create Maxmind directory if necessary
    if ( ! file_exists($theDir) ){ 
        // then this is the first download, or a new directory location has been defined
        $cca_perms = 0755;
        $item_perms = $this->return_permissions(dirname(__FILE__));  // determine whether 775 folder permissions required e.g. for dedicated server
        if (strlen($item_perms) == 4 && substr($item_perms, 2, 1) == '7') $cca_perms = 0775;
    	if ( ! @mkdir($theDir, $cca_perms, true) ) {
    	    $this->max_status['result_msg'] = __('ERROR: Unable to create directory "') . $theDir . __('" This may be due to your server permission settings.');
		    return FALSE;
		}
    }
	return TRUE;
  }


  protected function backupMax($fileToBack){
    $fileToBack . '.bak';
    if (! file_exists($fileToBack) || filesize($fileToBack) < 800000 ) return TRUE;
		if (! @copy($fileToBack, $fileToBack . '.bak' ) ):
		  $this->max_status['result_msg'] = __('Unable to back-up old Maxmind data - update halted. ');
		  return FALSE;
		endif;
    return TRUE;
  }


  protected function revertToOld($fileToRecover){
    $theBackup = $fileToRecover . '.bak';
    if (! file_exists($theBackup) || filesize($theBackup) < 800000 || ! @copy($theBackup, $fileToRecover) ) :
		$this->max_status['result_msg'] .= __("<br>NOTE: unable to revert to a previous version of the data file.");
        return FALSE;
	endif;
		$this->max_status['result_msg'] .= __('<br>It looks like we were able to revert to an old copy of the file.<br>');
		return TRUE;
  }


  public function save_maxmind($do_email = FALSE) {
	  $this->max_status['health'] = 'fail';
	  $error_prefix = __('Warning; unable to update the Maxmind mmdb data file:<br>');
	  $error_suffix = __('<br>If a previously installed valid Maxmind look-up file exists then it will continue to be used. See CCA "Country" or Country Caching "Support" tabs for more information.');
//if (  $this->dirCreated(CCA_MAXMIND_DATA_DIR) ): // don't upload during test
      if (  $this->dirCreated(CCA_MAXMIND_DATA_DIR) && $this->upload_max_gzfile() ):
	    if ($this->max_status['health'] == 'ok' || $this->gztarExtractMax() ) :
            update_option( 'cc_maxmind_status',  $this->max_status );
    	    return TRUE;
 	    endif;
      endif;

      $this->max_status['result_msg'] = $error_prefix . $this->max_status['result_msg'] . $error_suffix;
      update_option( 'cc_maxmind_status',  $this->max_status );

	  if ($do_email):  
            $subject = __("Error: site:") . get_bloginfo('url') . __(" unable to install latest Maxmind GeoIP file");
            $msg = str_replace('<br />', '' ,  $this->max_status['result_msg']) .  __('<br>Email sent by the CCA or Country Caching plugin ') . date(DATE_RFC2822);	
            @wp_mail( get_bloginfo('admin_email'), $subject, $msg );
      endif;
      return FALSE;
  }  // END save_maxmind_data() 


  public function gztarExtractMax() {
  // extract file from gzip and write to folder
        // decompress from gz
		@unlink(CCA_MAXMIND_DATA_DIR . $this->v2_tar); // phar unzip does not overwrite existing files and would fail
        try {
            $gz = new PharData($this->uploadedFile);
            $gz->decompress(); // extracts tar to same dir
        } catch (Exception $e) {
            $this->max_status['result_msg'] =   __("Error uncompressing tar.gz: ") . $e->getMessage();  // handle errors
    		return FALSE;
        }

        // unarchive from the tar
        try {
            $archive = new PharData(CCA_MAXMIND_DATA_DIR . $this->v2_tar);
            $archive->extractTo(CCA_MAXMIND_DATA_DIR);
        } catch (Exception $e) {
            $this->max_status['result_msg'] =   __("Unable to open tar.gz or Tar archive: ") . $e->getMessage();
    		return FALSE;
        }

        try {
            foreach ($archive as $entry) :
                $tarFolder = basename($entry);
                $extractDir = CCA_MAXMIND_DATA_DIR . $tarFolder;
            endforeach;
            $archive->extractTo(CCA_MAXMIND_DATA_DIR, $tarFolder . '/' . $this->v2_mmdb  ,TRUE);
        } catch (Exception $e) {
            $this->max_status['result_msg'] =   __("Error extracting mmdb from tar archive: ") . $e->getMessage();
    		$this->remove_maxtemp_dir($tarFolder);
    		return FALSE;
        }

        $archive->extractTo(CCA_MAXMIND_DATA_DIR, array($tarFolder . '/LICENSE.txt', $tarFolder . '/COPYRIGHT.txt')  ,TRUE);

        // Copy files to dir we actually want, then remove unwanted sub-dir & contents
        if ( ! empty($tarFolder) && $tarFolder != '/' && $tarFolder != "\\" ):  // then files extracted to subdir - copy to parent
            if (! $this->backupMax($this->mmdbFile) ) :
                $this->max_status['result_msg'] =  __("Unable to back-up old Maxmind mmdb file; update abandoned");
		        $this->remove_maxtemp_dir($tarFolder);
                return FALSE;
            endif;

/*
// maybe add this check?
if ( filesize($extractDir . '/' . $this->v2_mmdb) < 1048576 ):
  $this->max_status['result_msg'] =  __("The extracted mmdb file appears to be too small; update abandoned");
  $this->remove_maxtemp_dir($tarFolder);
  return FALSE;
endif;
*/

            if (! copy($extractDir . '/' . $this->v2_mmdb, $this->mmdbFile) ) :
                $this->max_status['result_msg'] =  __("Error copying Maxmind mmdb file to directory");
			    $this->revertToOld($this->mmdbFile);
			    $this->remove_maxtemp_dir($tarFolder);
                return FALSE;
            endif;
            clearstatcache(true, $this->mmdbFile);
            @rename($extractDir . '/LICENSE.txt', CCA_MAXMIND_DATA_DIR . 'LICENSE.txt');
            @rename($extractDir . '/COPYRIGHT.txt', CCA_MAXMIND_DATA_DIR . 'COPYRIGHT.txt');
        endif;

        $this->remove_maxtemp_dir($tarFolder);
        clearstatcache(true, $this->mmdbFile);
        if(filesize($this->mmdbFile) < 1) :
            $recoveryStatus = $this->revertToOld($extractedFile);
            $this->max_status['result_msg'] =  __('Failed to create a valid Maxmind data file - it appears to be empty. Trying to revert to old version: ') . $recoveryStatus;
            return FALSE;
        endif;

		$this->max_status['result_msg'] =  __('Last Maxmind data update successful');
        $this->max_status['v2_file_date'] = time();
		$this->max_status['health'] = 'ok';
        return TRUE;

  } // gztarExtractMax()


  //  This method retreives the "gzip" from Maxmind and then calls other methods to do the rest of the work
  protected function upload_max_gzfile() {
  	// check if an update is necessary
    clearstatcache(true, $this->mmdbFile);
  	if (file_exists($this->mmdbFile) && filesize($this->mmdbFile) > 1048576) :
      if ( ! empty( $this->max_status['v2_file_date']) && $this->max_status['v2_file_date'] > (time() - 3600) ):
		  $this->max_status['health'] = 'ok';
  		  $this->max_status['result_msg'] = __("As the current file < 1 hour old the last request to update Maxmind Look-up file was ignored");
  	      return TRUE;
  	  endif;
  	endif;

    // open file on server for overwrite by CURL
    if (! $fh = fopen($this->uploadedFile, 'wb')) :
  		$this->max_status['result_msg'] = __("Failed to fopen ") . $this->uploadedFile . __(" for writing: ") .  implode(' | ',error_get_last()) . "<br>";
  		return FALSE;
    endif;

    // Get the "file" from Maxmind
    $ch = curl_init($this->max_v2download_url);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);  // identify as error if http status code >= 400
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'a UA string'); // some servers require a non empty or specific UA
    if( !curl_setopt($ch, CURLOPT_FILE, $fh) ):
  		 $this->max_status['result_msg'] = __('curl_setopt(CURLOPT_FILE) failed for: "') . $this->uploadedFile . '"<br><br>';
  		 return FALSE;
  	endif;

    curl_exec($ch);
    if(curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200 ) :
    	fclose($fh);
  		$this->max_status['result_msg'] = __('File upload (CURL) error: ') . curl_error($ch) . __(' for ') . $this->max_v2download_url . ' (HTTP status ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ')';
        curl_close($ch);
  		return FALSE;
    endif;

    curl_close($ch);
    fflush($fh);
    fclose($fh);

    if( filesize($this->uploadedFile) < 1048576 ) :
  		$this->max_status['result_msg'] = __('CURL file transfer completed but the file to uncompress is empty, too small, or non-existent. ') . '(' . $this->uploadedFile . ').<br><br>';
      return FALSE;
    endif;

    $this->max_status['result_msg'] = __('Maxmind V2 data updated.');

    return TRUE;

  }  // END  upload_max_gzfile()


	protected function remove_maxtemp_dir($tarFolder) {
		if ( ! empty($tarFolder) && $tarFolder != '/' && $tarFolder != "\\" ): // we dont want to del maxdata dir
    		$extractDir = CCA_MAXMIND_DATA_DIR . $tarFolder;
    	    @unlink($extractDir . '/' . $this->v2_mmdb);
	        @unlink($extractDir . '/LICENSE.txt');
	        @unlink($extractDir . '/COPYRIGHT.txt');
	        if ( file_exists($extractDir) === TRUE && ! rmdir($extractDir)) : $this->max_status['nb'] = __('Unable to delete temp dir "') . $extractDir . '" after Maxmind update.'; endif;
		endif;
	}

}  // end class