<?php

namespace App\Services;

use App\Exceptions\ImportFileException;
use Generator;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class QuestionFileReader
{
    /** @return Generator<int, list<string>> */
    public function rows(string $path): Generator
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                throw new ImportFileException('Unable to open import file.');
            }
            try {
                $number = 0;
                while (($row = fgetcsv($stream, 262144, ',', '"', '')) !== false) {
                    yield ++$number => array_map(fn ($value): string => trim((string) $value), $row);
                }
            } finally {
                fclose($stream);
            }

            return;
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new ImportFileException('Only CSV and XLSX files are supported.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ImportFileException('Invalid XLSX file.');
        }
        $bytes = 0;
        try {
            if ($zip->numFiles > 5000) {
                throw new ImportFileException('Workbook contains too many files.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $bytes += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if ($bytes > 100 * 1024 * 1024) {
                throw new ImportFileException('Expanded workbook exceeds the 100 MB safety limit.');
            }
        } finally {
            $zip->close();
        }
        $reader = new Reader;
        try {
            $reader->open($path);
            $number = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$number => array_map(fn ($value): string => is_scalar($value) ? trim((string) $value) : '', $row->toArray());
                }
                break; // Only the first sheet is an import; prevents ambiguous repeated headers.
            }
        } finally {
            $reader->close();
        }
    }
}
