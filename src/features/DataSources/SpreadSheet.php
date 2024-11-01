<?php

namespace UkrSolution\UpcEanGenerator\features\DataSources;

use UkrSolution\UpcEanGenerator\Database;
use UkrSolution\UpcEanGenerator\Exceptions\GeneratorException;
use UkrSolution\UpcEanGenerator\features\DataSources\Filters\PhpExcelChunkReadFilter;

class SpreadSheet
{
    protected $wpdb;
    protected $filesTable;
    protected $codesTable;
    protected $chunkSize = 100;
    protected $validCodesNumberLength = array(8, 12, 13, 14);

    protected $csvDelimiter = ',';

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->filesTable = $wpdb->prefix . Database::$tableUploads;
        $this->codesTable = $wpdb->prefix . Database::$tableCodes;

        add_filter('upload_dir', array($this, 'upload_dir'));
    }

    public function uploadDataFile()
    {
        try {
            $uploadedFile = wp_handle_upload($_FILES['spreadsheetFile'], array(
                'test_form' => false,
                'mimes'     => array(
                    'csv'  => 'text/csv',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'xls'  => 'application/vnd.ms-excel',
                ),
            ));

            if (!empty($uploadedFile['error'])) {
                wp_send_json_error($uploadedFile['error']);
            }

            $sheetData = $this->getDataFromFile($uploadedFile['file'], 1, $this->chunkSize);
            $codeColumnInfo = $this->getCodeColumnInfo($sheetData);
            $fileMd5 = md5_file($uploadedFile['file']);

            $existedFileInfo = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT * FROM $this->filesTable WHERE `file_md5` = %s", $fileMd5)
            );
            if (!empty($existedFileInfo)) {
                throw new GeneratorException(__('File is already uploaded', 'upc-ean-generator'));
            }

            $insertResult = $this->wpdb->insert($this->filesTable, array(
                'file_name' => basename($uploadedFile['file']),
                'file_md5' => $fileMd5,
            ));

            if (false === $insertResult) {
                throw new GeneratorException('File information not saved to db');
            }
            $fileId = $this->wpdb->insert_id;

            wp_send_json_success(array(
                'startRow' => $codeColumnInfo['startRow'],
                'codeCol' => $codeColumnInfo['codeCol'],
                'csvDelimiter' => $this->csvDelimiter,
                'fileId' => $fileId,
                'filePath' => $uploadedFile['file'],
            ));
        } catch (\PHPExcel_Exception $e) {
            wp_send_json_success(array('error_message' => $e->getMessage()));
        } catch (GeneratorException $e) {
            wp_send_json_success(array('error_message' => $e->getMessage()));
        }
    }

    public function importFromFile()
    {
        try {
            $filePath = sanitize_text_field($_POST['filePath']);
            $codeType = sanitize_text_field($_POST['codeType']);
            $startRow = sanitize_text_field($_POST['startRow']);
            $csvDelimiter = sanitize_text_field($_POST['csvDelimiter']);
            $codeCol = sanitize_text_field($_POST['codeCol']);
            $fileId = sanitize_text_field($_POST['fileId']);

            $sheetData = $this->getDataFromFile($filePath, $startRow, $this->chunkSize, $csvDelimiter);
            $importedCodesCount = $this->importDataToDb(wp_list_pluck($sheetData, $codeCol), $codeType, $fileId);

            $count = count($sheetData);

            if ($count < $this->chunkSize) {
                wp_delete_file($filePath);
                wp_send_json_success(array(
                    'processed' => $count,
                    'imported' => $importedCodesCount,
                    'isComplete' => true,
                    'error_message' => '',
                ));
            } else {
                wp_send_json_success(array(
                    'isComplete' => false,
                    'processed' => $count,
                    'imported' => $importedCodesCount,
                    'startRow' => (int) $startRow + (int) $this->chunkSize,
                    'csvDelimiter' => $csvDelimiter,
                    'codeCol' => $codeCol,
                    'fileId' => $fileId,
                    'filePath' => $filePath,
                    'error_message' => '',
                ));
            }
        } catch (\PHPExcel_Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    protected function getDataFromFile($filePath, $startRow, $chunkSize, $csvDelimiter = null)
    {
        $inputFileType = \PHPExcel_IOFactory::identify($filePath);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);

        if ('CSV' === $inputFileType) {
            $csvDelimiter = null !== $csvDelimiter ? $csvDelimiter : $this->determineCsvDelimiter($filePath, $chunkSize);
            $objReader->setDelimiter($csvDelimiter);
            $this->csvDelimiter = $csvDelimiter;
        }

        $chunkFilter = new PhpExcelChunkReadFilter();
        $chunkFilter->setRows($startRow, $chunkSize);
        $objReader->setReadFilter($chunkFilter);
        $objReader->setReadDataOnly(true);

        $objPHPExcel = $objReader->load($filePath);
        $highestColumn = $objPHPExcel->getActiveSheet()->getHighestColumn();
        $highestDataRow = $objPHPExcel->getActiveSheet()->getHighestDataRow();
        $endRow = $startRow + $chunkSize - 1;
        if ($endRow > $highestDataRow) {
            $endRow = $highestDataRow;
        }
        $sheetData = $objPHPExcel->getActiveSheet()->rangeToArray(
            "A{$startRow}:{$highestColumn}{$endRow}",
            null,
            false,
            false,
            true
        );

        return $sheetData;
    }

    protected function determineCsvDelimiter($filePath, $chunkSize)
    {
        $allowedEnclosures = array('"', "'");
        $allowedDelimiters = array(";", ",", "|", "\t");
        $encloseResults = array();
        $delimiterResults = array();
        $results = array();

        $handle = fopen($filePath, 'r');
        for ($i = 0; $i < $chunkSize; $i++) {
            $line = fgets($handle);
            foreach ($allowedEnclosures as $enclosure) {
                $count = preg_match_all('/' . $enclosure . '/', $line);
                if ($count === 0 || $count % 2 !== 0) {
                    continue;
                }
                if (empty($encloseResults[$enclosure])) {
                    $encloseResults[$enclosure] = 0;
                }
                $encloseResults[$enclosure]++;
            }
        }
        fclose($handle);
        arsort($encloseResults, SORT_NUMERIC);
        $currentEnclosure = empty($encloseResults) ? null : key($encloseResults);


        $handle = fopen($filePath, 'r');
        for ($i = 0; $i < $chunkSize; $i++) {
            $line = fgets($handle);
            foreach ($allowedDelimiters as $delimiter) {
                $count = count(explode($delimiter, $line));
                if ($count === 1) { 
                    continue;
                }
                $exploded = explode($delimiter, $line);
                if ($currentEnclosure) {
                    foreach ($exploded as $value) { 
                        $match = preg_match_all('/\A[^' . $currentEnclosure . ']+'.
                            $currentEnclosure . '[^' . $currentEnclosure . ']+\z/',
                            $value);
                        if ($match) {
                            continue 2;
                        }
                    }
                }
                if (empty($delimiterResults[$delimiter])) { 
                    $delimiterResults[$delimiter] = array();
                }
                $delimiterResults[$delimiter][] = $count;
            }
        }
        fclose($handle);

        if (!empty($delimiterResults)) {
            foreach ($delimiterResults as $delimiter => $value) {
                $flipped = array_flip($value);
                $results[$delimiter] = count($flipped);
            }
            arsort($results, SORT_NUMERIC);
            $detectedDelimiter = key($results);
            if (!in_array($detectedDelimiter, $allowedDelimiters)) {
                $detectedDelimiter = ';';
            }
        } else {
            $detectedDelimiter = ';';
        }

        return $detectedDelimiter;
    }

    protected function importDataToDb($codes, $codeType, $fileId)
    {
        $codesAdded = 0;

        $isWpdbShowErrors = $this->wpdb->hide_errors();
        foreach ($codes as $code) {
            try {
                $insertResult = $this->wpdb->insert($this->codesTable, array('code' => $code, 'file_id' => $fileId));
                if (false !== $insertResult) {
                    $codesAdded++;
                }
            } catch (\Exception $e) {
            }
        }

        if ($isWpdbShowErrors) {
            $this->wpdb->show_errors();
        }

        return $codesAdded;
    }

    protected function getCodeColumnInfo($data)
    {
        $startRow = null;
        $codeCol = null;
        $maxCodesInColumnFound = 0;
        $columnsCodesInfo = array();

        foreach ($data as $rowKey => $row) {
            foreach ($row as $colKey => $colValue) {
                $colValue = (string)$colValue;
                $colValue = trim($colValue);
                $numLength = strlen($colValue);
                if (ctype_digit($colValue) && in_array($numLength, $this->validCodesNumberLength)) {
                    if (!isset($columnsCodesInfo[$colKey])) {
                        $columnsCodesInfo[$colKey] = array('codesFound' => 1, 'startRow' => $rowKey, 'codeCol' => $colKey);
                    } else {
                        $columnsCodesInfo[$colKey]['codesFound']++;
                    }
                }
            }
        }

        if (empty($columnsCodesInfo)) {
            throw new GeneratorException('Code values not found.');
        }

        foreach ($columnsCodesInfo as $columnCodesInfo) {
            if ($columnCodesInfo['codesFound'] > $maxCodesInColumnFound) {
                $maxCodesInColumnFound = $columnCodesInfo['codesFound'];
                $startRow = $columnCodesInfo['startRow'];
                $codeCol = $columnCodesInfo['codeCol'];
            }
        }

        return compact('startRow', 'codeCol');
    }

    public function getImportedFilesInfo($getResult = true)
    {
        $result = $this->wpdb->get_results(
                "
                    SELECT
                      f.*,
                      COALESCE(count(c.`code`), 0) AS `total_codes`,
                      SUM(if(c.`is_used` = 1, 1, 0)) AS `used_codes`,
                      c.`type` AS `code_type`
                    FROM
                      `$this->filesTable` f
                      LEFT JOIN `$this->codesTable` c
                        ON c.`file_id` = f.`id`
                    GROUP BY f.`id`
                "
        );

        if (!empty($getResult)) {
            return $result;
        } else {
            wp_send_json_success($result, null, JSON_NUMERIC_CHECK);
        }
    }

    protected function getImportedFileInfo($fileId)
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "
                    SELECT
                      f.*,
                      COALESCE(count(c.`code`), 0) AS `total_codes`,
                      SUM(if(c.`is_used` = 1, 1, 0)) AS `used_codes`,
                      c.`type` AS `code_type`
                    FROM
                      `$this->filesTable` f
                      LEFT JOIN `$this->codesTable` c
                        ON c.`file_id` = f.`id`
                      WHERE c.`file_id` = %d
                    GROUP BY f.`id`
                ",
                $fileId
            )
        );

        return $result;
    }

    public function deleteImportedFileData()
    {
        $fileId = sanitize_text_field($_POST['fileId']);

        $fileInfo = $this->getImportedFileInfo($fileId);
        if (isset($fileInfo->used_codes) && intval($fileInfo->used_codes) !== 0) {
            wp_send_json_error(__('Data cannot be deleted, file codes already in use', "upc-ean-generator"));
        }

        $deleteCodesResult = $this->wpdb->delete($this->codesTable, array('file_id' => $fileId));

        if (false === $deleteCodesResult) {
            wp_send_json_error(__('Delete codes error.', "upc-ean-generator"));
        }

        $deleteFileResult = $this->wpdb->delete($this->filesTable, array('id' => $fileId));

        if (false === $deleteFileResult) {
            wp_send_json_error(__('Delete file data error.', "upc-ean-generator"));
        }

        wp_send_json_success();
    }

    public function getAssignedCodesFromFile($fileId)
    {
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "
                    SELECT
                      c.*
                    FROM
                      `$this->codesTable` c
                      WHERE c.`file_id` = %d
                      AND c.`is_used` = 1  
                ",
                $fileId
            )
        );

        return $result;
    }

    public function getFreeCode($codeType, $offset = 0)
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $this->codesTable WHERE `is_used` = 0 LIMIT %d, 1", $offset)
        );
    }

    public function setCodeIsUsedFlag($codeRowId, $flagValue = 1)
    {
        return $this->wpdb->update($this->codesTable, array('is_used' => $flagValue), array('id' => $codeRowId));
    }

    public function updateCodeRow($codeRowId, $data)
    {
        return $this->wpdb->update($this->codesTable, $data, array('id' => $codeRowId));
    }

    public function setCodeIsUsedFlagByCodeValue($codeValue, $flagValue = 1)
    {
        return $this->wpdb->update($this->codesTable, array('is_used' => $flagValue), array('code' => $codeValue));
    }

    public function upload_dir($pathdata)
    {
        if ( isset( $_POST['action'] ) && 'uegen_upload_spreadsheet_file' === sanitize_text_field($_POST['action']) ) {
            $pathdata['path']   = $pathdata['basedir'] . '/upc_ean_code_generator';
            $pathdata['url']    = $pathdata['baseurl'] . '/upc_ean_code_generator';
            $pathdata['subdir'] = '/';
        }

        return $pathdata;
    }
}
