<?php



class PHPExcel_Writer_Excel5_Xf
{
	private $_isStyleXf;

	private $_fontIndex;

	public $_numberFormatIndex;

	public $_text_justlast;

	public $_fg_color;

	public $_bg_color;

	public $_bottom_color;

	public $_top_color;

	public $_left_color;

	public $_right_color;

	public function __construct(PHPExcel_Style $style = null)
	{
		$this->_isStyleXf =     false;
		$this->_fontIndex      = 0;

		$this->_numberFormatIndex     = 0;

		$this->_text_justlast  = 0;

		$this->_fg_color       = 0x40;
		$this->_bg_color       = 0x41;

		$this->_diag           = 0;

		$this->_bottom_color   = 0x40;
		$this->_top_color      = 0x40;
		$this->_left_color     = 0x40;
		$this->_right_color    = 0x40;
		$this->_diag_color     = 0x40;
		$this->_style = $style;

	}


	function writeXf()
	{
		if ($this->_isStyleXf) {
			$style = 0xFFF5;
		} else {
			$style   = self::_mapLocked($this->_style->getProtection()->getLocked());
			$style  |= self::_mapHidden($this->_style->getProtection()->getHidden()) << 1;
		}

		$atr_num     = ($this->_numberFormatIndex != 0)?1:0;
		$atr_fnt     = ($this->_fontIndex != 0)?1:0;
		$atr_alc     = ((int) $this->_style->getAlignment()->getWrapText()) ? 1 : 0;
		$atr_bdr     = (self::_mapBorderStyle($this->_style->getBorders()->getBottom()->getBorderStyle())   ||
						self::_mapBorderStyle($this->_style->getBorders()->getTop()->getBorderStyle())      ||
						self::_mapBorderStyle($this->_style->getBorders()->getLeft()->getBorderStyle())     ||
						self::_mapBorderStyle($this->_style->getBorders()->getRight()->getBorderStyle()))?1:0;
		$atr_pat     = (($this->_fg_color != 0x40) ||
						($this->_bg_color != 0x41) ||
						self::_mapFillType($this->_style->getFill()->getFillType()))?1:0;
		$atr_prot    = self::_mapLocked($this->_style->getProtection()->getLocked())
						| self::_mapHidden($this->_style->getProtection()->getHidden());

		if (self::_mapBorderStyle($this->_style->getBorders()->getBottom()->getBorderStyle()) == 0) {
			$this->_bottom_color = 0;
		}
		if (self::_mapBorderStyle($this->_style->getBorders()->getTop()->getBorderStyle())  == 0) {
			$this->_top_color = 0;
		}
		if (self::_mapBorderStyle($this->_style->getBorders()->getRight()->getBorderStyle()) == 0) {
			$this->_right_color = 0;
		}
		if (self::_mapBorderStyle($this->_style->getBorders()->getLeft()->getBorderStyle()) == 0) {
			$this->_left_color = 0;
		}
		if (self::_mapBorderStyle($this->_style->getBorders()->getDiagonal()->getBorderStyle()) == 0) {
			$this->_diag_color = 0;
		}

		$record         = 0x00E0;              
		$length         = 0x0014;              

		$ifnt           = $this->_fontIndex;   
		$ifmt           = $this->_numberFormatIndex;  

		$align          = $this->_mapHAlign($this->_style->getAlignment()->getHorizontal());       
		$align         |= (int) $this->_style->getAlignment()->getWrapText()     << 3;
		$align         |= self::_mapVAlign($this->_style->getAlignment()->getVertical())  << 4;
		$align         |= $this->_text_justlast << 7;

		$used_attrib    = $atr_num              << 2;
		$used_attrib   |= $atr_fnt              << 3;
		$used_attrib   |= $atr_alc              << 4;
		$used_attrib   |= $atr_bdr              << 5;
		$used_attrib   |= $atr_pat              << 6;
		$used_attrib   |= $atr_prot             << 7;

		$icv            = $this->_fg_color;      
		$icv           |= $this->_bg_color      << 7;

		$border1        = self::_mapBorderStyle($this->_style->getBorders()->getLeft()->getBorderStyle());          
		$border1       |= self::_mapBorderStyle($this->_style->getBorders()->getRight()->getBorderStyle())         << 4;
		$border1       |= self::_mapBorderStyle($this->_style->getBorders()->getTop()->getBorderStyle())           << 8;
		$border1       |= self::_mapBorderStyle($this->_style->getBorders()->getBottom()->getBorderStyle())        << 12;
		$border1       |= $this->_left_color    << 16;
		$border1       |= $this->_right_color   << 23;

		$diagonalDirection = $this->_style->getBorders()->getDiagonalDirection();
		$diag_tl_to_rb = $diagonalDirection == PHPExcel_Style_Borders::DIAGONAL_BOTH
							|| $diagonalDirection == PHPExcel_Style_Borders::DIAGONAL_DOWN;
		$diag_tr_to_lb = $diagonalDirection == PHPExcel_Style_Borders::DIAGONAL_BOTH
							|| $diagonalDirection == PHPExcel_Style_Borders::DIAGONAL_UP;
		$border1       |= $diag_tl_to_rb        << 30;
		$border1       |= $diag_tr_to_lb        << 31;

		$border2        = $this->_top_color;    
		$border2       |= $this->_bottom_color   << 7;
		$border2       |= $this->_diag_color     << 14;
		$border2       |= self::_mapBorderStyle($this->_style->getBorders()->getDiagonal()->getBorderStyle())           << 21;
		$border2       |= self::_mapFillType($this->_style->getFill()->getFillType())        << 26;

		$header      = pack("vv",       $record, $length);

		$biff8_options  = $this->_style->getAlignment()->getIndent();
		$biff8_options |= (int) $this->_style->getAlignment()->getShrinkToFit() << 4;

		$data  = pack("vvvC", $ifnt, $ifmt, $style, $align);
		$data .= pack("CCC"
			, self::_mapTextRotation($this->_style->getAlignment()->getTextRotation())
			, $biff8_options
			, $used_attrib
			);
		$data .= pack("VVv", $border1, $border2, $icv);

		return($header . $data);
	}

