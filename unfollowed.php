<?php
/*
  tweetnotes
  ----------------------------------------------------------------------------------------------------------------

  A nifty script send notification you when people unfollow you on Twitter.
  More information at http://gleuch.com/projects/tweetnotes -or- http://github.com/gleuch/tweetnotes

  ----------------------------------------------------------------------------------------------------------------
  Released under Creative Common License Attribution-Noncommercial-Share Alike 3.0
  
*/


if (!is_file('config.inc.php')) {
  echo '<H1>Go create your config.inc.php file. For more instructions, check out the README file or visit <a href="http://github.com/gleuch/tweetnotes">github.com/gleuch/tweetnotes</a>.';
  exit;
}

include_once('config.inc.php');
include_once('twitter.lib.php');

if (!is_dir('./cache')) mkdir('./cache', 0777);

foreach ($twitter as $k=>$user) {
  if (!is_dir('./cache/'. $user['user'])) mkdir('./cache/'. $user['user'], 0777);
  echo '<h3>'. $user['user'] .'</h3>';
  Twitter::Init($user);
  Twitter::NotifyUnfollows();

}

?>