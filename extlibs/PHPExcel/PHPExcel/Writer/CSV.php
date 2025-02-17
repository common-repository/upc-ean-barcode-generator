<?php


class PHPExcel_Writer_CSV extends PHPExcel_Writer_Abstract implements PHPExcel_Writer_IWriter {
	private $_phpExcel;

	private $_delimiter	= ',';

	private $_enclosure	= '"';

	private $_lineEnding	= PHP_EOL;

	private $_sheetIndex	= 0;

	private $_useBOM = false;

	private $_excelCompatibility = false;

	public function __construct(PHPExcel $phpExcel) {
		$this->_phpExcel	= $phpExcel;
	}

	public function save($pFilename = null) {
		$sheet = $this->_phpExcel->getSheet($this->_sheetIndex);

		$saveDebugLog = PHPExcel_Calculation::getInstance($this->_phpExcel)->getDebugLog()->getWriteDebugLog();
		PHPExcel_Calculation::getInstance($this->_phpExcel)->getDebugLog()->setWriteDebugLog(FALSE);
		$saveArrayReturnType = PHPExcel_Calculation::getArrayReturnType();
		PHPExcel_Calculation::setArrayReturnType(PHPExcel_Calculation::RETURN_ARRAY_AS_VALUE);

		$fileHandle = fopen($pFilename, 'wb+');
		if ($fileHandle === false) {
			throw new PHPExcel_Writer_Exception("Could not open file $pFilename for writing.");
		}

		if ($this->_excelCompatibility) {
			fwrite($fileHandle, "\xEF\xBB\xBF");	
			$this->setEnclosure('"');				
			$this->setDelimiter(";");			    
            $this->setLineEnding("\r\n");
			fwrite($fileHandle, 'sep=' . $this->getDelimiter() . $this->_lineEnding);
		} elseif ($this->_useBOM) {
			fwrite($fileHandle, "\xEF\xBB\xBF");
		}

		$maxCol = $sheet->getHighestDataColumn();
		$maxRow = $sheet->getHighestDataRow();

		for($row = 1; $row <= $maxRow; ++$row) {
			$cellsArray = $sheet->rangeToArray('A'.$row.':'.$maxCol.$row,'', $this->_preCalculateFormulas);
			$this->_writeLine($fileHandle, $cellsArray[0]);
		}

		fclose($fileHandle);

		PHPExcel_Calculation::setArrayReturnType($saveArrayReturnType);
		PHPExcel_Calculation::getInstance($this->_phpExcel)->getDebugLog()->setWriteDebugLog($saveDebugLog);
	}

	public function getDelimiter() {
		return $this->_delimiter;
	}

	public function setDelimiter($pValue = ',') {
		$this->_delimiter = $pValue;
		return $this;
	}

	public function getEnclosure() {
		return $this->_enclosure;
	}

	public function setEnclosure($pValue = '"') {
		if ($pValue == '') {
			$pValue = null;
		}
		$this->_enclosure = $pValue;
		return $this;
	}

	public function getLineEnding() {
		return $this->_lineEnding;
	}

	public function setLineEnding($pValue = PHP_EOL) {
		$this->_lineEnding = $pValue;
		return $this;
	}

	public function getUseBOM() {
		return $this->_useBOM;
	}

	public function setUseBOM($pValue = false) {
		$this->_useBOM = $pValue;
		return $this;
	}

	public function getExcelCompatibility() {
		return $this->_excelCompatibility;
	}

	public function setExcelCompatibility($pValue = false) {
		$this->_excelCompatibility = $pValue;
		return $this;
	}

	public function getSheetIndex() {
		return $this->_sheetIndex;
	}

	public function setSheetIndex($pValue = 0) {
		$this->_sheetIndex = $pValue;
		return $this;
	}

	private function _writeLine($pFileHandle = null, $pValues = null) {
		if (is_array($pValues)) {
			$writeDelimiter = false;

			$line = '';

			foreach ($pValues as $element) {
				$element = str_replace($this->_enclosure, $this->_enclosure . $this->_enclosure, $element);

				if ($writeDelimiter) {
					$line .= $this->_delimiter;
				} else {
					$writeDelimiter = true;
				}

				$line .= $this->_enclosure . $element . $this->_enclosure;
			}

			$line .= $this->_lineEnding;

            fwrite($pFileHandle, $line);
		} else {
			throw new PHPExcel_Writer_Exception("Invalid data row passed to CSV writer.");
		}
	}

}
