<h1>Welcome to the Home Page!</h1>
<h3>Your name is: <?php echo($first_name . ' ' . $last_name)?></h3>
<p>Your email is: <?php echo($email);?></p>
<p>You last logged in on: <?php echo($last_login);?></p>
<a href="<?php echo site_url().'main/logout';?>">Logout</a>