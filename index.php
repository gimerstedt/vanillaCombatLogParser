<?php
// die if invalid file
if(isset($_GET['f']) && !is_numeric($_GET['f']))
	die('boop.');

// read files
$files = array();
if ($handle = opendir('.')) {
    while (false !== ($entry = readdir($handle))) {
    	if($entry != "index.php" && $entry != "." && $entry != "..")
	    	array_push($files, array("name"=>$entry, "size"=>filesize($entry)));
    }
    closedir($handle);
}

// some variables
$debug = "";
$html = "<!doctype html><meta charset=\"utf-8\"><title>meep</title>";
$html .= '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">';
$html .= '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css">';
$html .= '<style>html{width:750px;margin:auto;}</style>';

if(isset($_GET['f'])){
	$d = new CLogData($files[$_GET['f']]['name']);
	$html .= <<<EOD
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
  google.load("visualization", "1", {packages:["corechart"]});
  google.setOnLoadCallback(drawChart);
  function drawChart() {

    var data = google.visualization.arrayToDataTable([
      ['Type', 'Total hits'],
      ['Hits', $d->nrHits],
      ['Crits', $d->nrCrits],
      ['Glancing blows', $d->nrGlancings],
      ['Blocked hits', $d->nrBlockedHits],
      ['Dodged hits', $d->nrDodgedHits],
      ['Parried hits', $d->nrParriedHits],
      ['Missed hits', $d->nrMissedHits]
    ]);

    var options = {
      title: 'Hit table',
      is3D: true,
    };

    var chart = new google.visualization.PieChart(document.getElementById('piechart'));

    chart.draw(data, options);
  }
</script>
<h1><button type="button" class="btn btn-default"><a href="index.php">Back</a></button> $d->name</h1>
<table class="table table-striped"><tr><th>name</th><th>amount</th><th>avg</th><th>misc</th><th>% of total</th></tr>
EOD;
	$html .= tableRow("hits", $d->nrHits, $d->avghit, "", $d->hitPercentage);
	$html .= tableRow("crits", $d->nrCrits, $d->avgcrit, "", $d->critPercentage);
	$html .= tableRow("glancings", $d->nrGlancings, $d->avgglancing, $d->reduction."% of avg hit", $d->glancingPercentage);
	$html .= tableRow("blocked hits", $d->nrBlockedHits, $d->blockedavg, $d->blockamountavg." blocked", $d->blockedHitPercentage);
	$html .= tableRow("dodged hits", $d->dodgedHits, 0,"", $d->dodgedHitPercentage);
	$html .= tableRow("parried hits", $d->parriedHits, 0,"", $d->parriedHitPercentage);
	$html .= tableRow("missed hits", $d->missedHits, 0, "", $d->missedHitPercentage);
	$html .= tableRow("total", $d->total, $d->avgTotal,"", 100);
	$html .= "</table>";
	$html .= '<div id="piechart" style="width: 900px; height: 500px;"></div>';
	$html .= $debug;
}else{
	$html .= '<h1>combat logs</h1>';
	$html .= '<table class="table"><tr><th>name</th><th>size</th><th>view</th></tr>';
	foreach ($files as $key => $value) {
		$html .= "<tr><td><a href=\"?f={$key}\">{$value['name']}</a></td><td>".humanFileSize($value['size'])."</td><td><a href=\"{$value['name']}\">view</a></td></tr>";
	}
}

echo $html;

function wl($nr, $name){
	return '<li><a href="?f='.$nr.'">'.$name.'</a></li>';
}

function tableRow($name, $amount, $avg, $misc, $percentage){
	return "<tr><td>{$name}</td><td>{$amount}</td><td>{$avg}</td><td>{$misc}</td><td>{$percentage}</tr>";
}

function getPercentage($type, $totala){
	return round($type/$totala*100, 2);
}

function humanFileSize($size,$unit="") {
	if( (!$unit && $size >= 1<<30) || $unit == "GB")
		return number_format($size/(1<<30),2)."GB";
	if( (!$unit && $size >= 1<<20) || $unit == "MB")
		return number_format($size/(1<<20),2)."MB";
	if( (!$unit && $size >= 1<<10) || $unit == "KB")
		return number_format($size/(1<<10),2)."KB";
	return number_format($size)." bytes";
}

