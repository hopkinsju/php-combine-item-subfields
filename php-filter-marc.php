<?php

// php php-filter-marc.php
// --item-tag 999
// --branch-subfield m
// --shelf-subfield l
// --input-file input.mrc
// --output-file output.mrc

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

  Example usage:
  php php-filter-marc.php --item-tag=999 --branch-subfield=m
    --shelf-subfield=l --input-file=input.mrc --output-file=output.mrc


EOL;
  print($help);
  print($message);
  exit(1);
}

$shortopts = "";
$longopts = array(
  "item-tag:", // MARC tag containing item data that we will search
  "branch-subfield:", // subfield of item tag containing branch data
  "shelf-subfield:", // subfield of item tag containing shelf location
  "input-file:", // MARC file to operate on
  "output-file:" // destination file
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

$bibs = new File_MARC($options['input-file']);

// $record = $bibs->next();
//$items = $record->getFields($itemTag);

$outputFile = fopen($outputFileName, "wb");
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
    $combinedField = "$branchData|$shelfData";
    $subfields->appendSubfield(new File_MARC_Subfield('z', $combinedField));
    if (!in_array($combinedField, $locationList)) {
      array_push($locationList, $combinedField);
    }
  }
  fwrite($outputFile, $record->toRaw());
  $recordCount++;
  echo "Processed $itemCount items in $recordCount records.\n";
}

fclose($outputFile);
$locationList = asort($locationList);
foreach ($locationList as $loc) {
  print "$loc\n";
}
print "Processed $itemCount items in $recordCount records.\n";


?>
