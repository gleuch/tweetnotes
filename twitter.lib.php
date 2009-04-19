<?php

class Twitter {
	public static $Host;
  private static $Username;
  private static $Password;
  private static $Email;
  private static $RealName;


	public static $Message;
	public static $Result;
	public static $ReturnInfo;
	public static $Send;

	
	private static $Connection = '';
	public static $Debug = false;
	public static $Post = 0;

  public static function Init($user) {
    Twitter::$Username = $user['user'];
    Twitter::$Password = $user['pass'];
    Twitter::$Email = $user['email'];
    Twitter::$RealName = $user['name'];
  }

  public static function NotifyUnfollows() {
    $current_followers = Twitter::Followers();
    $unfollowed = array();
    if (is_array($current_followers) && count($current_followers) > 0) {
      $file = './cache/'. Twitter::$Username .'/followers';
      if (!is_file($file)) touch($file);
      if (!is_writable($file)) chmod($file, 0777);
      $prev_followers = explode(',', file_get_contents($file));
      $ct_prev = count($prev_followers);
      $ct_current = count($current_followers);
      $filetime = filemtime($file);

      if ($ct_prev > 0) {
        foreach ($prev_followers as $k=>$follower) if (!empty($follower) && !in_array($follower, $current_followers)) array_push($unfollowed, $follower);
      }
      $ct_unfollowed = count($unfollowed);

      file_put_contents($file, implode(',', $current_followers));

      if ($ct_unfollowed > 0) {
        $msg = '<p>You had '. $ct_unfollowed .' user'. ($ct_unfollowed != 1 ? 's' : '') .' unfollow %s %s. '. ($ct_unfollowed != 1 ? 'They are' : 'That user is') .':</p><ul><li>'. implode(', ', $unfollowed) .'</li></ul>';
      } else {
        $msg = '<p>You had no users unfollow %s %s.</p>';
      }
      if ($ct_prev != $ct_current || $ct_unfollowed > 0) $msg .= '<p>Your follower count has'. ($ct_unfollowed > 0 ? ' also' : '') .' '. ($ct_prev > $ct_current ? 'dropped' : 'increased') .' by '. abs($ct_prev-$ct_current) .' follower'. (abs($ct_prev-$ct_current) != 1 ? 's' : '') .'.</p>';

      $msg = sprintf($msg, 'your Twitter account \''. Twitter::$Username .'\'', Twitter::FancyTime(date("U")-$filetime, 'in the past %s'));

      if (isset($_GET['web'])) {
        echo $msg;
      } else {
        if ($ct_unfollowed > 0) {
          $name = (!empty(Twitter::$RealName) ? Twitter::$RealName .' ('. Twitter::$Username .')' : (defined('DEFAULT_REAL_NAME') && DEFAULT_REAL_NAME != '' ? DEFAULT_REAL_NAME .' ('. Twitter::$Username .')' : Twitter::$Username));
          if (false) {
            // For text emails
            $msg = 'Hello '. $name .",\n\n". str_replace(array('<br />', '<br/>', '<p>', '</p>', '<ul>', '<li>', '</li>', '</ul>'), array("\n\n", "\n\n", "", "\n\n", "", "   - ", "\n", "\n"), $msg) ."\nThanks,\n\nYour Friendly Twitter Unfollower Notifier";
          } else {
            $msg = '<p>Hello '. $name .',</p>'. $msg .'<p><br />Thanks,</p><p>Your Friendly Twitter Unfollower Notifier</p>';
          }
          Twitter::SendEmail('Unfollow Note for '. Twitter::$Username, $msg);
        }
      }
    }
  }

  public static function SendEmail($subj, $msg) {
    if (!empty(Twitter::$Email)) {
      $email = Twitter::$Email;
    } elseif (defined('DEFAULT_EMAIL') && DEFAULT_EMAIL != '') {
      $email = DEFAULT_EMAIL;
    } else {
      echo 'No email address defined for '. Twitter::$Username .'.';
      return false;
    }
    if (!empty(Twitter::$RealName)) {
      $to = Twitter::$RealName .'<'. $email .'>';
    } elseif (defined('DEFAULT_REAL_NAME') && DEFAULT_REAL_NAME != '') {
      $to = DEFAULT_REAL_NAME .'<'. $email .'>';
    } else {
      $to = $email;
    }
    $from = 'Tweet-note Notifier <'. (defined('DEFAULT_FROM_EMAIL') && DEFAULT_FROM_EMAIL != '' ? DEFAULT_FROM_EMAIL : $email) .'>';

    return mail($to, '[tweet-note] '. $subj, $msg, "From:". $from ."\nReply-to:". $from ."\nContent-Type: text/html; charset=utf-8");
  }

  public static function GetPath($do, $page=false) {
    switch ($do) {
      case "friends": $path = "statuses/friends"; break;
      case "followers": default: $path = "statuses/followers"; break;
    }
    return 'http://twitter.com/'. $path .'.json'. ($page && $page > 0 ? '?page='. $page : '');
  }