class CLogData {
	public $name = "";
	public $total = 0;
	public $hits = array();
	public $crits = array();
	public $glancings = array();
	public $blockedHits = array();
	public $dodgedHits = 0;
	public $parriedHits = 0;
	public $missedHits = 0;

	public $nrHits = 0;
	public $nrCrits = 0;
	public $nrGlancings = 0;
	public $nrBlockedHits = 0;
	public $nrDodgedHits = 0;
	public $nrParriedHits = 0;
	public $nrMissedHits = 0;

	public $hitPercentage = 0;
	public $critPercentage = 0;
	public $glancingPercentage = 0;
	public $blockedHitPercentage = 0;
	public $dodgedHitPercentage = 0;
	public $parriedHitPercentage = 0;
	public $missedHitPercentage = 0;

	public $avghit = 0;
	public $avgcrit = 0;
	public $avgglancing = 0;
	public $blockedavg = 0;
	public $blockamountavg = 0;

	public $reduction = 0;
	public $avgTotal = 0;

	function __construct($name) {
		$this->name = $name;
		$this->readCombatLogData($name);
		$this->calcVars();
	}

	private function calcVars(){
		$this->nrHits = count($this->hits);
		$this->nrCrits = count($this->crits);
		$this->nrGlancings = count($this->glancings);
		$this->nrBlockedHits = count($this->blockedHits);
		$this->nrDodgedHits = $this->dodgedHits;
		$this->nrParriedHits = $this->parriedHits;
		$this->nrMissedHits = $this->missedHits;

		$this->hitPercentage = round($this->nrHits/$this->total*100, 2);
		$this->critPercentage = round($this->nrCrits/$this->total*100, 2);
		$this->glancingPercentage = round($this->nrGlancings/$this->total*100, 2);
		$this->blockedHitPercentage = round($this->nrBlockedHits/$this->total*100, 2);
		$this->dodgedHitPercentage = round($this->nrDodgedHits/$this->total*100, 2);
		$this->parriedHitPercentage = round($this->nrParriedHits/$this->total*100, 2);
		$this->missedHitPercentage = round($this->nrMissedHits/$this->total*100, 2);

		$this->avghit = round(array_sum($this->hits)/$this->nrHits, 2);
		$this->avgcrit = round(array_sum($this->crits)/$this->nrCrits, 2);
		$this->avgglancing = round(array_sum($this->glancings)/$this->nrGlancings, 2);
		$this->reduction = round(100*$this->avgglancing/$this->avghit, 2);

		$this->avgTotal = array_sum($this->hits)+array_sum($this->crits)+array_sum($this->glancings)+array_sum($this->blockedHits);
		$this->avgTotal = round($this->avgTotal/$this->total, 2);

		foreach ($this->blockedHits as $key => $value) {
			$this->blockedavg += $value[0];
			$this->blockamountavg += $value[1];
		}
		$this->blockedavg = $this->blockedavg/$this->nrBlockedHits;
 		$this->blockamountavg = $this->blockamountavg/$this->nrBlockedHits;
 	}

	private function readCombatLogData($name){
		$d = fopen($name, "r") or die("Unable to open file!");
		while(!feof($d)) {
			$l = fgets($d);
			// hits
			if(preg_match("/You hit.*for (?P<amount>[0-9]+)..\n/", $l, $res)){
				$this->total++;
				array_push($this->hits, $res['amount']);
			}
			// crits
			if(preg_match("/You crit.*for (?P<amount>[0-9]+)..\n/", $l, $res)){
				$this->total++;
				array_push($this->crits, $res['amount']);
			}
			// glancings
			if(preg_match("/You hit.*for (?P<amount>[0-9]+).*\(glancing\).*/", $l, $res)){
				$this->total++;
				array_push($this->glancings, $res['amount']);
			}
			// blocked hits
			if(preg_match("/You hit.*for (?P<amount>[0-9]+).*\((?P<blocked>[0-9]+) blocked\).*/", $l, $res)){
				$this->total++;
				array_push($this->blockedHits, array($res['amount'], $res['blocked']));
			}
			// dodged hits
			if(preg_match("/You attack.*dodges/", $l)){
				$this->total++;
				$this->dodgedHits++;
			}
			// parried hits
			if(preg_match("/You attack.*parries/", $l)){
				$this->total++;
				$this->parriedHits++;
			}
			// missed hits
			if(preg_match("/You miss/", $l)){
				$this->total++;
				$this->missedHits++;
			}
		}
		fclose($d);
	}
}