<?php
if (!defined('ATHENA_ROOT')) exit;

$this->loginRequired();

$title = 'List Accounts';

if (Athena::config('AutoRemoveTempBans')) {
	$sql = "UPDATE {$server->loginDatabase}.login SET unban_time = 0 WHERE unban_time <= UNIX_TIMESTAMP()";
	$sth = $server->connection->getStatement($sql);
	$sth->execute();
}

$useMD5         = $server->loginServer->config->get('UseMD5');
$searchMD5      = Athena::config('AllowMD5PasswordSearch') && Athena::config('ReallyAllowMD5PasswordSearch') && $auth->allowedToSearchMD5Passwords;
$searchPassword = (($useMD5 && $searchMD5) || !$useMD5) && $auth->allowedToSeeAccountPassword;
$showPassword   = !$useMD5 && $auth->allowedToSeeAccountPassword;
$bind           = array();
$creditsTable   = Athena::config('AthenaTables.CreditsTable');
$creditColumns  = 'credits.balance, credits.last_donation_date, credits.last_donation_amount';
$accountTable   = Athena::config('AthenaTables.AccountCreateTable');
$accountColumns = 'createlog.reg_date';
$createTable    = Athena::config('AthenaTables.AccountCreateTable');
$createColumns  = 'created.confirmed, created.confirm_code, created.reg_date';
$sqlpartial     = "LEFT OUTER JOIN {$server->loginDatabase}.{$creditsTable} AS credits ON login.account_id = credits.account_id ";
$sqlpartial    .= "LEFT OUTER JOIN {$server->loginDatabase}.{$accountTable} AS createlog ON login.account_id = createlog.account_id ";
$sqlpartial    .= "LEFT OUTER JOIN {$server->loginDatabase}.{$createTable} AS created ON login.account_id = created.account_id ";
$sqlpartial    .= "WHERE login.sex != 'S' AND login.group_id >= 0 ";

$accountID = $params->get('account_id');
if ($accountID) {
	$sqlpartial .= "AND login.account_id = ?";
	$bind[]      = $accountID;
}
else {
	$opMapping      = array('eq' => '=', 'gt' => '>', 'lt' => '<');
	$opValues       = array_keys($opMapping);
	$username       = $params->get('username');
	$password       = $params->get('password');
	$email          = $params->get('email');
	$lastIP         = $params->get('last_ip');
	$gender         = $params->get('gender');
	$accountState   = $params->get('account_state');
	$accountLevelOp = $params->get('account_level_op');
	$accountLevel   = $params->get('account_level');
	$balanceOp      = $params->get('balance_op');
	$balance        = $params->get('balance');
	$loginCountOp   = $params->get('logincount_op');
	$loginCount     = $params->get('logincount');
	$lastLoginDateA = $params->get('last_login_after_date');
	$lastLoginDateB = $params->get('last_login_before_date');
	
	if ($username) {
		$sqlpartial .= "AND (login.userid LIKE ? OR login.userid = ?) ";
		$bind[]      = "%$username%";
		$bind[]      = $username;
	}
	
	if ($searchPassword && $password) {
		if ($useMD5) {
			$sqlpartial .= "AND login.user_pass = MD5(?) ";
			$bind[]      = $password;
		}
		else {
			$sqlpartial .= "AND (login.user_pass LIKE ? OR login.user_pass = ?) ";
			$bind[]      = "%$password%";
			$bind[]      = $password;
		}
	}
	
	if ($email) {
		$sqlpartial .= "AND (login.email LIKE ? OR login.email = ?) ";
		$bind[]      = "%$email%";
		$bind[]      = $email;
	}
	
	if ($lastIP) {
		$sqlpartial .= "AND (login.last_ip LIKE ? OR login.last_ip = ?) ";
		$bind[]      = "%$lastIP%";
		$bind[]      = $lastIP;
	}
	
	if (in_array($gender, array('M', 'F'))) {
		$sqlpartial .= "AND login.sex = ? ";
		$bind[]      = $gender;
	}
	
	if ($accountState) {
		if ($accountState == 'normal') {
			$sqlpartial .= 'AND (login.state = 0 AND login.unban_time = 0 AND (created.confirmed = 1 OR created.confirmed IS NULL)) ';
		}
		elseif ($accountState == 'pending') {
			$sqlpartial .= 'AND (created.confirmed = 0 AND created.confirm_code IS NOT NULL) ';
		}
		elseif ($accountState == 'permabanned') {
			$sqlpartial .= 'AND (login.state = 5 AND login.unban_time = 0 AND (created.confirmed = 1 OR created.confirm_code IS NULL)) ';
		}
		elseif ($accountState == 'banned') {
			$sqlpartial .= 'AND login.unban_time > 0 ';
		}
	}
	
	if (in_array($accountLevelOp, $opValues) && trim($accountLevel) != '') {
		$op          = $opMapping[$accountLevelOp];
		$sqlpartial .= "AND login.group_id $op ? ";
		$bind[]      = $accountLevel;
	}
	
	if (in_array($balanceOp, $opValues) && trim($balance) != '') {
		$op  = $opMapping[$balanceOp];
		if ($op == '=' && $balance === '0') {
			$sqlpartial .= "AND (credits.balance IS NULL OR credits.balance = 0) ";
		}
		else {
			$sqlpartial .= "AND credits.balance $op ? ";
			$bind[]      = $balance;
		}
	}
	
	if (in_array($loginCountOp, $opValues) && trim($loginCount) != '') {
		$op          = $opMapping[$loginCountOp];
		$sqlpartial .= "AND login.logincount $op ? ";
		$bind[]      = $loginCount;
	}
	
	if ($lastLoginDateB && ($timestamp = strtotime($lastLoginDateB))) {
		$sqlpartial .= 'AND login.lastlogin < ? ';
		$bind[]      = date('Y-m-d', $timestamp);
	}
	
	if ($lastLoginDateA && ($timestamp = strtotime($lastLoginDateA))) {
		$sqlpartial .= 'AND login.lastlogin > ? ';
		$bind[]      = date('Y-m-d', $timestamp);
	}
}

$sql  = "SELECT COUNT(login.account_id) AS total FROM {$server->loginDatabase}.login $sqlpartial";
$sth  = $server->connection->getStatement($sql);
$sth->execute($bind);

$paginator = $this->getPaginator($sth->fetch()->total);
$paginator->setSortableColumns(array(
	'login.account_id' => 'asc', 'login.userid', 'login.user_pass',
	'login.sex', 'group_id', 'state', 'balance',
	'login.email', 'logincount', 'lastlogin', 'last_ip',
	'reg_date'
));

$sql  = $paginator->getSQL("SELECT login.*, {$creditColumns}, {$accountColumns}, {$createColumns} FROM {$server->loginDatabase}.login $sqlpartial");
$sth  = $server->connection->getStatement($sql);
$sth->execute($bind);

$accounts   = $sth->fetchAll();

$authorized = $auth->actionAllowed('account', 'view') && $auth->allowedToViewAccount;

if ($accounts && count($accounts) === 1 && $authorized && Athena::config('SingleMatchRedirect')) {
	$this->redirect($this->url('account', 'view', array('id' => $accounts[0]->account_id)));
}
?>