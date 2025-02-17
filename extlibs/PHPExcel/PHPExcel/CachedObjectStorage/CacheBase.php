<?php


abstract class PHPExcel_CachedObjectStorage_CacheBase {

	protected $_parent;

	protected $_currentObject = null;

	protected $_currentObjectID = null;


	protected $_currentCellIsDirty = true;

	protected $_cellCache = array();


	public function __construct(PHPExcel_Worksheet $parent) {
		$this->_parent = $parent;
	}	


	public function getParent()
	{
		return $this->_parent;
	}

	public function isDataSet($pCoord) {
		if ($pCoord === $this->_currentObjectID) {
			return true;
		}
		return isset($this->_cellCache[$pCoord]);
	}	


	public function moveCell($fromAddress, $toAddress) {
		if ($fromAddress === $this->_currentObjectID) {
			$this->_currentObjectID = $toAddress;
		}
		$this->_currentCellIsDirty = true;
		if (isset($this->_cellCache[$fromAddress])) {
			$this->_cellCache[$toAddress] = &$this->_cellCache[$fromAddress];
			unset($this->_cellCache[$fromAddress]);
		}

		return TRUE;
	}	


	public function updateCacheData(PHPExcel_Cell $cell) {
		return $this->addCacheData($cell->getCoordinate(),$cell);
	}	


	public function deleteCacheData($pCoord) {
		if ($pCoord === $this->_currentObjectID) {
			$this->_currentObject->detach();
			$this->_currentObjectID = $this->_currentObject = null;
		}

		if (is_object($this->_cellCache[$pCoord])) {
			$this->_cellCache[$pCoord]->detach();
			unset($this->_cellCache[$pCoord]);
		}
		$this->_currentCellIsDirty = false;
	}	


	public function getCellList() {
		return array_keys($this->_cellCache);
	}	


	public function getSortedCellList() {
		$sortKeys = array();
		foreach ($this->getCellList() as $coord) {
			sscanf($coord,'%[A-Z]%d', $column, $row);
			$sortKeys[sprintf('%09d%3s',$row,$column)] = $coord;
		}
		ksort($sortKeys);

		return array_values($sortKeys);
	}	



	public function getHighestRowAndColumn()
	{
		$col = array('A' => '1A');
		$row = array(1);
		foreach ($this->getCellList() as $coord) {
			sscanf($coord,'%[A-Z]%d', $c, $r);
			$row[$r] = $r;
			$col[$c] = strlen($c).$c;
		}
		if (!empty($row)) {
			$highestRow = max($row);
			$highestColumn = substr(max($col),1);
		}

		return array( 'row'	   => $highestRow,
					  'column' => $highestColumn
					);
	}


	public function getCurrentAddress()
	{
		return $this->_currentObjectID;
	}

	public function getCurrentColumn()
	{
		sscanf($this->_currentObjectID, '%[A-Z]%d', $column, $row);
		return $column;
	}

	public function getCurrentRow()
	{
		sscanf($this->_currentObjectID, '%[A-Z]%d', $column, $row);
		return (integer) $row;
	}

	public function getHighestColumn($row = null)
	{
        if ($row == null) {
    		$colRow = $this->getHighestRowAndColumn();
	    	return $colRow['column'];
        }

        $columnList = array(1);
        foreach ($this->getCellList() as $coord) {
            sscanf($coord,'%[A-Z]%d', $c, $r);
            if ($r != $row) {
                continue;
            }
            $columnList[] = PHPExcel_Cell::columnIndexFromString($c);
        }
        return PHPExcel_Cell::stringFromColumnIndex(max($columnList) - 1);
    }

	public function getHighestRow($column = null)
	{
        if ($column == null) {
	    	$colRow = $this->getHighestRowAndColumn();
    		return $colRow['row'];
        }

        $rowList = array(0);
        foreach ($this->getCellList() as $coord) {
            sscanf($coord,'%[A-Z]%d', $c, $r);
            if ($c != $column) {
                continue;
            }
            $rowList[] = $r;
        }

        return max($rowList);
	}


	protected function _getUniqueID() {
		if (function_exists('posix_getpid')) {
			$baseUnique = posix_getpid();
		} else {
			$baseUnique = mt_rand();
		}
		return uniqid($baseUnique,true);
	}

	public function copyCellCollection(PHPExcel_Worksheet $parent) {
		$this->_currentCellIsDirty;
        $this->_storeData();

		$this->_parent = $parent;
		if (($this->_currentObject !== NULL) && (is_object($this->_currentObject))) {
			$this->_currentObject->attach($this);
		}
	}	


	public static function cacheMethodIsAvailable() {
		return true;
	}

}
