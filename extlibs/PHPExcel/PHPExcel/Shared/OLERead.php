<?php

defined('IDENTIFIER_OLE') ||
    define('IDENTIFIER_OLE', pack('CCCCCCCC', 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1));

class PHPExcel_Shared_OLERead {
	private $data = '';

	const IDENTIFIER_OLE = IDENTIFIER_OLE;

	const BIG_BLOCK_SIZE					= 0x200;

	const SMALL_BLOCK_SIZE					= 0x40;

	const PROPERTY_STORAGE_BLOCK_SIZE		= 0x80;

	const SMALL_BLOCK_THRESHOLD				= 0x1000;

	const NUM_BIG_BLOCK_DEPOT_BLOCKS_POS	= 0x2c;
	const ROOT_START_BLOCK_POS				= 0x30;
	const SMALL_BLOCK_DEPOT_BLOCK_POS		= 0x3c;
	const EXTENSION_BLOCK_POS				= 0x44;
	const NUM_EXTENSION_BLOCK_POS			= 0x48;
	const BIG_BLOCK_DEPOT_BLOCKS_POS		= 0x4c;

	const SIZE_OF_NAME_POS					= 0x40;
	const TYPE_POS							= 0x42;
	const START_BLOCK_POS					= 0x74;
	const SIZE_POS							= 0x78;



	public $wrkbook						= null;
	public $summaryInformation			= null;
	public $documentSummaryInformation	= null;


	public function read($sFileName)
	{
		if(!is_readable($sFileName)) {
			throw new PHPExcel_Reader_Exception("Could not open " . $sFileName . " for reading! File does not exist, or it is not readable.");
		}

		$this->data = file_get_contents($sFileName, FALSE, NULL, 0, 8);

		if ($this->data != self::IDENTIFIER_OLE) {
			throw new PHPExcel_Reader_Exception('The filename ' . $sFileName . ' is not recognised as an OLE file');
		}

		$this->data = file_get_contents($sFileName);

		$this->numBigBlockDepotBlocks = self::_GetInt4d($this->data, self::NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);

		$this->rootStartBlock = self::_GetInt4d($this->data, self::ROOT_START_BLOCK_POS);

		$this->sbdStartBlock = self::_GetInt4d($this->data, self::SMALL_BLOCK_DEPOT_BLOCK_POS);

		$this->extensionBlock = self::_GetInt4d($this->data, self::EXTENSION_BLOCK_POS);

		$this->numExtensionBlocks = self::_GetInt4d($this->data, self::NUM_EXTENSION_BLOCK_POS);

		$bigBlockDepotBlocks = array();
		$pos = self::BIG_BLOCK_DEPOT_BLOCKS_POS;

		$bbdBlocks = $this->numBigBlockDepotBlocks;

		if ($this->numExtensionBlocks != 0) {
			$bbdBlocks = (self::BIG_BLOCK_SIZE - self::BIG_BLOCK_DEPOT_BLOCKS_POS)/4;
		}

		for ($i = 0; $i < $bbdBlocks; ++$i) {
			  $bigBlockDepotBlocks[$i] = self::_GetInt4d($this->data, $pos);
			  $pos += 4;
		}

		for ($j = 0; $j < $this->numExtensionBlocks; ++$j) {
			$pos = ($this->extensionBlock + 1) * self::BIG_BLOCK_SIZE;
			$blocksToRead = min($this->numBigBlockDepotBlocks - $bbdBlocks, self::BIG_BLOCK_SIZE / 4 - 1);

			for ($i = $bbdBlocks; $i < $bbdBlocks + $blocksToRead; ++$i) {
				$bigBlockDepotBlocks[$i] = self::_GetInt4d($this->data, $pos);
				$pos += 4;
			}

			$bbdBlocks += $blocksToRead;
			if ($bbdBlocks < $this->numBigBlockDepotBlocks) {
				$this->extensionBlock = self::_GetInt4d($this->data, $pos);
			}
		}

		$pos = 0;
		$this->bigBlockChain = '';
		$bbs = self::BIG_BLOCK_SIZE / 4;
		for ($i = 0; $i < $this->numBigBlockDepotBlocks; ++$i) {
			$pos = ($bigBlockDepotBlocks[$i] + 1) * self::BIG_BLOCK_SIZE;

			$this->bigBlockChain .= substr($this->data, $pos, 4*$bbs);
			$pos += 4*$bbs;
		}

		$pos = 0;
		$sbdBlock = $this->sbdStartBlock;
		$this->smallBlockChain = '';
		while ($sbdBlock != -2) {
			$pos = ($sbdBlock + 1) * self::BIG_BLOCK_SIZE;

			$this->smallBlockChain .= substr($this->data, $pos, 4*$bbs);
			$pos += 4*$bbs;

			$sbdBlock = self::_GetInt4d($this->bigBlockChain, $sbdBlock*4);
		}

		$block = $this->rootStartBlock;
		$this->entry = $this->_readData($block);

		$this->_readPropertySets();
	}

