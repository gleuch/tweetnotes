<?php

include_once('config.inc.php');
include_once('twitter.php');



if (!is_dir('./cache')) mkdir('./cache', 0777);



foreach ($twitter as $k=>$user) {
  if (!is_dir('./cache/'. $user['user'])) mkdir('./cache/'. $user['user'], 0777);
  echo '<h3>'. $user['user'] .'</h3>';
  Twitter::Init($user);
  Twitter::NotifyUnfollows();

}