  public static function Followers() {
    $size = 100; $page = 1; $followers = array();
    while ($size == 100) {
      Twitter::$Host = Twitter::GetPath('followers', $page);
      Twitter::Start();
      $page_followers = json_decode(strip_tags(Twitter::$Result));
      if (!$page_followers->error) {
        $size = count($page_followers);
        if ($size > 0) foreach ($page_followers as $k=>$follower) array_push($followers, $follower->screen_name);
        if ($size == 100) $page++;
        return false;
      } else {
        echo Twitter::$Username .': '. $page_followers->error;
        return $page_followers->error;
      }
    }
    return $followers;
  }



	public static function Start() {
		Twitter::Connect();
		Twitter::Post();
		Twitter::ReturnInfo();
		Twitter::Disconnect();
	}

	private static function Test() {
		echo 'test: '. Twitter::$Username .' - '. Twitter::$Host;
	}

	private static function Connect() {
		if (Twitter::Debug()) echo 'Starting connection...';
		Twitter::$Connection = curl_init();

		if (!Twitter::Connected()) return;
		if (Twitter::Debug()) echo 'connected!<br />';
		curl_setopt(Twitter::$Connection, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(Twitter::$Connection, CURLOPT_USERPWD, Twitter::$Username .':'. Twitter::$Password);
		curl_setopt(Twitter::$Connection, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt(Twitter::$Connection, CURLOPT_POST, Twitter::$Post);
	}

	private static function Post() {
		if (!Twitter::Connected()) return;
		if (Twitter::Debug()) echo 'Posting...';
		$host = sprintf(Twitter::$Host, Twitter::$Send);
		$host .= (eregi('\?', $host) ? '&' : '?') .'source=twitterart';
		curl_setopt(Twitter::$Connection, CURLOPT_URL, $host);
		Twitter::$Result = curl_exec(Twitter::$Connection);
		if (Twitter::Debug()) echo 'complete!<br />';
	}

	private static function ReturnInfo() {
		if (!Twitter::Connected()) return;
		Twitter::$ReturnInfo = curl_getinfo(Twitter::$Connection);
	}

	private static function Disconnect() {
		if (!Twitter::Connected()) return;
		if (Twitter::Debug()) echo 'Closing connection...';
		curl_close(Twitter::$Connection);
		if (Twitter::Debug()) echo 'closed.';
	}

	private static function Connected() {
		if (Twitter::$Connection) return true;
		if (Twitter::Debug()) echo 'Twitter connection not available.';
		return false;
	}

	private static function Debug() {
		return (Twitter::$Debug === true);
	}

	public static function ErrorCode($code='0') {
		$codes = array(
			'0' => 'Unknown Code',
			'200' => 'OK: everything went awesome.',
			'304' => 'Not Modified: there was no new data to return.',
			'400' => 'Bad Request: your request is invalid, and we\'ll return an error message that tells you why. This is the status code returned if you\'ve exceeded the rate limit (see below).',
			'401' => 'Not Authorized: either you need to provide authentication credentials, or the credentials provided aren\'t valid.',
			'403' => 'Forbidden: we understand your request, but are refusing to fulfill it.  An accompanying error message should explain why.',
			'404' => 'Not Found: either you\'re requesting an invalid URI or the resource in question doesn\'t exist (ex: no such user).',
			'500' => 'Internal Server Error: we did something wrong.  Please post to the group about it and the Twitter team will investigate.',
			'502' => 'Bad Gateway: returned if Twitter is down or being upgraded.',
			'503' => 'Service Unavailable: the Twitter servers are up, but are overloaded with requests. Try again later.',
		);

		return (array_key_exists($code, $codes)) ? $codes[$code] : $codes['0'];
	}


  public static function FancyTime($time, $wording=false) {
    if ($time > 2592000) {
      $t = floor($time/2592000);
      $m = $t .' month'. ($t != 1 ? 's' : '');
    } elseif ($time > 604800) {
      $t = floor($time/604800);
      $m = $t .' week'. ($t != 1 ? 's' : '');
    } elseif ($time > 86400) {
      $t = floor($time/86400);
      $m = $t .' day'. ($t != 1 ? 's' : '');
    } elseif ($time > 3600) {
      $t = floor($time/3660);
      $m = $t .' hour'. ($t != 1 ? 's' : '');
    } elseif ($time > 60) {
      $t = floor($time/60);
      $m = $t .' minute'. ($t != 1 ? 's' : '');
    } elseif ($time > 15) {
      $m = $time .' seconds';
    } else {
      $m = ($wording ? '' : 'a ') .'few seconds';
    }

    return !empty($wording) ? sprintf($wording, $m) : ($time > 0 ? 'in '. $m : $m .' ago');
  }
}

?>