	public function getStream($stream)
	{
		if ($stream === NULL) {
			return null;
		}

		$streamData = '';

		if ($this->props[$stream]['size'] < self::SMALL_BLOCK_THRESHOLD) {
			$rootdata = $this->_readData($this->props[$this->rootentry]['startBlock']);

			$block = $this->props[$stream]['startBlock'];

			while ($block != -2) {
	  			$pos = $block * self::SMALL_BLOCK_SIZE;
				$streamData .= substr($rootdata, $pos, self::SMALL_BLOCK_SIZE);

				$block = self::_GetInt4d($this->smallBlockChain, $block*4);
			}

			return $streamData;
		} else {
			$numBlocks = $this->props[$stream]['size'] / self::BIG_BLOCK_SIZE;
			if ($this->props[$stream]['size'] % self::BIG_BLOCK_SIZE != 0) {
				++$numBlocks;
			}

			if ($numBlocks == 0) return '';

			$block = $this->props[$stream]['startBlock'];

			while ($block != -2) {
				$pos = ($block + 1) * self::BIG_BLOCK_SIZE;
				$streamData .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
				$block = self::_GetInt4d($this->bigBlockChain, $block*4);
			}

			return $streamData;
		}
	}

	private function _readData($bl)
	{
		$block = $bl;
		$data = '';

		while ($block != -2)  {
			$pos = ($block + 1) * self::BIG_BLOCK_SIZE;
			$data .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
			$block = self::_GetInt4d($this->bigBlockChain, $block*4);
		}
		return $data;
	 }

	private function _readPropertySets() {
		$offset = 0;

		$entryLen = strlen($this->entry);
		while ($offset < $entryLen) {
			$d = substr($this->entry, $offset, self::PROPERTY_STORAGE_BLOCK_SIZE);

			$nameSize = ord($d[self::SIZE_OF_NAME_POS]) | (ord($d[self::SIZE_OF_NAME_POS+1]) << 8);

			$type = ord($d[self::TYPE_POS]);

			$startBlock = self::_GetInt4d($d, self::START_BLOCK_POS);

			$size = self::_GetInt4d($d, self::SIZE_POS);

			$name = str_replace("\x00", "", substr($d,0,$nameSize));


			$this->props[] = array (
				'name' => $name,
				'type' => $type,
				'startBlock' => $startBlock,
				'size' => $size);

			$upName = strtoupper($name);

			if (($upName === 'WORKBOOK') || ($upName === 'BOOK')) {
				$this->wrkbook = count($this->props) - 1;
			}
			else if ( $upName === 'ROOT ENTRY' || $upName === 'R') {
				$this->rootentry = count($this->props) - 1;
			}

			if ($name == chr(5) . 'SummaryInformation') {
				$this->summaryInformation = count($this->props) - 1;
			}

			if ($name == chr(5) . 'DocumentSummaryInformation') {
				$this->documentSummaryInformation = count($this->props) - 1;
			}

			$offset += self::PROPERTY_STORAGE_BLOCK_SIZE;
		}

	}

	private static function _GetInt4d($data, $pos)
	{
		$_or_24 = ord($data[$pos + 3]);
		if ($_or_24 >= 128) {
			$_ord_24 = -abs((256 - $_or_24) << 24);
		} else {
			$_ord_24 = ($_or_24 & 127) << 24;
		}
		return ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | $_ord_24;
	}

}
