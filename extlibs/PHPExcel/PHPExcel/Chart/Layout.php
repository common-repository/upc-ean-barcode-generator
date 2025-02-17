<?php


class PHPExcel_Chart_Layout
{
	private $_layoutTarget = NULL;

	private $_xMode		= NULL;

	private $_yMode		= NULL;

	private $_xPos		= NULL;

	private $_yPos		= NULL;

	private $_width		= NULL;

	private $_height	= NULL;

	private $_showLegendKey	= NULL;

	private $_showVal	= NULL;

	private $_showCatName	= NULL;

	private $_showSerName	= NULL;

	private $_showPercent	= NULL;

	private $_showBubbleSize	= NULL;

	private $_showLeaderLines	= NULL;


	public function __construct($layout=array())
	{
		if (isset($layout['layoutTarget']))	{ $this->_layoutTarget	= $layout['layoutTarget'];	}
		if (isset($layout['xMode']))		{ $this->_xMode			= $layout['xMode'];			}
		if (isset($layout['yMode']))		{ $this->_yMode			= $layout['yMode'];			}
		if (isset($layout['x']))			{ $this->_xPos			= (float) $layout['x'];		}
		if (isset($layout['y']))			{ $this->_yPos			= (float) $layout['y'];		}
		if (isset($layout['w']))			{ $this->_width			= (float) $layout['w'];		}
		if (isset($layout['h']))			{ $this->_height		= (float) $layout['h'];		}
	}

	public function getLayoutTarget() {
		return $this->_layoutTarget;
	}

	public function setLayoutTarget($value) {
		$this->_layoutTarget = $value;
        return $this;
	}

	public function getXMode() {
		return $this->_xMode;
	}

	public function setXMode($value) {
		$this->_xMode = $value;
        return $this;
	}

	public function getYMode() {
		return $this->_yMode;
	}

	public function setYMode($value) {
		$this->_yMode = $value;
        return $this;
	}

	public function getXPosition() {
		return $this->_xPos;
	}

	public function setXPosition($value) {
		$this->_xPos = $value;
        return $this;
	}

	public function getYPosition() {
		return $this->_yPos;
	}

	public function setYPosition($value) {
		$this->_yPos = $value;
        return $this;
	}

	public function getWidth() {
		return $this->_width;
	}

	public function setWidth($value) {
		$this->_width = $value;
        return $this;
	}

	public function getHeight() {
		return $this->_height;
	}

	public function setHeight($value) {
		$this->_height = $value;
        return $this;
	}


	public function getShowLegendKey() {
		return $this->_showLegendKey;
	}

	public function setShowLegendKey($value) {
		$this->_showLegendKey = $value;
        return $this;
	}

	public function getShowVal() {
		return $this->_showVal;
	}

	public function setShowVal($value) {
		$this->_showVal = $value;
        return $this;
	}

	public function getShowCatName() {
		return $this->_showCatName;
	}

	public function setShowCatName($value) {
		$this->_showCatName = $value;
        return $this;
	}

	public function getShowSerName() {
		return $this->_showSerName;
	}

	public function setShowSerName($value) {
		$this->_showSerName = $value;
        return $this;
	}

	public function getShowPercent() {
		return $this->_showPercent;
	}

	public function setShowPercent($value) {
		$this->_showPercent = $value;
        return $this;
	}

	public function getShowBubbleSize() {
		return $this->_showBubbleSize;
	}

	public function setShowBubbleSize($value) {
		$this->_showBubbleSize = $value;
        return $this;
	}

	public function getShowLeaderLines() {
		return $this->_showLeaderLines;
	}

	public function setShowLeaderLines($value) {
		$this->_showLeaderLines = $value;
        return $this;
	}

}