	public function setIsStyleXf($value)
	{
		$this->_isStyleXf = $value;
	}

	function setBottomColor($colorIndex)
	{
		$this->_bottom_color = $colorIndex;
	}

	function setTopColor($colorIndex)
	{
		$this->_top_color = $colorIndex;
	}

	function setLeftColor($colorIndex)
	{
		$this->_left_color = $colorIndex;
	}

	function setRightColor($colorIndex)
	{
		$this->_right_color = $colorIndex;
	}

	function setDiagColor($colorIndex)
	{
		$this->_diag_color = $colorIndex;
	}


	function setFgColor($colorIndex)
	{
		$this->_fg_color = $colorIndex;
	}

	function setBgColor($colorIndex)
	{
		$this->_bg_color = $colorIndex;
	}

	function setNumberFormatIndex($numberFormatIndex)
	{
		$this->_numberFormatIndex = $numberFormatIndex;
	}

	public function setFontIndex($value)
	{
		$this->_fontIndex = $value;
	}

	private static $_mapBorderStyle = array	( PHPExcel_Style_Border::BORDER_NONE				=> 0x00,
											  PHPExcel_Style_Border::BORDER_THIN				=> 0x01,
											  PHPExcel_Style_Border::BORDER_MEDIUM				=> 0x02,
											  PHPExcel_Style_Border::BORDER_DASHED				=> 0x03,
											  PHPExcel_Style_Border::BORDER_DOTTED				=> 0x04,
											  PHPExcel_Style_Border::BORDER_THICK				=> 0x05,
											  PHPExcel_Style_Border::BORDER_DOUBLE				=> 0x06,
											  PHPExcel_Style_Border::BORDER_HAIR				=> 0x07,
											  PHPExcel_Style_Border::BORDER_MEDIUMDASHED		=> 0x08,
											  PHPExcel_Style_Border::BORDER_DASHDOT				=> 0x09,
											  PHPExcel_Style_Border::BORDER_MEDIUMDASHDOT		=> 0x0A,
											  PHPExcel_Style_Border::BORDER_DASHDOTDOT			=> 0x0B,
											  PHPExcel_Style_Border::BORDER_MEDIUMDASHDOTDOT	=> 0x0C,
											  PHPExcel_Style_Border::BORDER_SLANTDASHDOT		=> 0x0D,
											);

