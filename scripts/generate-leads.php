<?php

/**
 * Large CSV Lead Generator for Performance & Scalability Testing.
 *
 * Usage:
 *   php scripts/generate-leads.php 100000
 *   php scripts/generate-leads.php 10k
 *   php scripts/generate-leads.php 100k
 *   php scripts/generate-leads.php 500k
 *   php scripts/generate-leads.php 1m
 *
 * Flags:
 *   --invalid=5     (5% invalid records)
 *   --duplicate=5   (5% duplicate records)
 *   --output=path   (custom output path)
 */

declare(strict_types=1);

$startTime = microtime(true);

// 1. Parse record count argument
$countArg = $argv[1] ?? '100000';
$countArgLower = strtolower(trim($countArg));

if (str_ends_with($countArgLower, 'k')) {
    $totalRecords = (int) (floatval($countArgLower) * 1000);
} elseif (str_ends_with($countArgLower, 'm')) {
    $totalRecords = (int) (floatval($countArgLower) * 1000000);
} else {
    $totalRecords = max(10, (int) $countArg);
}

// 2. Parse options
$invalidPct = 5;
$duplicatePct = 5;
$customOutput = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--invalid=')) {
        $invalidPct = (int) substr($arg, 10);
    } elseif (str_starts_with($arg, '--duplicate=')) {
        $duplicatePct = (int) substr($arg, 12);
    } elseif (str_starts_with($arg, '--output=')) {
        $customOutput = substr($arg, 9);
    }
}

// 3. Resolve destination path
$projectRoot = dirname(__DIR__);
$targetDir = $projectRoot . '/backend/storage/testing';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$formattedCount = $totalRecords >= 1000000 ? ($totalRecords / 1000000) . 'm' : ($totalRecords / 1000) . 'k';
$outputPath = $customOutput ?? "{$targetDir}/leads-{$formattedCount}.csv";

echo "========================================================\n";
echo " Large CSV Lead Generator\n";
echo "========================================================\n";
echo " Target records : " . number_format($totalRecords) . "\n";
echo " Invalid rate   : {$invalidPct}%\n";
echo " Duplicate rate : {$duplicatePct}%\n";
echo " Destination    : {$outputPath}\n";
echo " Generating...\n";

$firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen'];
$lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
$companies = ['Acme Corp', 'Globex International', 'Soylent Inc', 'Initech LLC', 'Umbrella Tech', 'Stark Industries', 'Wayne Enterprises', 'Cyberdyne Systems', 'Hooli', 'Pied Piper', 'Massive Dynamic', 'Oscorp'];
$domains = ['example.com', 'samplecorp.org', 'testlead.net', 'companyhq.io', 'leadpulse.co'];

$fp = fopen($outputPath, 'w');
if ($fp === false) {
    fwrite(STDERR, "Error: Unable to open file for writing at {$outputPath}\n");
    exit(1);
}

// Write Header
fputcsv($fp, ['name', 'email', 'phone', 'company'], ',', '"', '\\');

$validPool = [];
$poolLimit = 2000; // Small fixed pool in memory for duplicate picking
$validCount = 0;
$invalidCount = 0;
$duplicateCount = 0;

for ($i = 1; $i <= $totalRecords; $i++) {
    $rand = random_int(1, 100);

    // Case A: Duplicate record
    if ($rand <= $duplicatePct && !empty($validPool)) {
        $duplicateTemplate = $validPool[array_rand($validPool)];
        fputcsv($fp, [
            $duplicateTemplate['name'],
            $duplicateTemplate['email'], // Duplicate email!
            $duplicateTemplate['phone'],
            $duplicateTemplate['company'],
        ], ',', '"', '\\');
        $duplicateCount++;
        continue;
    }

    // Case B: Deliberate Invalid record
    if ($rand <= ($duplicatePct + $invalidPct)) {
        $invalidType = random_int(1, 5);
        $fn = $firstNames[array_rand($firstNames)];
        $ln = $lastNames[array_rand($lastNames)];
        $comp = $companies[array_rand($companies)];

        switch ($invalidType) {
            case 1: // Missing name
                fputcsv($fp, ['', "invalid.name.{$i}@example.com", '+1-555-' . sprintf('%04d', $i % 9999), $comp], ',', '"', '\\');
                break;
            case 2: // Invalid email format
                fputcsv($fp, ["{$fn} {$ln}", "invalid-email-format-{$i}", '+1-555-' . sprintf('%04d', $i % 9999), $comp], ',', '"', '\\');
                break;
            case 3: // Missing phone
                fputcsv($fp, ["{$fn} {$ln}", "missing.phone.{$i}@example.com", '', $comp], ',', '"', '\\');
                break;
            case 4: // Missing company
                fputcsv($fp, ["{$fn} {$ln}", "missing.comp.{$i}@example.com", '+1-555-' . sprintf('%04d', $i % 9999), ''], ',', '"', '\\');
                break;
            case 5: // Bad phone format
                fputcsv($fp, ["{$fn} {$ln}", "bad.phone.{$i}@example.com", 'ABC-INVALID-PHONE', $comp], ',', '"', '\\');
                break;
        }
        $invalidCount++;
        continue;
    }

    // Case C: Valid record
    $fn = $firstNames[array_rand($firstNames)];
    $ln = $lastNames[array_rand($lastNames)];
    $comp = $companies[array_rand($companies)];
    $domain = $domains[array_rand($domains)];
    $email = strtolower("{$fn}.{$ln}.{$i}@{$domain}");
    $phone = '+1-555-' . sprintf('%04d', $i % 9999);

    $leadRecord = [
        'name' => "{$fn} {$ln}",
        'email' => $email,
        'phone' => $phone,
        'company' => $comp,
    ];

    fputcsv($fp, [
        $leadRecord['name'],
        $leadRecord['email'],
        $leadRecord['phone'],
        $leadRecord['company'],
    ], ',', '"', '\\');

    $validCount++;

    if (count($validPool) < $poolLimit) {
        $validPool[] = $leadRecord;
    }
}

fclose($fp);

$elapsed = microtime(true) - $startTime;
$fileSizeBytes = filesize($outputPath);
$fileSizeMb = round($fileSizeBytes / (1024 * 1024), 2);
$peakMemoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

echo "--------------------------------------------------------\n";
echo " Generation Completed in " . round($elapsed, 2) . "s\n";
echo " File size       : {$fileSizeMb} MB\n";
echo " Peak memory     : {$peakMemoryMb} MB\n";
echo " Valid records   : " . number_format($validCount) . "\n";
echo " Invalid records : " . number_format($invalidCount) . "\n";
echo " Duplicates      : " . number_format($duplicateCount) . "\n";
echo " Total generated : " . number_format($totalRecords) . "\n";
echo " File path       : {$outputPath}\n";
echo "========================================================\n";
