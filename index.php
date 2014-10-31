<?php
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
if (isset($_REQUEST['help'])) {
	function currentPath() {
		$pageURL = $_SERVER['REQUEST_URI'];
		$pos = strpos($pageURL, '?');
		return $pos === false ? $pageURL : substr($pageURL, 0, $pos);
	}
	function server() {
		$pageURL = 'http';
		if ($_SERVER['HTTPS'] == 'on') {$pageURL .= 's';}
		$pageURL .= '://';
		if ($_SERVER['SERVER_PORT'] != '80') {
			$pageURL .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'];
		}
		return $pageURL;
	}
	function fullPath() {
		return server() . currentPath();
	}
?><html>
<head></head>
<body>
Usage: <?php echo fullPath(); ?>?key={KEY}&sheet={SHEET}&range={RANGE}
<br />You may also optionally include a "callback=" paramter to output JSONP
</body>
</head>
<?php } else {
	//Load Api
	$clientLibraryPath = '.'.substr(__DIR__,strlen(getcwd()));
	$oldPath = set_include_path(get_include_path() . PATH_SEPARATOR . $clientLibraryPath);
	require_once 'config.php';
	require_once 'Zend/Loader.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');
	Zend_Loader::loadClass('Zend_Gdata_App_AuthException');
	Zend_Loader::loadClass('Zend_Http_Client');
	
	function jsonGetParam($name, $config, $default = null) {
		if (isset($config->$name)) {
			return $config->$name;
		} else {
			$value = $_REQUEST[$name];
			return isset($value) ? $value : $default;
		}
	}
	function jsonAssertParam($name, $config) {
		$value = jsonGetParam($name, $config);
		if (!isset($value)) {
			unset($error);
			$error->error->message = 'Missing Parameter: '.$name;
			$error->error->code = 2;
			$error->error->type = 'MissingParameterException';
			jsonOutputJSON($error);
			exit;
		}
		return $value;
	}
	function jsonOutputJSON($obj) {
		$json = json_encode($obj);
		if (isset($CALLBACK)) {
			$json = $CALLBACK.'('.$json.');';
		}
		header('Content-Type: '.(isset($CALLBACK)?'application/javascript':'application/json'));
		echo $json;
	}
	
	//Get spreadsheet details
	function jsonRun($config) {
		$CALLBACK = $_REQUEST['callback'];
		$USERNAME = jsonAssertParam('username', $config);
		$PASSWORD = jsonAssertParam('password', $config);
		$KEY = jsonAssertParam('key', $config);
		$SHEET = jsonAssertParam('sheet', $config);
		$RANGE = jsonAssertParam('range', $config);
		$BREAK = jsonGetParam('break', $config);
	
		//Connect to api
		$service = Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
		$client = Zend_Gdata_ClientLogin::getHttpClient($USERNAME, $PASSWORD, $service);
		$spreadsheetService = new Zend_Gdata_Spreadsheets($client);
		$query = new Zend_Gdata_Spreadsheets_CellQuery();
		$query->setSpreadsheetKey($KEY);
		$query->setWorksheetId($SHEET);
		$query->setRange($RANGE);
		$cellFeed = $spreadsheetService->getCellFeed($query);
	
		//Iterate through cells
		$header = array();
		$results = array();
		$current = array();
		$firsttime = true;
		$rowoffset = 0;
		$coloffset = 0;
		foreach ($cellFeed as $cellEntry) {
			$cell = $cellEntry->cell;
			$row = $cell->getRow() - $rowoffset;
			$col = $cell->getColumn() - $coloffset;
			$value = $cell->getText();
			//$value = $cellEntry->cell->getNumericValue();
			if($firsttime) {
				$rowoffset = $row-1;
				$coloffset = $col-1;
				$firstrow = $row;
				$firstcol = $col;
				$lastcol = $col+$cellFeed->getColumnCount()-1;
				$lastrow = $row+$cellFeed->getRowCount()-1;
				$row = 1;
				$col = 1;
				$firsttime=false;
			}
			if ($row == 1) {
				$header[] = $value;
			} else {
				if ($col == 1) {
					if ($BREAK && $value == '') {
						break;
					}
					unset($current);
					$current->$header[$col-1] = $value;
					$results[] = $current;
				} else {
					$current->$header[$col-1] = $value;
				}
			}
		}
		unset($output);
		$output->results = $results;
		return $output;
	}
	if ($config->echo) {
		jsonOutputJSON(jsonRun($config));
	}
}