<?php

require_once 'File/MARC.php';

function print_help($message)
{
  $help = <<<EOL
  Help for this script:
  This script is intended to fix colliding shelving locations in a MARC file,
  i.e. where a multi-branch/location system uses the same shelf location names
  at the different branches. While the data must reside in the item record
  already, this script will combine both data into a single subfield.

  Required arguments:
    --item-tag: MARC tag containing item data that we will search
    --branch-subfield: subfield of item tag containing branch data
    --shelf-subfield: subfield of item tag containing shelf location
    --input-file: MARC file to operate on
    --output-file: destination file
    --logfile: report unique shelving locations in a file

  Example usage:
  php combine-item-subfields.php --item-tag=999 --branch-subfield=m
    --shelf-subfield=l --input-file=input.mrc --output-file=output.mrc --logfile=locations.log


EOL;
  print($help);
  print($message);
  exit(1);
}

function finalReport($log, $locs, $items, $records) {
	// Output a summary of unique values for the combined fields
  // and a count of items/records processed
  asort($locs);
  $logText = "-------------------------\n";
  $logText .= "Location Summary\n";
  $logText .= "-------------------------\n";
  foreach ($locs as $loc) {
    $logText .= "$loc\n";
  }
  $logText .= "-------------------------\n";
  $logText .= "Processed $items items in $records records.\n";
  $logText .= "-------------------------\n";
  $logFile = fopen($log, "wb");
  fwrite($logFile, $logText);
  fclose($logFile);
}

$shortopts = "";
$longopts = array(
  "item-tag:", // MARC tag containing item data that we will search
  "branch-subfield:", // subfield of item tag containing branch data
  "shelf-subfield:", // subfield of item tag containing shelf location
  "input-file:", // MARC file to operate on
  "output-file:", // destination file
  "logfile:" // report unique shelving locations
);
$options = getopt($shortopts, $longopts);

// TODO: Fix this test.
if (!$argc == (sizeof($options)+1)) {
  print_help("Incorrect arguments given. Required: ".sizeof($longopts)." Given: ".sizeof($options));
}

$inputFile = $options['input-file'];
$outputFileName = $options['output-file'];
$itemTag = $options['item-tag'];
$branchSubfield = $options['branch-subfield'];
$shelfSubfield = $options['shelf-subfield'];
$logFileName = $options ['logfile'];

try {
	// make sure all the files we will use are available
   if ( !file_exists($inputFile) ) {
     throw new Exception('Input file not found.');
   }

   $outputFile = fopen($outputFileName, "wb");
   if ( !$outputFile ) {
     throw new Exception('Could not open output file for writing.');
   }

   $logFile = fopen($logFileName, "wb");
   if (!$logFile) {
     throw new Exception('Could not open log file for writing');
   }
   fclose($logFile);
 } catch ( Exception $e ) {
   echo "Exiting: ".$e->getMessage();
   exit();
 }

$bibs = new File_MARC($inputFile);
$locationList = array();
$itemCount = 0;
$recordCount = 0;

while ($record = $bibs->next()) {
  foreach($record->getFields($itemTag) as $item => $subfields) {
    $itemCount++;
    foreach ($subfields->getSubfields() as $code => $value) {
      if ($code == $shelfSubfield) {
        $shelfData = $value->getdata();
      } elseif ($code == $branchSubfield) {
        $branchData = $value->getdata();
      }
    }
		// Create a pipe separated set of both values and add to a new field.
		// TODO: add option to specify a subfield other than $z
		// TODO: add check for the possibility that subfield already exists
    $combinedField = "$branchData|$shelfData";
    $subfields->appendSubfield(new File_MARC_Subfield('z', $combinedField));
		// Build the list for the report
    if (!in_array($combinedField, $locationList)) {
      array_push($locationList, $combinedField);
    }
  }

  fwrite($outputFile, $record->toRaw());
  $recordCount++;

	// TODO: could probably add the ability to quiet this output, and the "job done" statement at the end
  if ($recordCount % 10000 == 0) {
    echo "Processing: $itemCount items in $recordCount records so far.\n";
  }
}

fclose($outputFile);

finalReport($logFileName, $locationList, $itemCount, $recordCount);

echo "Job done, check $logFileName for details."
?>
