<h1 class="page-header">LOGIN</h1>

<form action="" method="POST">
	<ul>
		<li><input type="hidden" name="form_name" value="login"></li>
		<li><input type="text" name="email" placeholder="email"></li>
		<li><input type="password" name="password" placeholder="password"></li>
		<li><button type="submit">Sign In</button></li>
	</ul>
</form>
<?php if (isset($login_status)) : ?>
    <?= $login_status ?>
<?php endif; ?>

<p>Forgotten password? <a href="index.php?page=password_reset">Reset</a></p>



<p>or create an account: </p>
<form action="" method="GET">
	<ul>
		<li><input type="hidden" name="page" value="register"></li>
		<li><button type="submit">Register</button></li>
	</ul>
</form>

<br />
<br />

<form action="" method="GET">
	<ul>
		<li><input type="hidden" name="page" value="standings"></li>
		<li><button type="submit">View Standings</button></li>
	</ul>
</form>
