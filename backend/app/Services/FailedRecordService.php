<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportFailure;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class FailedRecordService
{
    /**
     * Characters that trigger formula execution in spreadsheet tools (Excel, Calc, Sheets).
     */
    protected const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Stream and export failed records into failed_records.csv using chunked/cursor DB reads.
     * Never loads all failed records into memory.
     *
     * @param Import $import
     * @return string|null Relative storage path of generated CSV, or null if no failures
     */
    public function generateFailedCsv(Import $import): ?string
    {
        if ($import->failed_count === 0) {
            return null;
        }

        $directory = 'failures';
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }

        $filename = "failed_records_{$import->id}_" . time() . ".csv";
        $relativePath = "{$directory}/{$filename}";
        $fullPath = Storage::disk('local')->path($relativePath);

        // Open file pointer in write mode
        $filePointer = fopen($fullPath, 'w+');
        if ($filePointer === false) {
            throw new \RuntimeException("Unable to open stream for failed records CSV at {$fullPath}");
        }

        $writer = Writer::createFromStream($filePointer);

        // Insert header row
        $writer->insertOne([
            'row_number',
            'name',
            'email',
            'phone',
            'company',
            'reason',
        ]);

        // Stream failures from database in chunks of 2,000 using cursor
        ImportFailure::where('import_id', $import->id)
            ->orderBy('row_number')
            ->cursor()
            ->each(function (ImportFailure $failure) use ($writer) {
                $writer->insertOne([
                    $failure->row_number,
                    $this->escapeFormulaInjection($failure->name ?? ''),
                    $this->escapeFormulaInjection($failure->email ?? ''),
                    $this->escapeFormulaInjection($failure->phone ?? ''),
                    $this->escapeFormulaInjection($failure->company ?? ''),
                    $this->escapeFormulaInjection($failure->reason ?? ''),
                ]);
            });

        fclose($filePointer);

        $import->update([
            'failed_csv_path' => $relativePath,
        ]);

        return $relativePath;
    }

    /**
     * Escape potential spreadsheet formula injection.
     * If a cell begins with '=', '+', '-', or '@', prepend a single quote (').
     *
     * @param string $value
     * @return string
     */
    public function escapeFormulaInjection(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, self::FORMULA_TRIGGERS, true)) {
            return "'" . $value;
        }

        return $value;
    }
}