	private static function _mapBorderStyle($borderStyle) {
		if (isset(self::$_mapBorderStyle[$borderStyle]))
			return self::$_mapBorderStyle[$borderStyle];
		return 0x00;
	}

	private static $_mapFillType = array( PHPExcel_Style_Fill::FILL_NONE					=> 0x00,
										  PHPExcel_Style_Fill::FILL_SOLID					=> 0x01,
										  PHPExcel_Style_Fill::FILL_PATTERN_MEDIUMGRAY		=> 0x02,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKGRAY		=> 0x03,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTGRAY		=> 0x04,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKHORIZONTAL	=> 0x05,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKVERTICAL	=> 0x06,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKDOWN		=> 0x07,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKUP			=> 0x08,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKGRID		=> 0x09,
										  PHPExcel_Style_Fill::FILL_PATTERN_DARKTRELLIS		=> 0x0A,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTHORIZONTAL	=> 0x0B,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTVERTICAL	=> 0x0C,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTDOWN		=> 0x0D,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTUP			=> 0x0E,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTGRID		=> 0x0F,
										  PHPExcel_Style_Fill::FILL_PATTERN_LIGHTTRELLIS	=> 0x10,
										  PHPExcel_Style_Fill::FILL_PATTERN_GRAY125			=> 0x11,
										  PHPExcel_Style_Fill::FILL_PATTERN_GRAY0625		=> 0x12,
										  PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR			=> 0x00,	
										  PHPExcel_Style_Fill::FILL_GRADIENT_PATH			=> 0x00,	
										);
	private static function _mapFillType($fillType) {
		if (isset(self::$_mapFillType[$fillType]))
			return self::$_mapFillType[$fillType];
		return 0x00;
	}

	private static $_mapHAlign = array( PHPExcel_Style_Alignment::HORIZONTAL_GENERAL			=> 0,
										PHPExcel_Style_Alignment::HORIZONTAL_LEFT				=> 1,
										PHPExcel_Style_Alignment::HORIZONTAL_CENTER				=> 2,
										PHPExcel_Style_Alignment::HORIZONTAL_RIGHT				=> 3,
										PHPExcel_Style_Alignment::HORIZONTAL_FILL				=> 4,
										PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY			=> 5,
										PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS	=> 6,
									  );
	private function _mapHAlign($hAlign)
	{
		if (isset(self::$_mapHAlign[$hAlign]))
			return self::$_mapHAlign[$hAlign];
		return 0;
	}

	private static $_mapVAlign = array( PHPExcel_Style_Alignment::VERTICAL_TOP		=> 0,
										PHPExcel_Style_Alignment::VERTICAL_CENTER	=> 1,
										PHPExcel_Style_Alignment::VERTICAL_BOTTOM	=> 2,
										PHPExcel_Style_Alignment::VERTICAL_JUSTIFY	=> 3,
									  );
	private static function _mapVAlign($vAlign) {
		if (isset(self::$_mapVAlign[$vAlign]))
			return self::$_mapVAlign[$vAlign];
		return 2;
	}

	private static function _mapTextRotation($textRotation) {
		if ($textRotation >= 0) {
			return $textRotation;
		}
		if ($textRotation == -165) {
			return 255;
		}
		if ($textRotation < 0) {
			return 90 - $textRotation;
		}
	}

	private static function _mapLocked($locked) {
		switch ($locked) {
			case PHPExcel_Style_Protection::PROTECTION_INHERIT:		return 1;
			case PHPExcel_Style_Protection::PROTECTION_PROTECTED:	return 1;
			case PHPExcel_Style_Protection::PROTECTION_UNPROTECTED:	return 0;
			default:												return 1;
		}
	}

	private static function _mapHidden($hidden) {
		switch ($hidden) {
			case PHPExcel_Style_Protection::PROTECTION_INHERIT:		return 0;
			case PHPExcel_Style_Protection::PROTECTION_PROTECTED:	return 1;
			case PHPExcel_Style_Protection::PROTECTION_UNPROTECTED:	return 0;
			default:												return 0;
		}
	}

}
